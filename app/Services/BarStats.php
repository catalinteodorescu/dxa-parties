<?php

namespace App\Services;

use App\Models\Party;
use App\Models\StockReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (Bar - statistici agregate). Statistici peste TOATE petrecerile cu raportare
 * finalizata (nu doar una), pentru pagina "Bar > Statistici". Spre deosebire de PartyStats (o
 * petrecere), aici se aduna cifrele mai multor petreceri.
 *
 * Nu recalculeaza formulele de la zero: fiecare petrecere isi ia cifrele din PartyStats::for()
 * (aceeasi sursa de adevar ca pagina de detaliu per petrecere, deci numerele raman consistente
 * intre "Bar > Statistici" si "Petrecere > Statistici"), iar aici doar se aduna/sorteaza.
 *
 * "Perioada" (all-time vs. ultimele 3 luni) se aplica pe data petrecerii (start_date), la fel ca
 * "activi recent" din Participanti > Statistici — nu pe data raportarii/finalizarii.
 */
class BarStats
{
    /**
     * Toate petrecerile cu cel putin o raportare finalizata, cu statisticile lor complete
     * (PartyStats::for), in ordine cronologica. Baza pentru restul metodelor din clasa.
     */
    public static function partyRanking(): Collection
    {
        return Party::query()
            ->whereIn('id', StockReport::query()->where('status', 'finalized')->whereNotNull('party_id')->select('party_id'))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Party $p) => (object) ['party' => $p, 'stats' => PartyStats::for($p)])
            ->values();
    }

    /** @return Collection<int, object{party: Party, stats: object}> filtrata dupa data petrecerii, daca $since e dat. */
    private static function inPeriod(Collection $rows, ?Carbon $since): Collection
    {
        if (! $since) {
            return $rows;
        }

        return $rows->filter(fn ($r) => $r->party->start_date->gte($since))->values();
    }

    /**
     * Privire generala: venit/cost/profit/marja, bonuri, bon mediu, distributie pe metode de plata.
     * $since null = all-time; altfel doar petrecerile din perioada (dupa start_date).
     */
    public static function overview(?Carbon $since = null): object
    {
        $rows = self::inPeriod(self::partyRanking(), $since);

        $stats = (object) [
            'parties_count' => $rows->count(),
            'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'margin' => null,
            'tx_count' => 0, 'tx_total' => 0.0, 'avg_ticket' => null,
            'payments' => [], 'payments_total' => 0.0,
        ];

        foreach ($rows as $r) {
            $s = $r->stats;
            $stats->revenue = round($stats->revenue + $s->revenue, 2);
            $stats->cost = round($stats->cost + $s->cost, 2);
            $stats->tx_count += $s->tx_count;
            $stats->tx_total = round($stats->tx_total + $s->tx_total, 2);

            foreach ($s->payments as $method => $p) {
                $stats->payments[$method]['amount'] = round(($stats->payments[$method]['amount'] ?? 0) + $p['amount'], 2);
                $stats->payments[$method]['tokens'] = ($stats->payments[$method]['tokens'] ?? 0) + $p['tokens'];
            }
        }

        $stats->profit = round($stats->revenue - $stats->cost, 2);
        $stats->margin = $stats->revenue > 0 ? round($stats->profit / $stats->revenue * 100, 1) : null;
        $stats->avg_ticket = $stats->tx_count > 0 ? round($stats->tx_total / $stats->tx_count, 2) : null;
        $stats->payments_total = round(array_sum(array_column($stats->payments, 'amount')), 2);

        return $stats;
    }

    /**
     * Trend cronologic (pentru grafic): o linie per petrecere din perioada, cu venit/profit/marja.
     * Foloseste aceleasi randuri ca overview(), doar ca lista in loc de suma.
     */
    public static function trend(?Carbon $since = null): Collection
    {
        return self::inPeriod(self::partyRanking(), $since)
            ->map(fn ($r) => (object) [
                'party' => $r->party,
                'revenue' => $r->stats->revenue,
                'profit' => $r->stats->profit,
                'margin' => $r->stats->margin,
            ])
            ->values();
    }

    /**
     * Clasamentul petrecerilor (all-time, nu urmeaza toggle-ul de perioada — e gandit ca istoric),
     * top 10 dupa criteriul ales: 'revenue' (implicit), 'margin' sau 'per_participant'.
     */
    public static function topParties(string $metric = 'revenue'): Collection
    {
        $key = match ($metric) {
            'margin' => fn ($r) => $r->stats->margin ?? -INF,
            'per_participant' => fn ($r) => $r->stats->bar_per_participant ?? -INF,
            default => fn ($r) => $r->stats->revenue,
        };

        return self::partyRanking()->sortByDesc($key)->take(10)->values();
    }

    /**
     * Produsele cele mai vandute, all-time (nu urmeaza toggle-ul, cerut explicit in design): top 8
     * cantitate, top 8 venit, top 8 profit (profitul ignora produsele cu cost necunoscut undeva).
     */
    public static function products(): object
    {
        $merged = [];
        foreach (self::partyRanking() as $r) {
            foreach ($r->stats->products as $p) {
                $merged[$p->menu_item_id] ??= (object) [
                    'menu_item_id' => $p->menu_item_id, 'name' => $p->name,
                    'qty' => 0.0, 'revenue' => 0.0, 'profit' => 0.0, 'profit_unknown' => false,
                ];
                $row = $merged[$p->menu_item_id];
                $row->qty += $p->qty;
                $row->revenue = round($row->revenue + $p->revenue, 2);
                if ($p->profit === null) {
                    $row->profit_unknown = true;
                } else {
                    $row->profit = round($row->profit + $p->profit, 2);
                }
            }
        }

        $all = collect($merged)->values();

        return (object) [
            'top_qty' => $all->sortByDesc('qty')->take(8)->values(),
            'top_revenue' => $all->sortByDesc('revenue')->take(8)->values(),
            'top_profit' => $all->reject(fn ($p) => $p->profit_unknown)->sortByDesc('profit')->take(8)->values(),
        ];
    }

    /** Staff-ul care a inregistrat cele mai multe vanzari/valoare, pe perioada aleasa. */
    public static function staff(?Carbon $since = null): Collection
    {
        $merged = [];
        foreach (self::inPeriod(self::partyRanking(), $since) as $r) {
            foreach ($r->stats->staff as $s) {
                $merged[$s->admin_id] ??= (object) ['admin_id' => $s->admin_id, 'name' => $s->name, 'count' => 0, 'total' => 0.0];
                $merged[$s->admin_id]->count += $s->count;
                $merged[$s->admin_id]->total = round($merged[$s->admin_id]->total + $s->total, 2);
            }
        }

        return collect($merged)->sortByDesc('total')->values();
    }
}
