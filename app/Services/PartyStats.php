<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\PartyEntryPayment;
use App\Models\ReceptionReport;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockReportSale;
use App\Models\TokenTransaction;
use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistici pe petrecere, calculate la cerere (fara tabele proprii) DOAR din raportarile FINALIZATE
 * ale petrecerii (stock_reports.party_id) — surse imuabile. O petrecere poate avea mai multe raportari;
 * cifrele se aduna. Vanzarile neraportate nu intra (vezi unreported()).
 *
 * Venit/cost/profit vin din randurile raportarii (aceleasi reguli ca StockReport::totalRevenue/totalCost:
 * cost = costul vanzarilor + costul iesirilor fara vanzare). Bonurile, curba pe ore, platile si
 * "inregistrat de" vin din tabelul `sales` (vanzarile aplicatiei postate de aceste raportari); liniile
 * introduse manual direct in Raportare au venit dar nu au bon/ora — de aceea aceste metrici se
 * calculeaza doar pe vanzarile din aplicatie.
 *
 * Extensie (modulul Recepție): `attendance` e acum null; cand exista intrari/participanti, se completeaza
 * in attendance() (participanti, gratuit vs. platit, tokeni vanduti) iar KPI-urile derivate
 * (venit bar / participant etc.) se adauga in Livewire\Admin\Parties\Stats::kpis() — comparatia le preia singura.
 */
class PartyStats
{
    /** Petrecerile care au cel putin o raportare finalizata (candidate la comparatie), cele mai recente primele. */
    public static function comparableParties(Party $except): Collection
    {
        return Party::query()
            ->whereKeyNot($except->id)
            ->whereIn('id', StockReport::query()->where('status', 'finalized')->whereNotNull('party_id')->select('party_id'))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /** Implicit: petrecerea precedenta de acelasi tip (kind), cu raportari finalizate. */
    public static function defaultCompare(Party $party): ?Party
    {
        return static::comparableParties($party)
            ->filter(fn (Party $p) => $p->kind === $party->kind
                && ($p->start_date->lt($party->start_date) || ($p->start_date->eq($party->start_date) && $p->id < $party->id)))
            ->first();
    }

    public static function finalizedReports(Party $party): EloquentCollection
    {
        return StockReport::query()
            ->where('party_id', $party->id)
            ->where('status', 'finalized')
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /** Vanzari finalizate ale sesiunilor petrecerii, inca neincluse in nicio raportare. @return array{count: int, total: float} */
    public static function unreported(Party $party): array
    {
        $q = Sale::query()
            ->whereNull('report_id')
            ->where('status', 'completed')
            ->whereIn('sales_group_id', SalesGroup::query()->where('party_id', $party->id)->select('id'));

        return ['count' => (int) $q->count(), 'total' => round((float) $q->sum('total'), 2)];
    }

    public static function for(Party $party): object
    {
        $reports = static::finalizedReports($party);
        $ids = $reports->pluck('id')->all();

        $stats = (object) [
            'party' => $party,
            'reports' => $reports,
            'has_data' => $ids !== [],
            'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'margin' => null, 'cost_unknown' => false,
            'losses_cost' => 0.0, 'inventory_cost' => 0.0,
            'tx_count' => 0, 'tx_total' => 0.0, 'avg_ticket' => null,
            'payments' => [], 'payments_total' => 0.0,
            'products' => collect(), 'hours' => [], 'staff' => collect(),
            'closing' => null,
            'attendance' => null,
            // Din intrari (Receptie); null cat nu exista nicio intrare. Vezi attendance().
            'entries_count' => null, 'entries_free_pct' => null, 'entries_revenue' => null, 'bar_per_participant' => null, 'entries_identified' => null,
            // Tokeni (Receptie): vanduti la recepție vs incasati la bar (toate vanzarile finalizate ale sesiunilor petrecerii).
            'tokens' => null, 'tokens_sold' => null, 'tokens_collected' => null,
            // Casa de recepție (raportări de recepție FINALIZATE, adunate); vezi reception(). null cât nu există nicio raportare.
            'reception' => null, 'reception_cash_diff' => null,
            // Participanti (Receptie/Bar): cine a cheltuit cel mai mult; vezi participants().
            'participants' => null, 'bar_identified_pct' => null,
        ];

        // Intrarile se numara imediat (nu asteapta raportari), deci si pentru o petrecere fara raportari finalizate.
        $stats->attendance = static::attendance($party);
        if ($stats->attendance) {
            $stats->entries_count = $stats->attendance->count;
            $stats->entries_free_pct = $stats->attendance->free_pct;
            $stats->entries_revenue = $stats->attendance->revenue;
            $stats->entries_identified = $stats->attendance->identified;
        }

        $stats->tokens = TokenLedger::partyTotals($party);
        if ($stats->tokens) {
            $stats->tokens_sold = $stats->tokens->sold;
            $stats->tokens_collected = $stats->tokens->collected;
        }

        $stats->reception = static::reception($party);
        $stats->reception_cash_diff = $stats->reception?->cash_diff;

        $stats->participants = static::participants($party, $ids);
        $stats->bar_identified_pct = $stats->participants?->bar_identified_pct;

        if ($ids === []) {
            return $stats;
        }

        // Venit / cost din randurile raportarilor.
        $agg = StockReportSale::query()
            ->whereIn('report_id', $ids)
            ->selectRaw('COALESCE(SUM(total_price), 0) as revenue, COALESCE(SUM(total_cost), 0) as cost, SUM(CASE WHEN total_cost IS NULL THEN 1 ELSE 0 END) as unknown_cost')
            ->first();

        // Iesiri fara vanzare: pierderi manuale + lipsuri postate din inventar.
        $lossQuery = fn () => StockMovement::query()->whereIn('report_id', $ids)->where('type', 'out')->whereNull('sale_id');
        $allLosses = round((float) $lossQuery()->sum('total_cost'), 2);
        $inventoryLosses = round((float) $lossQuery()->where('note', StockReport::INVENTORY_DIFF_NOTE)->sum('total_cost'), 2);

        $stats->revenue = round((float) $agg->revenue, 2);
        $stats->cost = round((float) $agg->cost + $allLosses, 2);
        $stats->profit = round($stats->revenue - $stats->cost, 2);
        $stats->margin = $stats->revenue > 0 ? round($stats->profit / $stats->revenue * 100, 1) : null;
        $stats->cost_unknown = (int) $agg->unknown_cost > 0;
        $stats->losses_cost = round($allLosses - $inventoryLosses, 2);
        $stats->inventory_cost = $inventoryLosses;
        $stats->bar_per_participant = $stats->attendance && $stats->attendance->count > 0
            ? round($stats->revenue / $stats->attendance->count, 2)
            : null;

        // Bonuri (vanzari din aplicatie postate de aceste raportari): numar, ora, "inregistrat de".
        $sales = Sale::query()
            ->whereIn('report_id', $ids)
            ->where('status', 'completed')
            ->get(['id', 'sold_at', 'total', 'created_by']);

        $stats->tx_count = $sales->count();
        $stats->tx_total = round((float) $sales->sum('total'), 2);
        $stats->avg_ticket = $stats->tx_count > 0 ? round($stats->tx_total / $stats->tx_count, 2) : null;

        foreach ($sales as $sale) {
            $h = (int) $sale->sold_at->format('G');
            $stats->hours[$h] ??= ['count' => 0, 'total' => 0.0];
            $stats->hours[$h]['count']++;
            $stats->hours[$h]['total'] = round($stats->hours[$h]['total'] + (float) $sale->total, 2);
        }
        ksort($stats->hours);

        $names = Admin::query()->whereIn('id', $sales->pluck('created_by')->filter()->unique()->all())->pluck('name', 'id');
        $stats->staff = $sales->groupBy(fn ($s) => $s->created_by ?? 0)
            ->map(fn ($group, $adminId) => (object) [
                'admin_id' => (int) $adminId,
                'name' => $adminId ? ($names[$adminId] ?? 'Cont șters') : 'Necunoscut',
                'count' => $group->count(),
                'total' => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        // Plati (doar vanzarile aplicatiei).
        $payments = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereIn('sales.report_id', $ids)
            ->where('sales.status', 'completed')
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, SUM(sale_payments.amount) as amount, SUM(sale_payments.tokens) as tokens')
            ->get();
        foreach ($payments as $row) {
            $stats->payments[$row->method] = ['amount' => round((float) $row->amount, 2), 'tokens' => (int) $row->tokens];
        }
        $stats->payments_total = round(array_sum(array_column($stats->payments, 'amount')), 2);

        // Produse.
        $rows = StockReportSale::query()
            ->whereIn('report_id', $ids)
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(qty) as qty, SUM(total_price) as revenue, SUM(total_cost) as cost, SUM(CASE WHEN total_cost IS NULL THEN 1 ELSE 0 END) as unknown_cost')
            ->get();
        $items = MenuItem::query()->whereIn('id', $rows->pluck('menu_item_id')->all())->pluck('name', 'id');
        $stats->products = $rows->map(function ($r) use ($items) {
            $revenue = round((float) $r->revenue, 2);
            $cost = (int) $r->unknown_cost > 0 ? null : round((float) $r->cost, 2);

            return (object) [
                'menu_item_id' => (int) $r->menu_item_id,
                'name' => $items[$r->menu_item_id] ?? 'Produs șters',
                'qty' => (float) $r->qty,
                'revenue' => $revenue,
                'profit' => $cost !== null ? round($revenue - $cost, 2) : null,
            ];
        })->values();

        // Inventar (numaratoare de final): cash/tokeni + produse cu diferente.
        $summaries = $reports->map(fn (StockReport $r) => $r->closingSummary())->filter();
        if ($summaries->isNotEmpty()) {
            $stats->closing = (object) [
                'reports' => $summaries->count(),
                'cash_diff' => $summaries->contains(fn ($s) => $s->cash_diff !== null) ? round((float) $summaries->sum(fn ($s) => $s->cash_diff ?? 0), 2) : null,
                'tokens_diff' => $summaries->contains(fn ($s) => $s->tokens_diff !== null) ? (int) $summaries->sum(fn ($s) => $s->tokens_diff ?? 0) : null,
                'diff_items' => (int) $summaries->sum('diff_items'),
                'inventory_value' => round((float) $summaries->sum('inventory_value'), 2),
                'cost_unknown' => $summaries->contains(fn ($s) => $s->inventory_cost_unknown),
                'clean' => $summaries->every(fn ($s) => $s->clean),
            ];
        }

        return $stats;
    }

    /**
     * Participanti & intrari (modulul Receptie): TOATE intrarile neanulate ale petrecerii, indiferent de raportari.
     * null daca nu exista nicio intrare.
     *
     * `paid`/`free` dupa suma incasata; `grace` = intrari cu reducerea acordata in toleranta; `overridden` = pret
     * schimbat manual (cu motiv); `revenue` = ce s-a incasat la intrare (separat de profitul barului);
     * `payments` = incasari pe metoda; `hours` = intrari pe ore de ceas [ora => ['count' => int, 'revenue' => float]].
     */
    public static function attendance(Party $party): ?object
    {
        $entries = PartyEntry::query()
            ->active()
            ->where('party_id', $party->id)
            ->get(['id', 'price_paid', 'grace_applied', 'override_reason', 'entered_at', 'participant_id']);

        if ($entries->isEmpty()) {
            return null;
        }

        $count = $entries->count();
        $free = $entries->filter(fn ($e) => $e->isFree())->count();

        $hours = [];
        foreach ($entries as $e) {
            $h = (int) $e->entered_at->format('G');
            $hours[$h] ??= ['count' => 0, 'revenue' => 0.0];
            $hours[$h]['count']++;
            $hours[$h]['revenue'] = round($hours[$h]['revenue'] + (float) $e->price_paid, 2);
        }
        ksort($hours);

        $payments = [];
        $rows = PartyEntryPayment::query()
            ->join('party_entries', 'party_entries.id', '=', 'party_entry_payments.party_entry_id')
            ->where('party_entries.party_id', $party->id)
            ->whereNull('party_entries.cancelled_at')
            ->groupBy('party_entry_payments.method')
            ->selectRaw('party_entry_payments.method as method, SUM(party_entry_payments.amount) as amount')
            ->get();
        foreach ($rows as $row) {
            $payments[$row->method] = round((float) $row->amount, 2);
        }
        // Ordinea metodelor din Setari (cash, card, ...; custom la urma), nu cea a bazei de date.
        $order = array_flip(PaymentMethods::all()->pluck('key')->all());
        uksort($payments, fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return (object) [
            'count' => $count,
            'paid' => $count - $free,
            'free' => $free,
            'free_pct' => round($free / $count * 100, 1),
            'grace' => $entries->filter(fn ($e) => $e->grace_applied && $e->override_reason === null)->count(),
            'overridden' => $entries->filter(fn ($e) => $e->isOverridden())->count(),
            'identified' => $entries->whereNotNull('participant_id')->count(),
            'unique_participants' => $entries->whereNotNull('participant_id')->pluck('participant_id')->unique()->count(),
            'revenue' => round((float) $entries->sum('price_paid'), 2),
            'payments' => $payments,
            'hours' => $hours,
        ];
    }

    /**
     * Casa de recepție (închiderea casei): raportările de recepție FINALIZATE ale petrecerii, adunate (ca la bar, unde
     * contează doar raportările finalizate). Valorile vin din cele ÎNGHEȚATE la finalizare. Diferența de casă e suma
     * diferențelor; `methods` = totalurile așteptate pe metodă (fără cash, care e numărat), cu eticheta de la finalizare.
     * Blocul de intrări (attendance) rămâne pe TOATE intrările neanulate, indiferent de raportări. null dacă nu există nicio raportare.
     */
    public static function reception(Party $party): ?object
    {
        $reports = ReceptionReport::query()->where('party_id', $party->id)->where('status', 'finalized')
            ->orderBy('finalized_at')->orderBy('id')->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $methods = [];
        foreach ($reports as $r) {
            foreach ($r->figures()->other_methods as $m) {
                $methods[$m['key']] ??= ['key' => $m['key'], 'label' => $m['label'], 'amount' => 0.0];
                $methods[$m['key']]['amount'] = round($methods[$m['key']]['amount'] + (float) $m['amount'], 2);
            }
        }
        // Ordinea metodelor din Setări.
        $order = array_flip(PaymentMethods::all()->pluck('key')->all());
        uasort($methods, fn ($a, $b) => ($order[$a['key']] ?? PHP_INT_MAX) <=> ($order[$b['key']] ?? PHP_INT_MAX));

        $counted = $reports->filter(fn ($r) => $r->counted_cash !== null);

        return (object) [
            'reports' => $reports->count(),
            'expected_cash' => round((float) $reports->sum('expected_cash'), 2),
            'counted_cash' => round((float) $counted->sum('counted_cash'), 2),
            'cash_diff' => round((float) $reports->sum('cash_diff'), 2),
            'cash_entries' => round((float) $reports->sum('cash_entries'), 2),
            'cash_tokens' => round((float) $reports->sum('cash_tokens'), 2),
            'handed_over' => round((float) $reports->sum('handed_over'), 2),
            'clean' => $reports->every(fn ($r) => abs((float) $r->cash_diff) < 0.005),
            'methods' => array_values($methods),
        ];
    }

    /**
     * Participantii petrecerii: pentru fiecare, intrari, cheltuit la bar si tokeni cumparati (definitiile: ParticipantStats).
     * Cheltuiala la bar se numara din vanzarile raportarilor FINALIZATE (ca restul statisticilor de bar); intrarile si
     * tokenii, imediat. null daca niciun participant nu are activitate. `bar_identified_pct` = ce parte din vanzarile din
     * aplicatie (raportari finalizate) au un participant.
     *
     * @param  array<int, int>  $reportIds
     */
    public static function participants(Party $party, array $reportIds): ?object
    {
        $entries = PartyEntry::query()->active()->where('party_id', $party->id)->whereNotNull('participant_id')
            ->groupBy('participant_id')
            ->selectRaw('participant_id, COUNT(*) as n, SUM(price_paid) as amount')
            ->get()->keyBy('participant_id');

        $tokens = TokenTransaction::query()->active()->where('type', TokenTransaction::SOLD)
            ->where('party_id', $party->id)->whereNotNull('participant_id')
            ->groupBy('participant_id')
            ->selectRaw('participant_id, SUM(tokens) as t, SUM(amount) as amount')
            ->get()->keyBy('participant_id');

        $bar = collect();
        $identifiedTotal = 0.0;
        $appTotal = 0.0;
        if ($reportIds !== []) {
            $bar = SalePayment::query()
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->whereIn('sales.report_id', $reportIds)
                ->whereNotNull('sales.customer_id')
                ->where('sales.status', 'completed')
                ->where('sale_payments.method', '!=', 'benefit')
                ->groupBy('sales.customer_id')
                ->selectRaw('sales.customer_id as participant_id, SUM(sale_payments.amount) as spent, COUNT(DISTINCT sales.id) as sales')
                ->get()->keyBy('participant_id');

            $appTotal = (float) Sale::query()->whereIn('report_id', $reportIds)->where('status', 'completed')->sum('total');
            $identifiedTotal = (float) Sale::query()->whereIn('report_id', $reportIds)->where('status', 'completed')->whereNotNull('customer_id')->sum('total');
        }

        $ids = $entries->keys()->merge($tokens->keys())->merge($bar->keys())->unique()->values();
        if ($ids->isEmpty()) {
            return null;
        }

        $names = Participant::query()->whereIn('id', $ids->all())->pluck('name', 'id');

        $rows = $ids->map(fn ($id) => (object) [
            'participant_id' => (int) $id,
            'name' => $names[$id] ?? 'Participant șters',
            'entries' => (int) ($entries->get($id)->n ?? 0),
            'entries_amount' => round((float) ($entries->get($id)->amount ?? 0), 2),
            'bar_spent' => round((float) ($bar->get($id)->spent ?? 0), 2),
            'bar_sales' => (int) ($bar->get($id)->sales ?? 0),
            'tokens' => (int) ($tokens->get($id)->t ?? 0),
            'tokens_amount' => round((float) ($tokens->get($id)->amount ?? 0), 2),
        ])->values();

        return (object) [
            'rows' => $rows,
            'bar_identified' => round($identifiedTotal, 2),
            'bar_identified_pct' => $appTotal > 0 ? round($identifiedTotal / $appTotal * 100, 1) : null,
        ];
    }

    /**
     * Axa orelor de ceas pentru curba: cel mai scurt interval circular care contine toate orele cu vanzari
     * (din una sau doua petreceri), deci o petrecere 21:00–03:00 se citeste corect peste miezul noptii.
     *
     * @param  array<int, array>  ...$hourMaps  cate un array [ora => [...]] per petrecere
     * @return int[] orele in ordine cronologica (0-23)
     */
    public static function hourAxis(array ...$hourMaps): array
    {
        $hours = [];
        foreach ($hourMaps as $map) {
            foreach (array_keys($map) as $h) {
                $hours[(int) $h] = true;
            }
        }
        $hours = array_keys($hours);
        if ($hours === []) {
            return [];
        }
        sort($hours);

        $bestStart = $hours[0];
        $bestSpan = PHP_INT_MAX;
        foreach ($hours as $start) {
            $span = 0;
            foreach ($hours as $h) {
                $span = max($span, ($h - $start + 24) % 24);
            }
            if ($span < $bestSpan) {
                $bestSpan = $span;
                $bestStart = $start;
            }
        }

        return array_map(fn ($i) => ($bestStart + $i) % 24, range(0, $bestSpan));
    }
}
