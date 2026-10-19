<?php

namespace App\Services\Plan;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (runda 80). Contorul lunar de SMS și raportul pentru pagina proprietarului.
 *
 * Cota inclusă vine din planul activ (`limits.sms_included`, gol = nelimitat); peste cotă se facturează `plans.sms_overage_eur`
 * (euro, fără TVA) pe SMS. Fiecare rând păstrează cota și prețul de la ultimul SMS al lunii, ca istoricul să rămână stabil.
 */
final class SmsUsage
{
    public const DEFAULT_OVERAGE_EUR = 0.07;

    public static function period(?Carbon $at = null): string
    {
        return ($at ?? now())->format('Y-m');
    }

    public static function overagePrice(): float
    {
        $plan = PlanGate::plan();

        return $plan ? (float) $plan->sms_overage_eur : self::DEFAULT_OVERAGE_EUR;
    }

    /** Numără un SMS trimis acum. */
    public static function record(int $count = 1): void
    {
        $period = self::period();
        $now = now();

        DB::table('sms_usage')->insertOrIgnore(['period' => $period, 'sent' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sms_usage')->where('period', $period)->increment('sent', $count, [
            'included' => PlanGate::limit('sms_included'),
            'overage_eur' => self::overagePrice(),
            'updated_at' => $now,
        ]);
    }

    /** Adaugă costul real al unui SMS (eurocenți, cum îl raportează furnizorul) la luna curentă. */
    public static function addCost(float $eurocents): void
    {
        $period = self::period();
        $now = now();

        DB::table('sms_usage')->insertOrIgnore(['period' => $period, 'sent' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sms_usage')->where('period', $period)->increment('cost_eurocents', $eurocents, ['updated_at' => $now]);
    }

    /**
     * Raportul unei luni: trimise, cota, depășirea și suma estimată (euro, fără TVA).
     *
     * @return array{period: string, sent: int, included: ?int, overage: int, price: float, amount: float, cost: float}
     */
    public static function report(?string $period = null): array
    {
        $period ??= self::period();
        $row = DB::table('sms_usage')->where('period', $period)->first();
        $current = $period === self::period();

        $sent = (int) ($row->sent ?? 0);
        // luna curentă folosește planul de acum; lunile trecute, instantaneul păstrat
        $included = $current || ! $row ? PlanGate::limit('sms_included') : ($row->included === null ? null : (int) $row->included);
        $price = $current || ! $row ? self::overagePrice() : (float) ($row->overage_eur ?? self::DEFAULT_OVERAGE_EUR);
        $overage = $included === null ? 0 : max(0, $sent - $included);

        return [
            'period' => $period,
            'sent' => $sent,
            'included' => $included,
            'overage' => $overage,
            'price' => $price,
            'amount' => round($overage * $price, 2),
            'cost' => round(((float) ($row->cost_eurocents ?? 0)) / 100, 2),   // costul real plătit furnizorului, în euro
        ];
    }

    /** Luna curentă și cele dinaintea ei (cele cu SMS sau luna curentă), cele mai noi întâi. @return list<array> */
    public static function history(int $months = 6): array
    {
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $period = self::period(now()->startOfMonth()->subMonths($i));
            $r = self::report($period);
            if ($i === 0 || $r['sent'] > 0) {
                $out[] = $r;
            }
        }

        return $out;
    }
}
