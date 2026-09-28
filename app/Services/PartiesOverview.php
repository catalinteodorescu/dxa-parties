<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (Petreceri - statistici agregate). Statistici despre petreceri ca EVENIMENTE
 * (câte, ce tip, cât de des), plus un rezumat all-time al banilor/intrărilor și topuri — pentru
 * pagina "Petreceri > Statistici" (privirea de ansamblu, fără toggle de perioadă).
 *
 * Nu recalculeaza formulele de bani/intrari: rezumatul si topurile delega la BarStats/ReceptionStats
 * (care la randul lor iau cifrele din PartyStats::for()/attendance()) — aceeasi sursa de adevar ca
 * paginile Bar > Statistici / Recepție > Statistici / Petrecere > Statistici. Aici doar se adauga
 * ce e specific petrecerii ca eveniment: numar total, tip (Simplă/Festival), frecventa in timp.
 */
class PartiesOverview
{
    /** Câte petreceri (toate, indiferent de raportări), pe tip. */
    public static function totals(): object
    {
        $all = Party::query()->get(['id', 'kind']);

        return (object) [
            'count' => $all->count(),
            'basic' => $all->where('kind', 'basic')->count(),
            'festival' => $all->where('kind', 'festival')->count(),
        ];
    }

    /**
     * Frecventa: numarul de petreceri pe luna calendaristica (dupa start_date), cronologic.
     * Eticheta e numerica ('mm.YYYY'), ca restul datelor din aplicatie (fara nume de luni).
     */
    public static function frequency(): Collection
    {
        return Party::query()
            ->orderBy('start_date')
            ->get(['start_date'])
            ->groupBy(fn (Party $p) => $p->start_date->format('Y-m'))
            ->map(fn (Collection $rows, string $key) => (object) [
                'key' => $key,
                'label' => $rows->first()->start_date->format('m.Y'),
                'count' => $rows->count(),
            ])
            ->values();
    }

    /**
     * Rezumat all-time bani + intrari (delega la BarStats/ReceptionStats — nu recalculeaza).
     */
    public static function summary(): object
    {
        $bar = BarStats::overview();
        $entries = ReceptionStats::entriesTimeline();
        $entriesTotal = (int) $entries->sum(fn ($r) => $r->attendance->count);

        return (object) [
            'revenue' => $bar->revenue,
            'profit' => $bar->profit,
            'entries_total' => $entriesTotal,
            'entries_avg' => $entries->isNotEmpty() ? round($entriesTotal / $entries->count(), 1) : null,
            // DXA: adaugat (Dashboard - venit intrare). Ce s-a incasat la intrare (bilete), separat
            // de venitul barului — vine din PartyStats::attendance()->revenue (acelasi camp ca la
            // pagina Petrecere > Statistici), doar adunat pe toate petrecerile.
            'entries_revenue' => round((float) $entries->sum(fn ($r) => $r->attendance->revenue), 2),
        ];
    }

    /** Top petreceri dupa participare (intrari valabile), all-time — din ReceptionStats::entriesTimeline(). */
    public static function topByAttendance(int $limit = 5): Collection
    {
        return ReceptionStats::entriesTimeline()
            ->sortByDesc(fn ($r) => $r->attendance->count)
            ->take($limit)
            ->values();
    }
}
