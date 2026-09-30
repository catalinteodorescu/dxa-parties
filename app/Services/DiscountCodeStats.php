<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\Promoter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (Coduri de reducere - statistici). Cât au adus codurile și promotorii, din intrările VALABILE cu cod
 * (anularea scoate intrarea din cifre). Nu depinde de raportări: intrările se numără imediat.
 *
 * Definiții:
 *  - bilete = intrări valabile cu cod (o reducere acordată = un bilet);
 *  - participanți = participanți identificați distincți din acele intrări (intrările anonime se numără ca bilete, nu ca persoane);
 *  - noi = participanți identificați care nu aveau nicio intrare valabilă la o petrecere anterioară (începută mai devreme);
 *    reveniți = restul. „Noi" înseamnă aduși ca persoane noi în comunitate, nu doar la prima petrecere cu cod;
 *  - reducere = suma discount_amount; încasat = suma price_paid a biletelor cu cod;
 *  - promotor = promotorul codului (fără promotor: grupul „Fără promotor").
 */
class DiscountCodeStats
{
    /**
     * Statistici pe o petrecere: totaluri, pe cod, pe promotor, pe zile.
     *
     * @return object{has_data: bool, totals: object, codes: Collection, promoters: Collection, daily: Collection}
     */
    public static function forParty(Party $party): object
    {
        $codes = PartyDiscountCode::query()->with('promoter')->where('party_id', $party->id)->orderBy('id')->get();
        $entries = self::codedEntries(fn ($q) => $q->where('party_id', $party->id));
        $new = self::newParticipants($entries);

        $partyEntries = (int) PartyEntry::query()->where('party_id', $party->id)->active()->count();

        $rows = $codes->map(fn (PartyDiscountCode $c) => self::row(
            $entries->where('discount_code_id', $c->id), $new,
            ['code' => $c->code, 'promoter_id' => $c->promoter_id, 'promoter' => $c->promoter?->name, 'type' => $c->type,
                'is_active' => $c->is_active, 'max_uses' => $c->max_uses, 'code_id' => $c->id]
        ))->sortByDesc('tickets')->values();

        $promoters = self::groupByPromoter($entries, $new, $codes->keyBy('id'));

        $daily = $entries->groupBy(fn ($e) => $e->entered_at->format('Y-m-d'))->map(fn ($g, $day) => (object) [
            'day' => $day,
            'tickets' => $g->count(),
            'discount' => round((float) $g->sum('discount_amount'), 2),
        ])->sortKeys()->values();

        $totals = self::row($entries, $new, []);
        $totals->share_pct = $partyEntries > 0 ? round($totals->tickets / $partyEntries * 100, 1) : null;
        $totals->party_entries = $partyEntries;

        return (object) [
            'has_data' => $codes->isNotEmpty() || $entries->isNotEmpty(),
            'totals' => $totals,
            'codes' => $rows,
            'promoters' => $promoters,
            'daily' => $daily,
        ];
    }

    /**
     * Clasament între petreceri: promotori și cele mai folosite coduri. `$partyIds` null = toate petrecerile.
     *
     * @param  array<int, int>|null  $partyIds
     * @return object{promoters: Collection, codes: Collection, parties: int, totals: object}
     */
    public static function overall(?array $partyIds = null): object
    {
        $entries = self::codedEntries(fn ($q) => $partyIds === null ? $q : $q->whereIn('party_id', $partyIds));
        $new = self::newParticipants($entries);
        $codes = PartyDiscountCode::query()->with(['promoter', 'party'])->whereIn('id', $entries->pluck('discount_code_id')->unique())->get()->keyBy('id');

        $promoters = self::groupByPromoter($entries, $new, $codes);

        $topCodes = $entries->groupBy('discount_code_id')->map(function ($g, $id) use ($codes, $new) {
            $c = $codes->get($id);

            return self::row($g, $new, [
                'code' => $c?->code ?? '#'.$id,
                'promoter' => $c?->promoter?->name,
                'promoter_id' => $c?->promoter_id,
                'party' => $c?->party?->name,
                'party_id' => $c?->party_id,
            ]);
        })->sortByDesc('tickets')->values();

        return (object) [
            'promoters' => $promoters,
            'codes' => $topCodes,
            'parties' => $entries->pluck('party_id')->unique()->count(),
            'totals' => self::row($entries, $new, []),
        ];
    }

    /** Intrările valabile cu cod (minimul de coloane). @return Collection<int, PartyEntry> */
    private static function codedEntries(\Closure $scope): Collection
    {
        return $scope(PartyEntry::query()->active()->whereNotNull('discount_code_id'))
            ->get(['id', 'party_id', 'discount_code_id', 'participant_id', 'price_paid', 'discount_amount', 'entered_at']);
    }

    /**
     * Perechi „party_id:participant_id" ale participanților NOI: nicio intrare valabilă la o petrecere începută mai devreme.
     *
     * @return array<string, true>
     */
    private static function newParticipants(Collection $entries): array
    {
        $ids = $entries->pluck('participant_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $starts = DB::table('parties')->whereIn('id', $entries->pluck('party_id')->unique())->pluck('starts_at', 'id'); // texte 'Y-m-d H:i:s' (comparabile)

        $history = PartyEntry::query()->active()->whereIn('participant_id', $ids)
            ->join('parties', 'parties.id', '=', 'party_entries.party_id')
            ->get(['party_entries.participant_id', 'party_entries.party_id', 'parties.starts_at as p_starts'])
            ->groupBy('participant_id');

        $new = [];
        foreach ($entries->filter(fn ($e) => $e->participant_id)->unique(fn ($e) => $e->party_id.':'.$e->participant_id) as $e) {
            $start = $starts->get($e->party_id);
            $earlier = $history->get($e->participant_id, collect())->contains(
                fn ($h) => (int) $h->party_id !== (int) $e->party_id && $h->p_starts !== null && $start !== null && $h->p_starts < $start
            );
            if (! $earlier) {
                $new[$e->party_id.':'.$e->participant_id] = true;
            }
        }

        return $new;
    }

    /** Un rând de cifre pentru un grup de intrări. */
    private static function row(Collection $g, array $new, array $extra): object
    {
        $identified = $g->filter(fn ($e) => $e->participant_id);
        $people = $identified->unique(fn ($e) => $e->party_id.':'.$e->participant_id);
        $newCount = $people->filter(fn ($e) => isset($new[$e->party_id.':'.$e->participant_id]))->count();

        return (object) array_merge([
            'tickets' => $g->count(),
            'anonymous' => $g->count() - $identified->count(),
            'participants' => $identified->pluck('participant_id')->unique()->count(),
            'new' => $newCount,
            'returning' => $people->count() - $newCount,
            'discount' => round((float) $g->sum('discount_amount'), 2),
            'revenue' => round((float) $g->sum('price_paid'), 2),
            'parties' => $g->pluck('party_id')->unique()->count(),
        ], $extra);
    }

    /**
     * Rânduri pe promotor (coduri fără promotor => grupul „Fără promotor").
     *
     * @param  Collection<int, PartyDiscountCode>  $codes  keyBy id, cu promoter încărcat
     */
    private static function groupByPromoter(Collection $entries, array $new, Collection $codes): Collection
    {
        $names = Promoter::query()->pluck('name', 'id');

        return $entries->groupBy(fn ($e) => (string) ($codes->get($e->discount_code_id)?->promoter_id ?? ''))
            ->map(function ($g, $pid) use ($new, $names, $codes) {
                $codeIds = $g->pluck('discount_code_id')->unique();

                return self::row($g, $new, [
                    'promoter_id' => $pid === '' ? null : (int) $pid,
                    'promoter' => $pid === '' ? 'Fără promotor' : ($names[(int) $pid] ?? '#'.$pid),
                    'codes' => $codeIds->map(fn ($id) => $codes->get($id)?->code)->filter()->values()->all(),
                ]);
            })->sortByDesc(fn ($r) => $r->participants * 1000000 + $r->tickets)->values();
    }
}
