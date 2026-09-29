<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\PartyEntryPayment;
use App\Models\ReceptionReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (Receptie - statistici agregate). Statistici peste TOATE petrecerile, pentru pagina
 * "Receptie > Statistici". Ca la BarStats: nu recalculeaza formulele, ci reutilizeaza
 * PartyStats::attendance()/reception() si TokenLedger::partyTotals() (aceeasi sursa de adevar ca
 * pagina de detaliu per petrecere), doar le aduna/le pune cronologic.
 *
 * "Perioada" (all-time vs. ultimele 3 luni) se aplica pe data petrecerii (start_date), la fel ca in BarStats.
 */
class ReceptionStats
{
    /**
     * Metodele de plată folosite la intrări (all-time, fără intrările anulate), cele mai folosite primele.
     * Numărul = de câte ori a apărut metoda într-o plată; suma = încasat pe ea. Din ea vin și butoanele „Tot cu…” din recepție.
     *
     * @return Collection<int, object{method: string, count: int, amount: float}>
     */
    public static function entryMethodUsage(): Collection
    {
        return PartyEntryPayment::query()
            ->whereHas('entry', fn ($q) => $q->whereNull('cancelled_at'))
            ->selectRaw('method, COUNT(*) as uses, SUM(amount) as total')
            ->groupBy('method')
            ->orderByDesc('uses')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) ['method' => $r->method, 'count' => (int) $r->uses, 'amount' => round((float) $r->total, 2)]);
    }

    /** @return Collection<int, object{party: Party, attendance: object}> cronologic, doar petrecerile cu intrari. */
    public static function entriesTimeline(): Collection
    {
        return Party::query()
            ->whereIn('id', PartyEntry::query()->active()->select('party_id')->distinct())
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Party $p) => (object) ['party' => $p, 'attendance' => PartyStats::attendance($p)])
            ->filter(fn ($r) => $r->attendance !== null)
            ->values();
    }

    /** @return Collection<int, object{party: Party, totals: object}> cronologic, doar petrecerile cu tokeni (vanduti sau incasati). */
    public static function tokensTimeline(?Carbon $since = null): Collection
    {
        return Party::query()
            ->when($since, fn ($q) => $q->where('start_date', '>=', $since))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Party $p) => (object) ['party' => $p, 'totals' => TokenLedger::partyTotals($p)])
            ->filter(fn ($r) => $r->totals !== null)
            ->values();
    }

    /** Tokeni vanduti (receptie) vs. incasati (bar), totalizati pe perioada aleasa. */
    public static function tokensOverview(?Carbon $since = null): object
    {
        $rows = self::tokensTimeline($since);

        return (object) [
            'sold' => (int) $rows->sum(fn ($r) => $r->totals->sold),
            'collected' => (int) $rows->sum(fn ($r) => $r->totals->collected),
        ];
    }

    /**
     * Casa de receptie, cronologic: o linie per petrecere cu raportare de receptie finalizata,
     * cu diferenta ei de casa (suma raportarilor petrecerii, ca in PartyStats::reception()).
     * All-time — nu urmeaza toggle-ul de perioada (e gandit ca istoric, de unde se vede un tipar).
     *
     * @return Collection<int, object{party: Party, reception: object}>
     */
    public static function cashDiffTimeline(): Collection
    {
        return Party::query()
            ->whereIn('id', ReceptionReport::query()->where('status', 'finalized')->whereNotNull('party_id')->select('party_id'))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Party $p) => (object) ['party' => $p, 'reception' => PartyStats::reception($p)])
            ->filter(fn ($r) => $r->reception !== null)
            ->values();
    }
}
