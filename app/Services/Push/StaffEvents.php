<?php

namespace App\Services\Push;

use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\ReceptionSession;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Services\TicketOrders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (runda 77). Evenimentele care produc notificări push pentru telefoanele din Recepție și Bar (vezi StaffNotifier). Fiecare metodă
 * e idempotentă (cheie unică per cont și eveniment), deci poate rula de câte ori vrea cron-ul. Cele „la fața locului” (produs epuizat, petrecere
 * epuizată) pleacă imediat, restul rulează din `runScheduled()` (la 15 minute, prin `app:push-run`).
 */
class StaffEvents
{
    /** „Deschide casa / barul”: cu cel mult atât înainte de început (și până cu 30 de minute înainte). */
    public const OPEN_HOURS = 2;

    /** „Casă nedeschisă”: în ultimele atâtea minute dinaintea începutului (și „Deschide casa” nu mai pleacă după ele). */
    public const NOT_OPEN_MINUTES = 30;

    /** „Final de petrecere”: la atâtea minute după sfârșit, și numai în următoarele atâtea ore (nu a doua zi). */
    public const END_AFTER_MINUTES = 30;

    public const END_WINDOW_HOURS = 3;

    /** Sesiunile deschise de cel puțin atâtea ore sunt „fără raportare” dimineața. */
    public const MISSING_AFTER_HOURS = 6;

    /** @return array<string,int> câte notificări s-au creat, pe tip */
    public static function runScheduled(?Carbon $now = null): array
    {
        if (! StaffNotifier::enabled()) {
            return [];
        }
        $now ??= now();

        return [
            'open' => self::openApps($now),
            'not_open' => self::notOpen($now),
            'party_end' => self::partyEnd($now),
            'report_missing' => self::reportMissing($now),
            'session_stale' => self::sessionStale(),
            'low_stock' => self::lowStock(),
        ];
    }

    /** Petrecerile vizibile care încep în intervalul dat. @return Collection<int, Party> */
    private static function startingBetween(Carbon $from, Carbon $to): Collection
    {
        return Party::query()->visible('auth')->whereBetween('starts_at', [$from, $to])->get();
    }

    private static function hasSession(string $app, Party $party): bool
    {
        return $app === 'reception'
            ? ReceptionSession::query()->where('party_id', $party->id)->exists()
            : SalesGroup::query()->where('party_id', $party->id)->exists();
    }

    /** „Deschide casa” / „Deschide barul”: petrecerile care încep în următoarele 2 ore și n-au încă sesiune, către conturile cu acces. */
    public static function openApps(Carbon $now): int
    {
        $count = 0;
        foreach (self::startingBetween($now->copy()->addMinutes(self::NOT_OPEN_MINUTES), $now->copy()->addHours(self::OPEN_HOURS)) as $party) {
            $at = $party->starts_at->format('H:i');
            if (! self::hasSession('reception', $party)) {
                $count += StaffNotifier::notifyMany(StaffNotifier::workers('reception'), 'r_open', 'open:reception:'.$party->id,
                    'Deschide casa', $party->name.' începe la '.$at.'. Pornește sesiunea de recepție.', '/receptie', 'reception');
            }
            if (! self::hasSession('bar', $party)) {
                $count += StaffNotifier::notifyMany(StaffNotifier::workers('bar'), 'b_open', 'open:bar:'.$party->id,
                    'Deschide barul', $party->name.' începe la '.$at.'. Pregătește barul.', '/bar', 'bar');
            }
        }

        return $count;
    }

    /** „Casă / bar nedeschis”: în ultimele 30 de minute dinaintea începutului (și 5 după), fără nicio sesiune. */
    public static function notOpen(Carbon $now): int
    {
        $count = 0;
        foreach (self::startingBetween($now->copy()->subMinutes(5), $now->copy()->addMinutes(self::NOT_OPEN_MINUTES)) as $party) {
            $at = $party->starts_at->format('H:i');
            if (! self::hasSession('reception', $party)) {
                $count += StaffNotifier::notifyMany(StaffNotifier::workers('reception'), 'r_not_open', 'notopen:reception:'.$party->id,
                    'Casa nu e deschisă', $party->name.' începe la '.$at.' și nu e nicio sesiune de recepție.', '/receptie', 'reception');
            }
            if (! self::hasSession('bar', $party)) {
                $count += StaffNotifier::notifyMany(StaffNotifier::workers('bar'), 'b_not_open', 'notopen:bar:'.$party->id,
                    'Barul nu e deschis', $party->name.' începe la '.$at.' și nu e nicio sesiune de bar.', '/bar', 'bar');
            }
        }

        return $count;
    }

    /**
     * „Final de petrecere”: la 30 de minute după ora de sfârșit (și cel mult 3 ore după), către cel care a deschis sesiunea, dacă ea e încă
     * deschisă și raportarea nu e depusă.
     */
    public static function partyEnd(Carbon $now): int
    {
        $count = 0;
        $from = $now->copy()->subHours(self::END_WINDOW_HOURS);
        $to = $now->copy()->subMinutes(self::END_AFTER_MINUTES);

        foreach (ReceptionSession::query()->open()->whereHas('party', fn ($q) => $q->whereBetween('ends_at', [$from, $to]))->with(['report', 'party'])->get()->reject(fn ($s) => $s->reportSubmitted()) as $s) {
            $count += StaffNotifier::notifyMany(self::owners('reception', $s->opened_by), 'r_party_end', 'end:reception:'.$s->id,
                'Petrecerea s-a terminat', ($s->party?->name ?? 'Petrecerea').': numără casa și depune raportarea.', '/receptie/raportare', 'reception');
        }
        foreach (SalesGroup::query()->open()->whereHas('party', fn ($q) => $q->whereBetween('ends_at', [$from, $to]))->with(['barReport', 'party'])->get()->reject(fn ($g) => $g->reportSubmitted()) as $g) {
            $count += StaffNotifier::notifyMany(self::owners('bar', $g->bartender_id ?? $g->created_by), 'b_party_end', 'end:bar:'.$g->id,
                'Petrecerea s-a terminat', ($g->party?->name ?? 'Petrecerea').': numără casa și depune raportarea.', '/bar/raportare', 'bar');
        }

        return $count;
    }

    /**
     * Dimineața (09:00–12:00): sesiunile deschise de cel puțin 6 ore, fără raportare depusă, către cel care le-a deschis (sau, fără
     * el, către toate conturile cu acces la aplicație). Cheia e comună cu „sesiune uitată”: pentru o sesiune pleacă una singură.
     */
    public static function reportMissing(Carbon $now): int
    {
        if ($now->hour < 9 || $now->hour >= 12) {
            return 0;
        }
        $cutoff = $now->copy()->subHours(self::MISSING_AFTER_HOURS);
        $count = 0;

        foreach (ReceptionSession::query()->open()->where('created_at', '<=', $cutoff)->with(['report', 'party'])->get()->reject(fn ($s) => $s->reportSubmitted()) as $s) {
            $count += StaffNotifier::notifyMany(self::owners('reception', $s->opened_by), 'r_report_missing', 'sess:reception:'.$s->id,
                'Raportare nedepusă', ($s->party?->name ?? 'Petrecerea').': sesiunea de recepție e încă deschisă. Depune raportarea.', '/receptie/raportare', 'reception');
        }
        foreach (SalesGroup::query()->open()->where('created_at', '<=', $cutoff)->with(['barReport', 'party'])->get()->reject(fn ($g) => $g->reportSubmitted()) as $g) {
            $count += StaffNotifier::notifyMany(self::owners('bar', $g->bartender_id ?? $g->created_by), 'b_report_missing', 'sess:bar:'.$g->id,
                'Raportare nedepusă', ($g->party?->name ?? 'Petrecerea').': sesiunea de bar e încă deschisă. Depune raportarea.', '/bar/raportare', 'bar');
        }

        return $count;
    }

    /** Sesiunile uitate deschise (peste 12 ore, aceeași regulă ca alerta din dashboard), către cel care le-a deschis. */
    public static function sessionStale(): int
    {
        $count = 0;
        foreach (ReceptionSession::staleOpen() as $s) {
            $count += StaffNotifier::notifyMany(self::owners('reception', $s->opened_by), 'r_session_stale', 'sess:reception:'.$s->id,
                'Sesiune uitată deschisă', ($s->party?->name ?? 'Petrecerea').': deschisă de peste '.ReceptionSession::STALE_HOURS.' ore. Depune raportarea.', '/receptie/raportare', 'reception');
        }
        foreach (SalesGroup::staleOpen() as $g) {
            $count += StaffNotifier::notifyMany(self::owners('bar', $g->bartender_id ?? $g->created_by), 'b_session_stale', 'sess:bar:'.$g->id,
                'Sesiune uitată deschisă', ($g->party?->name ?? 'Petrecerea').': deschisă de peste '.SalesGroup::STALE_HOURS.' ore. Depune raportarea.', '/bar/raportare', 'bar');
        }

        return $count;
    }

    /** Cel care a deschis sesiunea, dacă are acces la aplicație; altfel toate conturile cu acces. @return Collection<int, Admin> */
    private static function owners(string $app, ?int $openedBy): Collection
    {
        $workers = StaffNotifier::workers($app);
        $owner = $openedBy ? $workers->firstWhere('id', $openedBy) : null;

        return $owner ? collect([$owner]) : $workers;
    }

    /** Un barman a marcat un produs ca epuizat (apelat din Bar): colegii cu acces la Bar, nu și cel care l-a marcat. */
    public static function soldOut(MenuItem $item, Party $party, string $workerName, ?int $workerId = null): void
    {
        Events::safely(function () use ($item, $party, $workerName, $workerId) {
            StaffNotifier::notifyMany(StaffNotifier::workers('bar')->reject(fn (Admin $a) => $workerId !== null && $a->id === $workerId), 'b_sold_out',
                'soldout:'.$party->id.':'.$item->id.':'.now()->format('YmdH'),
                'Produs epuizat', $item->name.' a fost marcat epuizat la '.$party->name.' de '.$workerName.'.', '/bar/vanzare', 'bar');
        });
    }

    /** După o vânzare de bilete (apelat din Events::afterSale, fără ore liniștite): petrecerea a rămas fără bilete. */
    public static function partySeats(Party $party): void
    {
        Events::safely(function () use ($party) {
            $party->refresh();
            $total = (int) $party->tickets_for_sale;
            if (! StaffNotifier::enabled() || ! $party->online_sales || $total <= 0 || TicketOrders::soldTotal($party) < $total) {
                return;
            }
            StaffNotifier::notifyMany(StaffNotifier::workers('reception'), 'r_sold_out', 'soldout:'.$party->id, 'Petrecere epuizată', $party->name.': toate cele '.$total.' bilete sunt vândute.', '/receptie', 'reception');
        });
    }

    /**
     * Produsele de bar la sau sub pragul minim: o notificare pe produs, o singură dată pe „episod” (urma rămâne în `push_events` până
     * stocul se reface). Se verifică la 15 minute. Un tip oprit marchează episodul fără să trimită (nu se anunță mai târziu).
     */
    public static function lowStock(): int
    {
        $items = StockItem::query()->active()->whereNotNull('min_stock')->get();
        $low = $items->filter(fn (StockItem $i) => (float) $i->stock_qty <= (float) $i->min_stock);

        DB::table('push_events')->whereIn('key', $items->diff($low)->map(fn ($i) => 'staff_low:'.$i->id)->all())->delete();

        $count = 0;
        foreach ($low as $item) {
            $key = 'staff_low:'.$item->id;
            if (DB::table('push_events')->where('key', $key)->exists()) {
                continue;
            }
            $eventId = DB::table('push_events')->insertGetId(['key' => $key, 'created_at' => now()]);
            $qty = rtrim(rtrim(number_format((float) $item->stock_qty, 3, ',', '.'), '0'), ',');
            $min = rtrim(rtrim(number_format((float) $item->min_stock, 3, ',', '.'), '0'), ',');
            $count += StaffNotifier::notifyMany(StaffNotifier::workers('bar'), 'b_low_stock', 'low:'.$item->id.':'.$eventId,
                'Stoc sub minim', $item->name.': '.($qty === '' ? '0' : $qty).' '.$item->unit.' (minim '.$min.').', '/bar', 'bar');
        }

        return $count;
    }
}
