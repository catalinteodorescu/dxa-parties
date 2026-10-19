<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * DXA: adaugat (runda 82). Citiri din contul SMSO: creditul rămas (GET /credit-check) și expeditorii disponibili (GET /senders).
 * Contul e comun tuturor instalărilor, deci creditul e același peste tot. Folosit de comanda `app:sms-status` și de pagina Proprietar
 * (creditul se ține 5 minute în cache, ca pagina să nu apese pe API la fiecare deschidere).
 */
class SmsoClient
{
    public const BASE = 'https://app.smso.ro/api/v1';

    public function __construct(private string $apiKey, private int $timeout = 8) {}

    public static function fromConfig(): ?self
    {
        $key = (string) config('sms.smso.key');

        return $key === '' ? null : new self($key, (int) config('sms.smso.timeout', 10));
    }

    /** Creditul rămas (în euro, cum îl dă SMSO) sau null dacă nu s-a putut citi. */
    public function credit(): ?float
    {
        try {
            $r = Http::withHeaders(['X-Authorization' => $this->apiKey])->timeout($this->timeout)->get(self::BASE.'/credit-check');
            $v = $r->successful() ? $r->json('credit_value') : null;

            return is_numeric($v) ? (float) $v : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<array{id: int, name: string, price: float|int|null}> sau [] dacă nu s-a putut citi */
    public function senders(): array
    {
        try {
            $r = Http::withHeaders(['X-Authorization' => $this->apiKey])->timeout($this->timeout)->get(self::BASE.'/senders');
            if (! $r->successful() || ! is_array($r->json())) {
                return [];
            }

            return collect($r->json())->filter(fn ($s) => is_array($s) && isset($s['id']))
                ->map(fn ($s) => ['id' => (int) $s['id'], 'name' => (string) ($s['name'] ?? ''), 'price' => $s['pricePerMessage'] ?? null])->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Creditul din cache (5 minute) pentru pagini; null dacă driverul nu e SMSO sau nu s-a putut citi. */
    public static function creditCached(): ?float
    {
        if (config('sms.driver') !== 'smso' || ! ($client = self::fromConfig())) {
            return null;
        }

        return Cache::remember('sms.smso.credit', 300, fn () => $client->credit());
    }
}
