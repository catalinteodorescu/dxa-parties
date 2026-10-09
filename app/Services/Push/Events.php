<?php

namespace App\Services\Push;

use App\Models\Announcement;
use App\Models\CreditTransaction;
use App\Models\LoyaltyCard;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\PartyInterest;
use App\Models\PushEvent;
use App\Models\Sale;
use App\Models\Ticket;
use App\Services\CreditLedger;
use App\Services\LoyaltyLedger;
use App\Services\TicketOrders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 70). Evenimentele care produc notificări push. Fiecare metodă e idempotentă (cheie unică per participant sau
 * per eveniment), deci poate rula de câte ori vrea cron-ul. Tipurile fără urgență (petrecere nouă, preț, puține locuri) așteaptă
 * în orele liniștite (22:00–09:00); „bilet anulat”, „petrecerea e mâine”, „cu 2 ore înainte” și anunțurile pleacă oricând.
 */
class Events
{
    /** Procentul de locuri rămase de la care se trimite „puține locuri”. */
    public const LOW_SEATS_PERCENT = 10;

    /** „Petrecerea e mâine”: ora (din ziua dinainte) de la care pleacă. */
    public const TOMORROW_FROM_HOUR = 18;

    /** „Cu 2 ore înainte”: fereastra în care pleacă (nu mai târziu de 30 de minute înainte de început). */
    public const READY_HOURS = 2;

    /** Rulează tot ce e programat. Întoarce câte notificări au fost create, pe tip. @return array<string,int> */
    public static function runScheduled(?Carbon $now = null): array
    {
        if (! Notifier::enabled()) {
            return [];
        }
        $now ??= now();

        return [
            'party_new' => self::partyNew($now),
            'price' => self::priceRises($now),
            'seats' => self::lowSeats($now),
            'buyers' => self::buyers($now),
            'tomorrow' => self::partyTomorrow($now),
            'ready' => self::partyReady($now),
            'announcement' => self::announcements($now),
        ];
    }

    // ---- Petrecere nouă ------------------------------------------------------------

    /** Petrecerile publicate și active care n-au fost încă anunțate: câte o notificare către toți abonații. */
    public static function partyNew(?Carbon $now = null, ?Party $only = null, ?Participant $to = null): int
    {
        $now ??= now();
        if (Notifier::isQuietHour($now)) {
            return 0;
        }

        $count = 0;
        $parties = $only ? Party::query()->visible('auth')->whereKey($only->id)->get() : Party::query()->visible('auth')->get();
        foreach ($parties as $party) {
            if (! PushEvent::claim('party_new:'.$party->id)) {
                continue;
            }
            if (! Notifier::typeEnabled('party_new')) {
                continue;   // tip oprit: petrecerea se consideră tratată (nu se anunță mai târziu)
            }
            $count += self::broadcast(fn (Participant $p) => Notifier::notify(
                $p, 'party_new', 'party_new:'.$party->id, 'Petrecere nouă', $party->name.' · '.self::when($party).self::freeEntryNote($party, $p),
                self::partyUrl($party),
            ), $to);
        }

        return $count;
    }

    // ---- Favorite fără bilet: preț care crește / puține locuri --------------------------

    /**
     * Pentru petrecerile viitoare cu vânzare online: dacă o reducere (`until`) expiră în următoarele 24 de ore și prețul chiar crește
     * după ea, cei care au petrecerea la inimă și nu au bilet primesc „de mâine crește prețul”.
     */
    public static function priceRises(?Carbon $now = null, ?Party $only = null): int
    {
        $now ??= now();
        if (Notifier::isQuietHour($now)) {
            return 0;
        }

        $count = 0;
        foreach (self::sellableParties($now, $only) as $party) {
            $byMoment = [];
            foreach ((array) $party->ticket_types as $type) {
                $current = $party->priceForTypeAt($type, $now);
                foreach ((array) ($type['discounts'] ?? $type['tiers'] ?? []) as $d) {
                    $until = trim((string) ($d['until'] ?? ''));
                    if ($until === '') {
                        continue;
                    }
                    $moment = mb_strlen($until) <= 10 ? Carbon::parse($until)->endOfDay()->addSecond() : Carbon::parse($until);
                    if ($moment->lessThanOrEqualTo($now) || $moment->greaterThan($now->copy()->addDay())) {
                        continue;
                    }
                    $after = $party->priceForTypeAt($type, $moment);
                    if ($current !== null && $after !== null && $after > $current) {
                        $byMoment[$moment->timestamp]['dateOnly'] = mb_strlen($until) <= 10;
                        $byMoment[$moment->timestamp]['until'] = $until;
                        $byMoment[$moment->timestamp]['lines'][] = [$type['name'] ?? 'Bilet', $current, $after];
                    }
                }
            }

            foreach ($byMoment as $ts => $info) {
                $key = 'price:'.$party->id.':'.$ts;
                $body = self::priceBody($party, $info);
                $count += self::toInterestedWithoutTicket($party, 'price', $key, 'Prețul biletelor crește', $body);
            }
        }

        return $count;
    }

    /** Petrecerile cu locuri puține (≤10% din cele puse la vânzare, sau din limita unui tip) — către favoritele fără bilet. */
    public static function lowSeats(?Carbon $now = null, ?Party $only = null): int
    {
        $now ??= now();
        if (Notifier::isQuietHour($now)) {
            return 0;
        }

        $count = 0;
        foreach (self::sellableParties($now, $only) as $party) {
            $left = self::lowSeatsLeft($party);
            if ($left !== null) {
                $count += self::toInterestedWithoutTicket(
                    $party, 'seats', 'seats:'.$party->id, 'Au mai rămas puține bilete',
                    $party->name.': '.($left === 1 ? 'a mai rămas un singur bilet' : 'au mai rămas doar '.$left.' bilete').'.',
                );
            }
        }

        return $count;
    }

    /** Apelat imediat după o vânzare: dacă pragul de locuri sau cel de cumpărători a fost atins, trimite fără să aștepte cron-ul. */
    public static function afterSale(Party $party): void
    {
        if (! Notifier::enabled() || Notifier::isQuietHour()) {
            return;   // în orele liniștite preia cron-ul de dimineață
        }
        $party->refresh();
        if ($party->state() === 'past' || ! $party->online_sales) {
            return;
        }
        if (($left = self::lowSeatsLeft($party)) !== null) {
            self::toInterestedWithoutTicket(
                $party, 'seats', 'seats:'.$party->id, 'Au mai rămas puține bilete',
                $party->name.': '.($left === 1 ? 'a mai rămas un singur bilet' : 'au mai rămas doar '.$left.' bilete').'.',
            );
        }
        self::buyers(now(), $party);
    }

    // ---- Bilete luate de alții (prag setat de admin) --------------------------------

    /**
     * Pentru petrecerile viitoare cu vânzare online la care cel puțin X persoane (pragul din setări) au cumpărat bilet: abonații fără bilet
     * la ea află, o singură dată pe petrecere. Fără prag completat sau cu tipul oprit nu face nimic. `$threshold` (doar pentru date de test)
     * înlocuiește pragul din setări.
     */
    public static function buyers(?Carbon $now = null, ?Party $only = null, ?Participant $to = null, ?int $threshold = null): int
    {
        $now ??= now();
        $threshold ??= Notifier::buyersThreshold();
        if ($threshold === null || ! Notifier::typeEnabled('buyers') || Notifier::isQuietHour($now)) {
            return 0;
        }

        $count = 0;
        foreach (self::sellableParties($now, $only) as $party) {
            $buyers = Ticket::query()->counted()->where('party_id', $party->id)->whereNotNull('owner_participant_id')->distinct()->count('owner_participant_id');
            if ($buyers < $threshold) {
                continue;
            }
            $withTicket = Ticket::query()->counted()->where('party_id', $party->id)->get(['owner_participant_id', 'holder_participant_id'])
                ->flatMap(fn ($t) => [$t->owner_participant_id, $t->holder_participant_id])->filter()->unique();
            $body = $party->name.': '.($buyers === 1 ? 'o persoană și-a luat deja bilet' : $buyers.' persoane și-au luat deja bilet').'.';
            foreach (Notifier::subscribedParticipants()->whereNotIn('id', $withTicket)->when($to, fn ($q) => $q->whereKey($to->id))->get() as $p) {
                $count += Notifier::notify($p, 'buyers', 'buyers:'.$party->id, 'Se umple petrecerea', $body, self::partyUrl($party)) ? 1 : 0;
            }
        }

        return $count;
    }

    /** Cele mai puține bilete rămase (>0) dintre limita totală și limitele pe tip, dacă vreuna a ajuns la prag; altfel null. */
    public static function lowSeatsLeft(Party $party): ?int
    {
        $candidates = [];
        if ($party->tickets_for_sale) {
            $candidates[] = [(int) $party->tickets_for_sale, (int) $party->tickets_for_sale - TicketOrders::soldTotal($party)];
        }
        foreach ((array) $party->ticket_types as $type) {
            if (isset($type['limit']) && is_numeric($type['limit']) && (int) $type['limit'] > 0) {
                $candidates[] = [(int) $type['limit'], (int) $type['limit'] - TicketOrders::soldCount($party, (string) ($type['name'] ?? ''))];
            }
        }

        $low = [];
        foreach ($candidates as [$total, $left]) {
            if ($left > 0 && $left * 100 <= $total * self::LOW_SEATS_PERCENT) {
                $low[] = $left;
            }
        }

        return $low ? min($low) : null;
    }

    // ---- Cu bilet: mâine și cu 2 ore înainte --------------------------------------

    /** De la 18:00, ziua dinainte: „mâine e petrecerea”, către cei care dețin un bilet valid. */
    public static function partyTomorrow(?Carbon $now = null, ?Party $only = null): int
    {
        $now ??= now();
        if ($now->hour < self::TOMORROW_FROM_HOUR) {
            return 0;
        }

        $count = 0;
        $parties = Party::query()->visible('auth')->whereBetween('starts_at', [$now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()])
            ->when($only, fn ($q) => $q->whereKey($only->id))->get();
        foreach ($parties as $party) {
            $where = $party->location_name ? ', '.$party->location_name : '';
            $body = 'Mâine, la '.$party->starts_at->format('H:i').$where.'. '.$party->name.'.';
            $ids = Ticket::query()->where('party_id', $party->id)->where('status', Ticket::VALID)->whereNotNull('holder_participant_id')->pluck('holder_participant_id')->unique();
            foreach (Participant::query()->whereIn('id', $ids)->get() as $p) {
                $count += Notifier::notify($p, 'tomorrow', 'tomorrow:'.$party->id, 'Petrecerea e mâine', $body, '/bilete', true) ? 1 : 0;
            }
        }

        return $count;
    }

    /** Cu cel mult 2 ore înainte de început (și cel puțin 30 de minute): „ești gata?” + dresscode-ul, dacă există. */
    public static function partyReady(?Carbon $now = null, ?Party $only = null): int
    {
        $now ??= now();
        $count = 0;
        $parties = Party::query()->visible('auth')->whereBetween('starts_at', [$now->copy()->addMinutes(30), $now->copy()->addHours(self::READY_HOURS)])
            ->when($only, fn ($q) => $q->whereKey($only->id))->get();
        foreach ($parties as $party) {
            $dress = self::dresscodeFor($party);
            $body = 'Ești gata de petrecere? '.($dress ? 'Dresscode: '.$dress.'.' : 'Începem la '.$party->starts_at->format('H:i').'.');
            $ids = Ticket::query()->where('party_id', $party->id)->where('status', Ticket::VALID)->whereNotNull('holder_participant_id')->pluck('holder_participant_id')->unique();
            foreach (Participant::query()->whereIn('id', $ids)->get() as $p) {
                $count += Notifier::notify($p, 'ready', 'ready:'.$party->id, $party->name, $body, '/bilete', true) ? 1 : 0;
            }
        }

        return $count;
    }

    // ---- Bilet anulat (imediat) -----------------------------------------------------

    /** Biletul anulat din admin: deținătorul (și cumpărătorul, dacă e alt cont) află imediat, cu suma returnată în credite. */
    public static function ticketCancelled(Ticket $ticket, float $refunded = 0.0): void
    {
        if (! Notifier::enabled()) {
            return;
        }
        $ticket->loadMissing('party');
        $ids = collect([$ticket->holder_participant_id, $ticket->owner_participant_id])->filter()->unique();

        foreach (Participant::query()->whereIn('id', $ids)->get() as $p) {
            $body = 'Biletul '.$ticket->shortCode().' la '.($ticket->party?->name ?? 'petrecere').' a fost anulat.';
            if ($refunded > 0 && $p->id === $ticket->owner_participant_id) {
                $body .= ' '.number_format($refunded, 2, ',', '.').' lei au fost returnați în credite.';
            }
            Notifier::notify($p, 'ticket_cancelled', 'ticket_cancelled:'.$ticket->id, 'Bilet anulat', $body, '/bilete', true);
        }
    }

    // ---- Bilet cumpărat / primit, plată cu credite, card complet (imediat) ----------------

    /** Comanda de bilete plătită sau rezervată (nu și rezervarea cu cardul rămasă neplătită): cumpărătorul află imediat. */
    public static function ticketsBought(Order $order): void
    {
        $buyer = Participant::query()->find($order->participant_id);
        $party = Party::query()->find($order->party_id);
        if (! $buyer || ! $party || ! Notifier::enabled()) {
            return;
        }
        $n = Ticket::query()->where('order_id', $order->id)->count();
        $total = (float) $order->total;
        Notifier::notify(
            $buyer, 'ticket_new', 'ticket_new:order:'.$order->id, $n === 1 ? 'Bilet cumpărat' : 'Bilete cumpărate',
            $party->name.': '.($n === 1 ? 'un bilet' : $n.' bilete').($total > 0 ? ', '.self::money($total).' lei' : '').'.', '/bilete', true,
        );
    }

    /** Biletul trimis de alt participant: destinatarul (dacă are cont cu notificări) află imediat. */
    public static function ticketReceived(Ticket $ticket, Participant $from, Participant $to, int $transferId): void
    {
        $ticket->loadMissing('party');
        Notifier::notify(
            $to, 'ticket_new', 'ticket_new:transfer:'.$transferId, 'Ai primit un bilet',
            $from->name.' ți-a trimis un bilet la '.($ticket->party?->name ?? 'o petrecere').'.', '/bilete', true,
        );
    }

    /**
     * Plata cu credite la Bar (vânzare) sau la Recepție (intrare). Biletele plătite cu credite în aplicație nu intră aici
     * (primesc doar notificarea de bilet). `$id` face notificarea unică (id-ul tranzacției).
     */
    public static function creditPaid(Participant $p, float $amount, ?string $referenceType, int|string $id): void
    {
        $where = match ($referenceType) {
            Sale::class => 'la Bar',
            PartyEntry::class => 'la Recepție',
            default => null,
        };
        if ($where === null || ! Notifier::enabled()) {
            return;
        }
        $balance = CreditLedger::balance($p->fresh() ?? $p);
        Notifier::notify(
            $p, 'credit_payment', 'credit_payment:'.$id, 'Plată cu credite',
            'Ai plătit '.self::money($amount).' lei cu credite '.$where.'. Sold: '.self::money($balance).' lei.', '/portofel', true,
        );
    }

    /**
     * Credite încărcate (aplicație cu cardul, Recepție, manual): participantul află imediat. `$amount` = totalul creditat (cu tot cu bonus).
     */
    public static function creditLoaded(Participant $p, float $amount, float $bonus, ?string $source, int|string $id): void
    {
        if (! Notifier::enabled()) {
            return;
        }
        $where = match ($source) {
            CreditTransaction::SOURCE_RECEPTION => ' la Recepție',
            CreditTransaction::SOURCE_MANUAL => ' de către echipă',
            default => '',
        };
        $balance = CreditLedger::balance($p->fresh() ?? $p);
        Notifier::notify(
            $p, 'credit_load', 'credit_load:'.$id, 'Credite încărcate',
            'Ai încărcat '.self::money($amount).' lei credite'.$where.($bonus > 0 ? ' (din care bonus '.self::money($bonus).' lei)' : '').'. Sold: '.self::money($balance).' lei.', '/portofel', true,
        );
    }

    /** Cardul de fidelitate are toate ștampilele: următoarea intrare e gratuită. O singură dată pe card. */
    public static function cardReady(Participant $p, int|string $cardId): void
    {
        if (! Notifier::enabled()) {
            return;
        }
        Notifier::notify(
            $p, 'card_ready', 'card_ready:'.$cardId, 'Ai intrare gratuită',
            'Ai strâns toate ștampilele. Următoarea intrare la o petrecere cu fidelitate e gratuită.', '/', true,
        );
    }

    /** Hook de card complet, apelat din LoyaltyLedger. */
    public static function cardCompleted(LoyaltyCard $card): void
    {
        $card->loadMissing('participant');
        if ($card->participant) {
            self::cardReady($card->participant, $card->id);
        }
    }

    /** O eroare de notificare nu trebuie să strice plata, vânzarea sau ștampila. */
    public static function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ---- Anunțuri -----------------------------------------------------------------

    /**
     * Fiecare anunț publicat și vizibil (fără `push_sent_at`) pleacă o singură dată către toți abonații. Dacă tipul „Anunț nou” e oprit,
     * anunțul se marchează ca tratat fără trimitere (nu se anunță mai târziu).
     */
    public static function announcements(?Carbon $now = null, ?Announcement $only = null, ?Participant $to = null): int
    {
        $now ??= now();
        $count = 0;
        $items = Announcement::query()->visible('auth')->whereNull('push_sent_at')->when($only, fn ($q) => $q->whereKey($only->id))->get();
        foreach ($items as $a) {
            if (Announcement::query()->whereKey($a->id)->whereNull('push_sent_at')->update(['push_sent_at' => $now]) === 0) {
                continue;   // preluat între timp de altă rulare
            }
            if (! Notifier::typeEnabled('announcement')) {
                continue;
            }
            $count += self::broadcast(fn (Participant $p) => Notifier::notify(
                $p, 'announcement', 'announcement:'.$a->id, $a->title, Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $a->body))), 150, '…'),
                route('app.announcement', $a, false), true,
            ), $to);
        }

        return $count;
    }

    /** Din admin, la salvare/publicare: trimite imediat anunțurile cu push bifat, fără să strice salvarea dacă ceva nu merge. */
    public static function announcementsNow(): void
    {
        try {
            if (Notifier::enabled()) {
                self::announcements();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ---- Ajutoare -----------------------------------------------------------------

    /** Trimite către toți abonații (sau doar către $to, pentru date de test), pe bucăți. @param callable(Participant): mixed $send */
    private static function broadcast(callable $send, ?Participant $to = null): int
    {
        $count = 0;
        Notifier::subscribedParticipants()->when($to, fn ($q) => $q->whereKey($to->id))->chunkById(200, function ($participants) use ($send, &$count) {
            foreach ($participants as $p) {
                $count += $send($p) ? 1 : 0;
            }
        });

        return $count;
    }

    /** Către cei care au petrecerea la favorite, au abonare push și nu au (nici ca deținători, nici ca cumpărători) vreun bilet. */
    private static function toInterestedWithoutTicket(Party $party, string $kind, string $key, string $title, string $body): int
    {
        $ids = PartyInterest::query()->where('party_id', $party->id)->pluck('participant_id');
        $withTicket = Ticket::query()->counted()->where('party_id', $party->id)
            ->where(fn ($q) => $q->whereIn('owner_participant_id', $ids)->orWhereIn('holder_participant_id', $ids))
            ->get(['owner_participant_id', 'holder_participant_id'])
            ->flatMap(fn ($t) => [$t->owner_participant_id, $t->holder_participant_id])->filter()->unique();

        $count = 0;
        foreach (Notifier::subscribedParticipants()->whereIn('id', $ids->diff($withTicket))->get() as $p) {
            $count += Notifier::notify($p, $kind, $key, $title, $body, self::partyUrl($party)) ? 1 : 0;
        }

        return $count;
    }

    /** Petrecerile viitoare, publicate, active, cu vânzare online. */
    private static function sellableParties(Carbon $now, ?Party $only = null)
    {
        return Party::query()->visible('auth')->where('online_sales', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '>', $now))
            ->when($only, fn ($q) => $q->whereKey($only->id))->get();
    }

    /** „ · Ai intrare gratuită” pentru cei cu card de fidelitate complet, la o petrecere cu fidelitate. */
    private static function freeEntryNote(Party $party, Participant $p): string
    {
        return LoyaltyLedger::partyEligible($party) && LoyaltyLedger::readyForFreeEntry($p) ? ' · Ai intrare gratuită' : '';
    }

    private static function money(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    private static function priceBody(Party $party, array $info): string
    {
        $fmt = fn ($v) => $v <= 0 ? 'gratuit' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',').' lei';
        $parts = collect($info['lines'])->map(fn ($l) => (count($info['lines']) > 1 ? $l[0].': ' : '').$fmt($l[1]).' → '.$fmt($l[2]))->unique()->take(3)->implode('; ');
        $lead = $info['dateOnly'] ? 'Ultima zi la prețul actual, de mâine crește' : 'Prețul actual e valabil doar până la '.Party::formatUntil($info['until']);

        return $party->name.': '.$lead.' ('.$parts.').';
    }

    public static function dresscodeFor(Party $party): ?string
    {
        $date = $party->starts_at?->toDateString();
        foreach ((array) $party->days as $day) {
            if (($day['date'] ?? null) === $date && filled($day['dresscode'] ?? null)) {
                return (string) $day['dresscode'];
            }
        }

        return filled($party->dresscode) ? (string) $party->dresscode : (collect((array) $party->days)->pluck('dresscode')->filter()->first() ?: null);
    }

    private static function when(Party $party): string
    {
        return $party->starts_at ? $party->starts_at->format('d.m.Y, H:i') : 'în curând';
    }

    private static function partyUrl(Party $party): string
    {
        return route('app.party', $party, false);
    }
}
