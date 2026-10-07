<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Party;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (runda 36). Raportul vânzărilor ONLINE pe o petrecere, din biletele create de TicketOrders::place().
 *
 * Definiții:
 *  - bilete = bilete online neanulate (status ≠ void); cele anulate se numără separat;
 *  - oferite = biletele gratuite dintr-un combo (`combo_free`); gratuite = alte bilete cu preț de listă 0 (ex. „gratis până la 22:00”);
 *    plătite = restul (chiar dacă un cod le-a redus la 0);
 *  - valoare = suma `price` a biletelor (comenzile sunt „de plătit la intrare”, nu există încă plată online; cele plătite cu credite
 *    se numără separat: `credit_orders` / `credit_amount`, runda 50);
 *  - reducere = suma `discount_amount` (doar codurile; reducerile de preț cu oră și treptele sunt deja în prețul de listă). Detaliul pe coduri
 *    și promotori NU e aici: îl dă DiscountCodeStats, afișat în secțiunea „Coduri de reducere” din aceeași pagină;
 *  - locuri la preț special = cât mai rămâne din fiecare treaptă „primele N” (numărate ca la vânzare: TicketOrders::tierSoldCount).
 */
class OnlineSalesReport
{
    /**
     * @return object{has_data: bool, totals: object, types: Collection, combos: Collection, tiers: Collection}
     */
    public static function forParty(Party $party): object
    {
        $rows = Ticket::query()
            ->where('party_id', $party->id)
            ->selectRaw('ticket_type, combo_label, combo_free, status, discount_code_id, COUNT(*) as n, SUM(price) as price_sum, SUM(discount_amount) as discount_sum, SUM(list_price) as list_sum, SUM(CASE WHEN list_price = 0 THEN 1 ELSE 0 END) as zero_list')
            ->groupBy('ticket_type', 'combo_label', 'combo_free', 'status', 'discount_code_id')
            ->get();

        $counted = $rows->where('status', '!=', Ticket::VOID);

        // Tipurile: cele definite acum (în ordinea din admin) + orice nume cu vânzări care nu mai există.
        $defined = collect($party->entryTicketTypes())->keyBy('name');
        $names = $defined->keys()->merge($counted->pluck('ticket_type'))->unique()->values();

        $types = $names->map(fn (string $name) => self::typeRow($party, $name, $defined->get($name)['type'] ?? null, $counted->where('ticket_type', $name)));

        $totals = self::sum($counted);
        $totals->orders = (int) Order::query()->where('party_id', $party->id)->count();
        $totals->buyers = (int) Order::query()->where('party_id', $party->id)->distinct()->count('participant_id');
        $totals->voided = (int) $rows->where('status', Ticket::VOID)->sum('n');
        $creditOrders = Order::query()->where('party_id', $party->id)->where('payment_status', Order::PAY_CREDITS);   // runda 50
        $totals->credit_orders = (int) (clone $creditOrders)->count();
        $totals->credit_amount = round((float) (clone $creditOrders)->sum('total'), 2);
        $totals->limit = $party->tickets_for_sale;
        $totals->left = $party->tickets_for_sale !== null ? max(0, (int) $party->tickets_for_sale - $totals->tickets) : null;

        return (object) [
            'has_data' => $totals->tickets > 0 || $totals->voided > 0,
            'totals' => $totals,
            'types' => $types,
            'combos' => self::comboRows($counted),
            'tiers' => $types->flatMap(fn ($t) => $t->tiers)->values(),
        ];
    }

    /** Totaluri pe un set de rânduri grupate. */
    private static function sum(Collection $g): object
    {
        $tickets = (int) $g->sum('n');
        $offered = (int) $g->where('combo_free', true)->sum('n');
        $free = (int) $g->where('combo_free', false)->sum('zero_list');

        return (object) [
            'tickets' => $tickets,
            'offered' => $offered,
            'free' => $free,
            'paid' => $tickets - $offered - $free,
            'used' => (int) $g->where('status', Ticket::USED)->sum('n'),
            'revenue' => round((float) $g->sum('price_sum'), 2),
            'discount' => round((float) $g->sum('discount_sum'), 2),
        ];
    }

    private static function typeRow(Party $party, string $name, ?array $def, Collection $g): object
    {
        $row = self::sum($g);
        $row->name = $name;
        $row->limit = isset($def['limit']) && is_numeric($def['limit']) ? (int) $def['limit'] : null;
        $row->left = $row->limit !== null ? max(0, $row->limit - $row->tickets) : null;
        $row->defined = $def !== null;
        $row->tiers = $def !== null ? self::tierRows($party, $name, $def) : collect();

        return $row;
    }

    /**
     * Treptele „primele N” ale unui tip, în ordine: câte s-au vândut la fiecare și câte locuri mai sunt la prețul ei.
     * Treapta k acoperă biletele de la (N_{k-1} + 1) la N_k; starea: epuizată / activă (cea de la care se vinde acum) / în așteptare.
     */
    private static function tierRows(Party $party, string $name, array $def): Collection
    {
        $tiers = collect($def['qty_tiers'] ?? [])
            ->filter(fn ($q) => isset($q['price'], $q['first']) && is_numeric($q['price']) && is_numeric($q['first']) && (int) $q['first'] > 0)
            ->sortBy(fn ($q) => (int) $q['first'])
            ->values();
        if ($tiers->isEmpty()) {
            return collect();
        }

        $sold = TicketOrders::tierSoldCount($party, $name);
        $prev = 0;
        $activeFound = false;

        return $tiers->map(function ($q) use ($name, $sold, &$prev, &$activeFound) {
            $first = (int) $q['first'];
            $size = max(0, $first - $prev);
            $inTier = max(0, min($sold, $first) - $prev);
            $left = max(0, $size - $inTier);
            $state = $left === 0 ? 'sold_out' : ($activeFound ? 'pending' : 'active');
            $activeFound = $activeFound || $state === 'active';
            $prev = max($prev, $first);

            return (object) [
                'type' => $name, 'label' => $q['label'] ?? null, 'price' => (float) $q['price'], 'first' => $first,
                'size' => $size, 'sold' => $inTier, 'left' => $left, 'state' => $state,
            ];
        });
    }

    /** Combo-urile vândute: pe tip și combo („3+1”), în seturi. */
    private static function comboRows(Collection $counted): Collection
    {
        return $counted->whereNotNull('combo_label')->groupBy(fn ($r) => $r->ticket_type."\0".$r->combo_label)->map(function ($g) {
            $first = $g->first();
            [$buy, $free] = array_pad(array_map('intval', explode('+', (string) $first->combo_label)), 2, 0);
            $size = max(1, $buy + $free);
            $tickets = (int) $g->sum('n');

            return (object) [
                'type' => $first->ticket_type,
                'label' => $first->combo_label,
                'sets' => intdiv($tickets, $size),
                'tickets' => $tickets,
                'paid' => (int) $g->where('combo_free', false)->sum('n'),
                'offered' => (int) $g->where('combo_free', true)->sum('n'),
                'revenue' => round((float) $g->sum('price_sum'), 2),
                'discount' => round((float) $g->sum('discount_sum'), 2),
            ];
        })->sortByDesc('tickets')->values();
    }
}
