<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Services\Plan\SmsUsage;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * DXA: adaugat (runda 81, revizuit în runda 82 după documentația SMSO). Trimite SMS prin https://app.smso.ro/api/v1/send:
 * POST cu antetul `X-Authorization` (cheia API), `X-Source-App` și câmpurile `to` (+40…), `body`, `sender` (id-ul expeditorului din cont)
 * și `type` (implicit `transactional`: toate mesajele aplicației sunt despre contul sau biletul destinatarului, nu marketing).
 *
 * Răspuns: succes = HTTP 2xx și, dacă JSON-ul are `status`, acela să fie 200. Erori după documentație:
 *   401 cheie invalidă · 402 credit insuficient · 409 prea multe pe minut (se reîncearcă de 2 ori) · 400 cerere invalidă ·
 *   403 / 405 / 422 problemă a destinatarului (cuvinte interzise, dezabonat, număr străin nepermis) -> mesaj către utilizator.
 * Orice eșec aruncă SmsDeliveryException (fără număr în log), deci SMS-ul nu se numără la facturare. La succes, `transaction_cost`
 * (eurocenți) se adaugă la costul real al lunii (SmsUsage::addCost), pentru pagina Proprietar.
 */
class SmsoSmsSender implements SmsSender
{
    public const URL = 'https://app.smso.ro/api/v1/send';

    /** Identifică instalarea în contul SMSO (antetul X-Source-App): numele aplicației din .env, ca să se vadă care client a trimis. */
    public static function sourceApp(): string
    {
        return Str::slug((string) config('app.name')) ?: 'Parties';
    }

    /** Mesajul pentru problemele care țin de destinatar (număr străin, dezabonat, cuvinte interzise). */
    public const RECIPIENT_MESSAGE = 'Nu putem trimite SMS către acest număr.';

    public function __construct(
        private string $apiKey,
        private string $sender,
        private int $timeout = 10,
        private string $url = self::URL,
        private string $type = 'transactional',
        private int $retryDelayMs = 1500,
    ) {}

    public function send(string $phone, string $message): void
    {
        $to = Phone::normalize($phone);
        if ($to === null) {
            throw new RuntimeException('SMSO: număr de telefon invalid.');
        }

        $response = $this->post($to, $message);

        $status = $response->status();
        $json = $response->json();
        $apiStatus = is_array($json) && isset($json['status']) ? (int) $json['status'] : null;
        if ($response->successful() && ($apiStatus === null || $apiStatus === 200)) {
            $cost = is_array($json) ? ($json['transaction_cost'] ?? null) : null;
            if (is_numeric($cost)) {
                try {
                    SmsUsage::addCost((float) $cost);
                } catch (Throwable $e) {
                    report($e);   // costul real e informativ: nu strică trimiterea
                }
            }

            return;
        }

        $code = $response->successful() ? $apiStatus : $status;
        // problemele destinatarului nu sunt o defecțiune a noastră: fără alertă în log
        if (in_array($code, [403, 405, 422], true)) {
            throw new SmsDeliveryException(self::RECIPIENT_MESSAGE);
        }

        report(new RuntimeException('SMSO a refuzat SMS-ul (cod '.$code.'): '.Str::limit((string) $response->body(), 200, '')));

        throw new SmsDeliveryException(SmsDeliveryException::MESSAGE);
    }

    /** Trimite cererea; la 409 (prea multe pe minut) mai încearcă de două ori, după o pauză scurtă. */
    private function post(string $to, string $message): Response
    {
        $attempt = 0;
        while (true) {
            $attempt++;

            try {
                $response = Http::asForm()
                    ->withHeaders(['X-Authorization' => $this->apiKey, 'X-Source-App' => self::sourceApp()])
                    ->timeout($this->timeout)
                    ->post($this->url, ['to' => $to, 'body' => $message, 'sender' => $this->sender, 'type' => $this->type]);
            } catch (ConnectionException $e) {
                report($e);

                throw new SmsDeliveryException(SmsDeliveryException::MESSAGE, previous: $e);
            }

            if ($response->status() !== 409 || $attempt >= 3) {
                return $response;
            }
            usleep($this->retryDelayMs * 1000);
        }
    }
}
