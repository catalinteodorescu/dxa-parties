<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Punctul unic de creare a unei vanzari (folosit acum de formularul din admin, iar
 * mai tarziu de API-ul aplicatiei DXA - Bar). NU atinge stocul: stocul se posteaza
 * la finalizarea Raportarii, din vanzarile grupului (vezi StockReport::finalize()).
 *
 * Linii: [['menu_item_id' => int, 'qty' => numeric], ...]
 * Plati: [['method' => <cheie din Setari > Metode de plata | benefit>, 'amount' => numeric (lei), 'tokens' => int (doar token)], ...]
 *        La method = token, amount se calculeaza din tokens x cursul curent (Setari).
 *        Metoda trebuie sa fie activa in Setari si acceptata de petrecerea sesiunii (PaymentMethods::forBar);
 *        `benefit` (voucher) e mereu permis. $enforceMethods = false DOAR pentru seedere/importuri istorice.
 * Client: $participantId (optional) = participantul identificat -> sales.customer_id; $identifiedBy = cum a fost identificat
 *        (`qr` din PWA, `phone` cand e cautat dupa telefon/nume).
 *
 * Arunca \DomainException cu un mesaj gata de afisat daca datele nu sunt valide.
 */
class SaleRecorder
{
    /** Toleranta la compararea totalului cu suma platilor (lei). */
    private const EPSILON = 0.01;

    public static function record(
        ?SalesGroup $group,
        array $lines,
        array $payments,
        string $source = 'manual',
        ?int $adminId = null,
        array $extra = [],
        bool $enforceMethods = true,
        ?int $participantId = null,
        string $identifiedBy = 'phone',
    ): Sale {
        // Participantul (client identificat): validat inainte de tranzactie; se salveaza ca customer_id + identified_by.
        if ($participantId !== null) {
            ParticipantRegistry::usable($participantId);
            $extra['customer_id'] = $participantId;
            $extra['identified_by'] = $identifiedBy;
        }

        return DB::transaction(function () use ($group, $lines, $payments, $source, $adminId, $extra, $enforceMethods) {
            // Sesiunea (optionala) se reincarca cu lock: nu se poate adauga o vanzare intr-o
            // sesiune care se inchide chiar acum (finalizarea unei Raportari).
            if ($group !== null) {
                $group = SalesGroup::query()->lockForUpdate()->findOrFail($group->id);

                if (! $group->isOpen()) {
                    throw new \DomainException('Sesiunea de vânzări este închisă.');
                }
            }

            $lines = array_values(array_filter($lines, fn ($l) => ! empty($l['menu_item_id']) && is_numeric($l['qty'] ?? null) && (float) $l['qty'] > 0));

            if ($lines === []) {
                throw new \DomainException('Adaugă cel puțin un produs.');
            }

            $menuItems = MenuItem::with('recipeLines.stockItem')
                ->whereIn('id', collect($lines)->pluck('menu_item_id')->map(fn ($v) => (int) $v)->unique()->all())
                ->get()
                ->keyBy('id');

            $lineRows = [];
            $total = 0.0;

            foreach ($lines as $l) {
                $mi = $menuItems->get((int) $l['menu_item_id']);
                if (! $mi) {
                    throw new \DomainException('Un produs ales nu mai există.');
                }

                $qty = (float) $l['qty'];
                $unitCost = $mi->costPerUnit();
                $lineTotal = round($qty * (float) $mi->price, 2);
                $total += $lineTotal;

                $lineRows[] = [
                    'menu_item_id' => $mi->id,
                    'qty' => $qty,
                    'unit_price' => $mi->price,
                    'total_price' => $lineTotal,
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
                ];
            }

            $total = round($total, 2);
            $paymentRows = self::normalizePayments($payments, $group, $enforceMethods);
            $paid = round(array_sum(array_column($paymentRows, 'amount')), 2);

            if (abs($paid - $total) > self::EPSILON) {
                throw new \DomainException('Plățile ('.number_format($paid, 2, ',', '.').' lei) nu acoperă exact totalul ('.number_format($total, 2, ',', '.').' lei).');
            }

            $sale = Sale::create(array_merge([
                'sales_group_id' => $group?->id,
                'source' => $source,
                'status' => 'completed',
                'total' => $total,
                'sold_at' => now(),
                'created_by' => $adminId,
            ], $extra));

            $sale->lines()->createMany($lineRows);
            $sale->payments()->createMany($paymentRows);

            return $sale;
        });
    }

    /** @return array<int, array{method:string, amount:float, tokens:?int, token_rate:?float}> */
    private static function normalizePayments(array $payments, ?SalesGroup $group, bool $enforceMethods): array
    {
        $rows = [];
        $labels = PaymentMethods::labels();
        $allowed = $enforceMethods ? PaymentMethods::forBar($group?->party) : [];

        foreach ($payments as $p) {
            $method = (string) ($p['method'] ?? '');

            if (! array_key_exists($method, $labels)) {
                throw new \DomainException('Metodă de plată necunoscută.');
            }

            // Randurile goale se ignora inainte de verificarea metodei (formularul are mereu un rand liber).
            $isToken = $method === PaymentMethods::TOKEN;
            $filled = $isToken
                ? (int) ($p['tokens'] ?? 0) > 0
                : (is_numeric($p['amount'] ?? null) && round((float) $p['amount'], 2) > 0);

            if (! $filled) {
                continue;
            }

            if ($enforceMethods && $method !== PaymentMethods::BENEFIT && ! isset($allowed[$method])) {
                throw new \DomainException('Metoda „'.$labels[$method].'" nu este acceptată'.($group?->party ? ' la această petrecere' : '').'.');
            }

            if ($isToken) {
                $tokens = (int) $p['tokens'];

                $rate = (float) Settings::get('token_rate');
                if ($rate <= 0) {
                    throw new \DomainException('Cursul token → lei nu este setat (Setări).');
                }

                $rows[] = ['method' => 'token', 'amount' => round($tokens * $rate, 2), 'tokens' => $tokens, 'token_rate' => $rate];

                continue;
            }

            $amount = round((float) $p['amount'], 2);

            $rows[] = ['method' => $method, 'amount' => $amount, 'tokens' => null, 'token_rate' => null];
        }

        if ($rows === []) {
            throw new \DomainException('Adaugă cel puțin o plată.');
        }

        return $rows;
    }
}
