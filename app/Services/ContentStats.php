<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Party;
use App\Models\PartyInterest;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (runda 40). Contoare reale pentru petreceri și anunțuri, agregate pe zile (`content_stats`).
 *
 *  - Evenimente: `impression` (cardul a fost vizibil ≥ 50%, o dată pe sesiune - decide clientul), `open` (s-a deschis pagina, numărat pe
 *    server), `action` (click pe „Cumpără bilete” / pe butonul cu link al anunțului).
 *  - „Unici” = persoane distincte PE ZI, după o amprentă zilnică anonimă (HMAC din IP + browser + zi; nu se stochează IP-ul și nu e cookie).
 *    Aceeași persoană în zile diferite se numără de câte ori a venit; în aceeași zi, de la același IP + browser, o singură dată.
 *  - Nu se numără: boții (fără User-Agent sau cu cuvinte tipice), adminii conectați, elementele inexistente sau nepublicate.
 *  - Se păstrează doar contoarele zilnice; amprentele (`content_visitors`) se șterg după 2 zile.
 */
class ContentStats
{
    public const TYPES = ['party', 'announcement'];

    public const EVENTS = ['impression', 'open', 'action'];

    public const MAX_BATCH = 40;

    /** @var array<string, array<string, int>> cache pe cerere */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function isBot(Request $request): bool
    {
        $ua = trim((string) $request->userAgent());

        return $ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|monitor|curl|wget|python|httpclient/i', $ua) === 1;
    }

    /** Numără un eveniment. Întoarce true dacă a fost numărat. */
    public static function record(string $type, int $id, string $event, Request $request): bool
    {
        return self::recordMany([[$type, $id, $event]], $request) > 0;
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: string}>  $items
     * @return int câte evenimente au fost numărate
     */
    public static function recordMany(array $items, Request $request): int
    {
        if (self::isBot($request) || Auth::guard('admin')->check()) {
            return 0;
        }

        $clean = [];
        foreach (array_slice($items, 0, self::MAX_BATCH) as $item) {
            [$type, $id, $event] = [(string) ($item[0] ?? ''), (int) ($item[1] ?? 0), (string) ($item[2] ?? '')];
            if ($id > 0 && in_array($type, self::TYPES, true) && in_array($event, self::EVENTS, true)) {
                $clean[$type.'|'.$id.'|'.$event] = [$type, $id, $event];
            }
        }
        if ($clean === []) {
            return 0;
        }

        // Doar elementele care există și sunt publicate.
        $valid = [];
        foreach (self::TYPES as $type) {
            $ids = collect($clean)->where(0, $type)->pluck(1)->unique()->values()->all();
            if ($ids !== []) {
                $model = $type === 'party' ? Party::class : Announcement::class;
                $valid[$type] = $model::query()->whereIn('id', $ids)->where('status', 'published')->pluck('id')->map(fn ($v) => (int) $v)->all();
            }
        }

        $day = now()->toDateString();
        $visitor = self::visitor($request, $day);
        $counted = 0;

        foreach ($clean as [$type, $id, $event]) {
            if (! in_array($id, $valid[$type] ?? [], true)) {
                continue;
            }

            $new = DB::table('content_visitors')->insertOrIgnore([
                'subject_type' => $type, 'subject_id' => $id, 'day' => $day, 'event' => $event, 'visitor' => $visitor,
            ]);

            $where = ['subject_type' => $type, 'subject_id' => $id, 'day' => $day, 'event' => $event];
            DB::table('content_stats')->insertOrIgnore($where + ['total' => 0, 'uniques' => 0]);
            DB::table('content_stats')->where($where)->update([
                'total' => DB::raw('total + 1'),
                'uniques' => DB::raw('uniques + '.($new > 0 ? 1 : 0)),
            ]);
            $counted++;
        }

        self::$cache = [];

        if (random_int(1, 50) === 1) {
            DB::table('content_visitors')->where('day', '<', now()->subDay()->toDateString())->delete();
        }

        return $counted;
    }

    /**
     * Totalurile unui element: impression / open / action (+ `_u` = unici pe zile).
     *
     * @return array{impression: int, open: int, action: int, impression_u: int, open_u: int, action_u: int}
     */
    public static function totals(string $type, int $id): array
    {
        $key = $type.'|'.$id;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $out = ['impression' => 0, 'open' => 0, 'action' => 0, 'impression_u' => 0, 'open_u' => 0, 'action_u' => 0];
        $rows = DB::table('content_stats')->where('subject_type', $type)->where('subject_id', $id)
            ->groupBy('event')->selectRaw('event, SUM(total) as t, SUM(uniques) as u')->get();
        foreach ($rows as $r) {
            $out[$r->event] = (int) $r->t;
            $out[$r->event.'_u'] = (int) $r->u;
        }

        return self::$cache[$key] = $out;
    }

    /**
     * Pâlnia unei petreceri pentru Statistici: afișări → deschideri → click „Cumpără” → interesați → cumpărători.
     *
     * @return array<string, int>
     */
    public static function partyFunnel(Party $party): array
    {
        $t = self::totals('party', $party->id);
        $interested = PartyInterests::count($party);

        $buyers = Ticket::query()->where('party_id', $party->id)->where('status', '!=', Ticket::VOID)
            ->whereIn('owner_participant_id', PartyInterest::query()->where('party_id', $party->id)->select('participant_id'))
            ->distinct()->count('owner_participant_id');

        return $t + ['interested' => $interested, 'interested_bought' => $buyers];
    }

    private static function visitor(Request $request, string $day): string
    {
        return substr(hash_hmac('sha256', $request->ip().'|'.$request->userAgent().'|'.$day, (string) config('app.key')), 0, 16);
    }
}
