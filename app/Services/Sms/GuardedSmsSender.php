<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Support\Phone;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;

/**
 * DXA: adaugat (runda 81). Protecție împotriva trimiterii în masă de SMS-uri (cineva care cere coduri pe numere la întâmplare ne costă bani).
 * Limitele existente sunt pe telefon; aici sunt cele care le lipseau, valabile pentru ORICE SMS din aplicație:
 *   - pe destinatar: cel mult `per_phone` SMS pe oră, indiferent de tip;
 *   - pe adresa IP a cererii: cel mult `per_ip` pe oră (mare, pentru că la o petrecere zeci de oameni se înscriu de pe același wifi);
 *   - pe instalare: cel mult `daily` pe zi, o siguranță finală (0 = fără limită).
 * La depășire aruncă SmsLimitException (DomainException): ecranele o prind și arată mesajul. Se numără doar trimiterile încercate
 * aici; contorul de facturare e separat (CountingSmsSender, în afara acestuia).
 */
class GuardedSmsSender implements SmsSender
{
    public const MESSAGE = 'Prea multe SMS-uri trimise. Încearcă din nou mai târziu.';

    public function __construct(private SmsSender $inner) {}

    public function send(string $phone, string $message): void
    {
        $perPhone = (int) config('sms.limits.per_phone', 6);
        $perIp = (int) config('sms.limits.per_ip', 60);
        $daily = (int) config('sms.limits.daily', 1000);

        $to = Phone::normalize($phone) ?? $phone;
        $ip = Request::ip();

        $checks = [['sms:to:'.sha1($to), $perPhone, 3600]];
        if ($ip !== null && $perIp > 0) {
            $checks[] = ['sms:ip:'.$ip, $perIp, 3600];
        }
        if ($daily > 0) {
            $checks[] = ['sms:day:'.now()->format('Y-m-d'), $daily, 86400];
        }

        foreach ($checks as [$key, $max]) {
            if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
                report(new SmsLimitException("Limita de SMS atinsă ({$key})."));

                throw new SmsLimitException(self::MESSAGE);
            }
        }

        $this->inner->send($phone, $message);

        foreach ($checks as [$key, $max, $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }
}
