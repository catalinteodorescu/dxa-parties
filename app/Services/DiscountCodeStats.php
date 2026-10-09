<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\Promoter;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (Coduri de reducere - statistici). Cât au adus codurile și promotorii, din BILETELE ONLINE cu cod
 * (biletele anulate nu se numără). Codurile se folosesc doar la biletele online; la Recepție nu se folosesc coduri
 * (câmpul de acolo e doar de test), deci intrările nu intră în aceste cifre.
 *
 * Definiții:
 *  - bilete = bilete online neanulate cu cod (o reducere acordată = un bilet);
 *  - participanți = deținători cu nume distincți (biletele fără deținător cu nume se numără ca anonime);
 *  - noi = participanți care nu aveau nicio intrare/bilet online la o petrecere anterioară (începută mai devreme);
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

        $partyEntries = (int) Ticket::query()->where('party_id', $party->id)->issued()->count();

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
    public static function overall(?array $partyIds = null, ?Carbon $since = null): object
    {
        $entries = self::codedEntries(fn ($q) => $q
            ->when($partyIds !== null, fn ($q2) => $q2->whereIn('party_id', $partyIds))
            ->when($since !== null, fn ($q2) => $q2->where('created_at', '>=', $since)));
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

    /**
     * DXA: adaugat (dashboard). Cifrele pentru cardurile „Coduri de reducere”: ultimele 30 de zile (reduceri, noi/reveniți),
     * petrecerea curentă (în desfășurare, altfel următoarea) și primii promotori din total.
     *
     * @return object{days: int, period: object, period_entries: int, share_pct: ?float, party: ?Party, party_stats: ?object, top_code: ?object, promoters: Collection}
     */
    public static function dashboard(?Carbon $now = null, int $days = 30): object
    {
        $now ??= now();
        $since = $now->copy()->subDays($days);

        $period = self::overall(null, $since)->totals;
        $periodEntries = (int) Ticket::query()->issued()->where('created_at', '>=', $since)->count();

        // Petrecerea curentă: cea în desfășurare, altfel cea mai apropiată viitoare (publicată, activă, neîncheiată).
        $party = Party::query()
            ->where('status', '!=', 'draft')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('starts_at')->orderBy('id')->first();
        $partyStats = $party ? self::forParty($party) : null;

        $promoters = self::overall()->promoters->filter(fn ($r) => $r->promoter_id !== null)->take(3)->values();

        return (object) [
            'days' => $days,
            'period' => $period,
            'period_entries' => $periodEntries,
            'share_pct' => $periodEntries > 0 ? round($period->tickets / $periodEntries * 100, 1) : null,
            'party' => $party,
            'party_stats' => $partyStats,
            'top_code' => $partyStats?->codes->first(fn ($c) => $c->tickets > 0),
            'promoters' => $promoters,
        ];
    }

    /**
     * Biletele online neanulate cu cod, aduse la aceeași formă (participant_id, price_paid, discount_amount, entered_at).
     *
     * @return Collection<int, object>
     */
    private static function codedEntries(\Closure $scope): Collection
    {
        return $scope(Ticket::query()->issued()->whereNotNull('discount_code_id'))
            ->get(['id', 'party_id', 'discount_code_id', 'holder_participant_id', 'price', 'discount_amount', 'created_at'])
            ->map(fn (Ticket $t) => (object) [
                'id' => $t->id,
                'party_id' => $t->party_id,
                'discount_code_id' => $t->discount_code_id,
                'participant_id' => $t->holder_participant_id,
                'price_paid' => $t->price,
                'discount_amount' => $t->discount_amount,
                'entered_at' => $t->created_at,
            ]);
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

        $fromEntries = PartyEntry::query()->active()->whereIn('participant_id', $ids)
            ->join('parties', 'parties.id', '=', 'party_entries.party_id')
            ->get(['party_entries.participant_id', 'party_entries.party_id', 'parties.starts_at as p_starts']);
        $fromTickets = Ticket::query()->whereIn('tickets.status', [Ticket::VALID, Ticket::USED])->whereIn('tickets.holder_participant_id', $ids)
            ->join('parties', 'parties.id', '=', 'tickets.party_id')
            ->get(['tickets.holder_participant_id', 'tickets.party_id', 'parties.starts_at as p_starts'])
            ->map(fn ($t) => (object) ['participant_id' => $t->holder_participant_id, 'party_id' => $t->party_id, 'p_starts' => $t->p_starts]);
        $history = $fromEntries->concat($fromTickets)->groupBy('participant_id');

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
