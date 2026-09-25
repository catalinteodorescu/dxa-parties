<?php

namespace App\Services;

use App\Support\PaymentMethods;
use DomainException;

/**
 * DXA: adaugat (Recepție). Plata introdusă PE TOTAL (poate fi mixtă) -> listă [[metodă, bani], ...] validată.
 * Folosit de EntryRecorder (intrări) și TokenLedger (vânzări de tokeni).
 */
class PaymentRows
{
    /**
     * Rândurile goale se ignoră; aceeași metodă introdusă de mai multe ori se adună; suma trebuie să fie exact
     * totalul; la total 0 nu se acceptă plăți.
     *
     * @param  array<string, string>  $allowed  metode permise (cheie => etichetă)
     * @param  array<int, array{method?: string, amount?: mixed}>  $payments
     * @param  string  $where  ex. „la intrare” (în mesajul „Metoda X nu este acceptată <where> la această petrecere.”)
     * @return array<int, array{0: string, 1: int}> [metodă, cenți]
     */
    public static function normalize(array $allowed, array $payments, int $totalCents, string $where, string $freeMessage = 'Nu se înregistrează nicio plată.'): array
    {
        $byMethod = [];

        foreach ($payments as $p) {
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            if ($raw === '' || ! is_numeric($raw) || (float) $raw <= 0) {
                continue;
            }

            $method = (string) ($p['method'] ?? '');
            if (! isset($allowed[$method])) {
                $label = PaymentMethods::labels()[$method] ?? $method;
                throw new DomainException('Metoda „'.$label.'” nu este acceptată '.$where.' la această petrecere.');
            }

            $byMethod[$method] = ($byMethod[$method] ?? 0) + self::cents((float) $raw);
        }

        $paid = array_sum($byMethod);

        if ($totalCents === 0) {
            if ($paid > 0) {
                throw new DomainException($freeMessage);
            }

            return [];
        }

        if ($paid !== $totalCents) {
            throw new DomainException('Plata ('.self::money($paid / 100).' lei) nu se potrivește cu totalul ('.self::money($totalCents / 100).' lei).');
        }

        $queue = [];
        foreach ($byMethod as $method => $cents) {
            $queue[] = [$method, $cents];
        }

        return $queue;
    }

    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
