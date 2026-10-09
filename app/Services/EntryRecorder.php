<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Models\Ticket;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Recepție - intrări). Singura cale de a înregistra/anula intrări (admin acum, PWA mai târziu).
 *
 * O înregistrare = un GRUP de N persoane (N >= 1) cu același tip de bilet, același preț și o plată introdusă
 * PE TOTAL (poate fi mixtă): sistemul creează N rânduri `party_entries` (același `batch`) și distribuie plata
 * pe rânduri, în ordinea în care a fost introdusă, astfel încât totalurile pe metodă ies exact ca în încasare.
 *
 * Prețul:
 *  - list_price = ce spune sistemul la ora intrării (fără toleranță);
 *  - prețul implicit = cel cu toleranța din Setări (entry_grace_minutes) aplicată reducerilor cu oră;
 *  - recepționerul poate suprascrie prețul (per persoană), cu motiv obligatoriu (logat).
 * Codul de reducere (DXA: Coduri de reducere, App\Services\DiscountCodes): opțional, un singur cod pe comandă, aplicat
 * fiecărei persoane din grup peste prețul curent (fiecare bilet consumă o utilizare a codului); `list_price` rămâne prețul de listă, `price_paid` cel final,
 * `discount_amount` (per persoană) ce a scăzut codul. Utilizările se numără din intrările valabile.
 * Plata: metode din PaymentMethods::forEntry($party) (fără tokeni); suma plăților = totalul grupului.
 * Nu se șterge nimic: anularea (cu motiv) marchează rândurile grupului.
 */
class EntryRecorder
{
    public const MAX_GROUP = 50;

    /** Toleranța din Setări, în minute (0-60). */
    public static function graceMinutes(): int
    {
        return max(0, min(60, (int) round((float) Settings::get('entry_grace_minutes'))));
    }

    /**
     * Prețul unui tip de bilet acum: lista (fără toleranță) și prețul de încasat (cu toleranță).
     *
     * Cu $code (opțional): prețul include codul de reducere (aruncă DomainException dacă nu se poate aplica);
     * `price_before_code` = prețul curent fără cod, `discount` = ce a scăzut codul (per persoană), `code` = codul aplicat.
     *
     * @param  array<int, int>  $participantIds  pentru limita per participant a codului
     * @return object{name: string, list_price: float, price: float, grace: bool, price_before_code: float, discount: float, code: ?PartyDiscountCode}|null
     */
    public static function quote(Party $party, string $ticketName, ?Carbon $at = null, ?string $code = null, array $participantIds = [], int $count = 1): ?object
    {
        $at ??= now();

        foreach ($party->entryTicketTypes() as $t) {
            if ($t['name'] !== $ticketName) {
                continue;
            }

            $list = $party->priceForTypeAt($t['type'], $at, 0);
            $price = $party->priceForTypeAt($t['type'], $at, self::graceMinutes());

            if ($list === null || $price === null) {
                return null;
            }

            $quote = (object) [
                'name' => $t['name'],
                'list_price' => round($list, 2),
                'price' => round($price, 2),
                'grace' => $price < $list - 0.004,
                'price_before_code' => round($price, 2),
                'discount' => 0.0,
                'code' => null,
            ];

            if ($code !== null && trim($code) !== '') {
                $applied = DiscountCodes::apply($party, $t['name'], $quote->price, $code, $participantIds, $at, $count);
                $quote->price = $applied->price;
                $quote->discount = $applied->discount;
                $quote->code = $applied->code;
            }

            return $quote;
        }

        return null;
    }

    /**
     * DXA: adaugat (runda 15). Cât se încasează la intrare pentru un bilet online, în bani întregi (bani/cenți):
     *  - bilet valabil: prețul lui dacă încă nu s-a plătit (acum toate comenzile sunt „de plătit la intrare"), altfel 0;
     *  - bilet cu termenul „intri până la" depășit (cu toleranța din Setări): prețul curent de la intrare minus ce s-a plătit
     *    (nepltit încă = cel mai mare dintre prețul lui și cel curent); niciodată sub 0.
     *
     * @return array{due: int, expired: bool, current: ?float}
     */
    public static function ticketDue(Ticket $ticket, ?Carbon $at = null): array
    {
        $at ??= now();
        $unpaid = ($ticket->order?->payment_status ?? Order::PAY_AT_ENTRY) === Order::PAY_AT_ENTRY;
        $paid = (float) $ticket->price;
        $base = $unpaid ? $paid : 0.0;

        $expired = $ticket->isExpired($at->copy()->subMinutes(self::graceMinutes()));
        if (! $expired) {
            return ['due' => self::cents($base), 'expired' => false, 'current' => null];
        }

        $party = $ticket->party;
        $current = $party ? self::quote($party, $ticket->ticket_type, $at)?->price : null;
        if ($current === null) {
            return ['due' => self::cents($base), 'expired' => true, 'current' => null];
        }

        $due = $unpaid ? max($paid, $current) : max(0.0, $current - $paid);

        return ['due' => self::cents($due), 'expired' => true, 'current' => $current];
    }

    /**
     * @param  array<int, array{method?: string, amount?: mixed}>  $payments  pe TOTALUL grupului
     * @param  bool  $enforceState  false doar pentru seedere/importuri (altfel: doar petreceri viitoare sau în desfășurare)
     * @param  array<int, int>  $participants  id-uri de participanți identificați (opțional; restul persoanelor rămân anonime).
     *                                         Nu pot fi mai mulți decât persoane, fără dubluri, iar un participant nu poate avea
     *                                         două intrări valabile în aceeași sesiune de recepție (ParticipantRegistry::enteredInSession).
     * @param  array<int, int>  $ticketIds  DXA (runda 15): bilete online prezentate la intrare (fiecare acoperă o persoană din $count; restul
     *                                      persoanelor plătesc prețul curent). Se încasează `ticketDue()` pe bilet; biletele devin „folosite".
     * @return Collection<int, PartyEntry>
     */
    public static function record(
        Party $party,
        string $ticketName,
        int $count = 1,
        array $payments = [],
        ?float $overridePrice = null,
        ?string $overrideReason = null,
        ?int $adminId = null,
        ?Carbon $at = null,
        bool $enforceState = true,
        array $participants = [],
        ?string $discountCode = null,
        array $ticketIds = [],
    ): Collection {
        if ($count < 1 || $count > self::MAX_GROUP) {
            throw new DomainException('Numărul de persoane trebuie să fie între 1 și '.self::MAX_GROUP.'.');
        }
        if ($enforceState && ! $party->acceptsReceptionRecords()) {
            self::logRefusedClosed($party, $count);

            throw new DomainException('Petrecerea nu primește intrări (nu e publicată, sau s-a încheiat și casa e închisă).');
        }

        $at ??= now();

        // Biletele online prezentate (fiecare acoperă o persoană); restul persoanelor plătesc la prețul curent.
        $tickets = self::loadTickets($party, $ticketIds);
        $extras = $count - $tickets->count();
        if ($extras < 0) {
            throw new DomainException('Ai '.$tickets->count().' bilete online pentru '.$count.' '.($count === 1 ? 'persoană' : 'persoane').'. Mărește numărul de persoane.');
        }

        $quote = $extras > 0 || $tickets->isEmpty()
            ? (self::quote($party, $ticketName, $at) ?? throw new DomainException('Tipul de bilet „'.$ticketName.'” nu există sau nu are preț.'))
            : null;

        // Participanții biletelor primesc intrarea; ceilalți (aleși în plus) merg la persoanele fără bilet.
        $holderIds = $tickets->pluck('holder_participant_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $extraIds = array_values(array_diff(array_values(array_filter(array_map('intval', $participants))), $holderIds));
        // Un titular care a intrat deja în sesiune (alt bilet al lui) nu blochează biletul: acesta intră fără nume.
        $freeHolders = array_values(array_filter($holderIds, function (int $id) use ($party) {
            $p = Participant::query()->find($id);

            return $p && ! ParticipantRegistry::enteredInSession($p, $party);
        }));
        // Cu bilete online (scanare QR personal): persoana ale cărei alte bilete au intrat deja nu mai blochează intrarea; biletul intră fără nume.
        if ($tickets->isNotEmpty()) {
            $extraIds = array_values(array_filter($extraIds, function (int $id) use ($party) {
                $p = Participant::query()->find($id);

                return $p && ! ParticipantRegistry::enteredInSession($p, $party);
            }));
        }
        $participantIds = self::validatedParticipants($party, array_merge($freeHolders, $extraIds), $count);

        // Codul de reducere (opțional) se aplică peste prețul curent al persoanelor FĂRĂ bilet; de aici „prețul sistemului" e cel cu cod.
        $applied = null;
        if ($extras > 0 && $discountCode !== null && trim($discountCode) !== '') {
            $applied = DiscountCodes::apply($party, $quote->name, $quote->price, $discountCode, $participantIds, $at, $extras);
        }
        $systemPrice = $applied?->price ?? $quote?->price ?? 0.0;

        // Pret per persoana fara bilet: implicit cel cu toleranta (si cod); suprascrierea cere motiv.
        $unit = $systemPrice;
        $reason = null;
        if ($overridePrice !== null && $extras > 0) {
            if ($overridePrice < 0 || $overridePrice > 10000) {
                throw new DomainException('Prețul trebuie să fie între 0 și 10.000 lei.');
            }
            if (abs($overridePrice - $systemPrice) >= 0.005) {
                $reason = trim((string) $overrideReason);
                if ($reason === '') {
                    throw new DomainException('Spune de ce schimbi prețul (motiv obligatoriu).');
                }
                $unit = round($overridePrice, 2);
            }
        }
        $overridden = $reason !== null;

        // Suma de încasat pe fiecare rând: întâi biletele online (în ordine), apoi persoanele fără bilet.
        $unitCents = self::cents($unit);
        $ticketDues = $tickets->map(fn (Ticket $t) => self::ticketDue($t, $at));
        $rowCents = array_merge($ticketDues->pluck('due')->all(), array_fill(0, $extras, $unitCents));
        $totalCents = array_sum($rowCents);
        $queue = self::normalizePayments($party, $payments, $totalCents);

        // Plata cu credite: necesita exact un participant identificat (creditele ies din portofelul lui —
        // cu mai multi identificati in acelasi grup nu s-ar sti al cui portofel se debiteaza).
        $creditCents = array_sum(array_map(fn ($q) => $q[0] === PaymentMethods::CREDIT ? $q[1] : 0, $queue));
        if ($creditCents > 0 && count($participantIds) !== 1) {
            throw new DomainException('Plata cu credite la intrare necesită exact un participant identificat (creditele ies din portofelul lui).');
        }

        $onlyExisting = $enforceState && $party->receptionOnlyExistingSession();

        $entries = DB::transaction(function () use ($freeHolders, $party, $quote, $applied, $count, $extras, $rowCents, $tickets, $extraIds, $queue, $reason, $overridden, $adminId, $at, $participantIds, $creditCents, $onlyExisting) {
            // Codul de reducere: reverificat pe rândul blocat, ca două comenzi simultane să nu depășească limitele.
            if ($applied) {
                $locked = PartyDiscountCode::query()->whereKey($applied->code->id)->lockForUpdate()->first()
                    ?? throw new DomainException('Codul de reducere nu mai există.');
                DiscountCodes::assertUsable($locked, $quote->name, $at);
                DiscountCodes::assertLimits($locked, $participantIds, $extras);
            }

            // Biletele online: reverificate pe rândurile blocate (altă recepție nu le-a folosit între timp).
            if ($tickets->isNotEmpty()) {
                $locked = Ticket::query()->whereIn('id', $tickets->pluck('id'))->lockForUpdate()->get();
                if ($locked->contains(fn (Ticket $t) => $t->status !== Ticket::VALID)) {
                    throw new DomainException('Un bilet online a fost deja folosit sau anulat. Reîncarcă și scanează din nou.');
                }
            }

            // Sesiunea de recepție deschisă a petrecerii (se deschide singură la prima înregistrare).
            $session = ReceptionSession::openFor($party->id, $adminId, onlyExisting: $onlyExisting);
            $batch = (string) Str::uuid();
            $qi = 0;
            $rows = collect();

            $extraSlot = 0;
            $seenHolders = [];
            $stampEntries = collect();
            for ($i = 0; $i < $count; $i++) {
                $ticket = $tickets->get($i);   // primele rânduri = bilete online
                $participantId = $ticket ? ($ticket->holder_participant_id ? (int) $ticket->holder_participant_id : null) : ($extraIds[$extraSlot++] ?? null);
                // Mai multe bilete pe același participant: doar primul rând îl poartă (restul intră fără nume), ca să nu se blocheze singure.
                if ($ticket && $participantId) {
                    if (! in_array($participantId, $freeHolders, true) || isset($seenHolders[$participantId])) {
                        $participantId = null;
                    } else {
                        $seenHolders[$participantId] = true;
                    }
                }
                $rowUnit = $rowCents[$i] / 100;

                $entry = PartyEntry::create([
                    'party_id' => $party->id,
                    'reception_session_id' => $session->id,
                    'batch' => $batch,
                    'ticket_type' => $ticket ? $ticket->ticket_type : $quote->name,
                    'list_price' => $ticket ? $ticket->list_price : $quote->list_price,
                    'price_paid' => $rowUnit,
                    'grace_applied' => ! $ticket && $quote->grace && ! $overridden,
                    'override_reason' => ! $ticket && $reason !== null ? Str::limit($reason, 255, '') : null,
                    'discount_code_id' => $ticket ? null : $applied?->code->id,
                    'discount_amount' => $ticket ? 0 : ($applied?->discount ?? 0),
                    'entered_at' => $at,
                    'participant_id' => $participantId,
                    'created_by' => $adminId,
                ]);

                if ($ticket) {
                    $ticket->forceFill(['status' => Ticket::USED, 'party_entry_id' => $entry->id, 'used_at' => $at])->save();
                }
                // Ștampila: doar dacă prezența a fost „plătită” (bilet online cu preț sau încasare la intrare); intrarea 0 lei nu dă ștampilă.
                if ($participantId && ($rowUnit > 0.0 || ($ticket && (float) $ticket->price > 0.0))) {
                    $stampEntries->put($participantId, $entry);
                }

                // Distribuie plata pe acest rand, in ordinea metodelor introduse.
                $need = $rowCents[$i];
                while ($need > 0 && isset($queue[$qi])) {
                    $take = min($need, $queue[$qi][1]);
                    $entry->payments()->create(['method' => $queue[$qi][0], 'amount' => $take / 100]);
                    $queue[$qi][1] -= $take;
                    $need -= $take;
                    if ($queue[$qi][1] === 0) {
                        $qi++;
                    }
                }

                $rows->push($entry);
            }

            if ($creditCents > 0) {
                $participant = Participant::query()->findOrFail($participantIds[0]);
                CreditLedger::pay($participant, $creditCents / 100, PartyEntry::class, $rows->first()->id, $adminId, $at);
            }

            // Ștampilare automată de fidelitate: doar pentru persoanele identificate, pe o petrecere eligibilă, și doar la prezență
            // plătită (o intrare gratuită din reducerea cu oră, preț 0, nu acordă ștampilă; biletul online plătit da).
            if (LoyaltyLedger::partyEligible($party) && $participantIds !== []) {
                foreach ($participantIds as $pid) {
                    $entry = $stampEntries->get($pid);
                    $participant = $entry ? Participant::query()->find($pid) : null;
                    if ($participant) {
                        LoyaltyLedger::recordEntryStamp($participant, $entry, $adminId, $at);
                    }
                }
            }

            return $rows;
        });

        ActivityLogger::log('entries.recorded', sprintf(
            'A înregistrat %d × „%s” (%s lei) la „%s”%s%s.',
            $count,
            $quote->name ?? $tickets->first()->ticket_type,
            self::money($totalCents / 100),
            $party->name,
            $participantIds ? ' — '.count($participantIds).' identificați' : '',
            ($tickets->isNotEmpty() ? ' — '.$tickets->count().' bilete online' : '').($overridden ? ' — preț suprascris: '.$reason : '')
        ).($applied ? sprintf(' Cod de reducere „%s” (−%s lei/persoană).', $applied->code->code, self::money($applied->discount)) : ''));

        return $entries;
    }

    /**
     * DXA: adaugat (runda 48). Încercare de intrare la o petrecere ÎNCHEIATĂ cu casa închisă: se trece în Jurnal (categoria „Intrări”),
     * ca adminul să vadă că cineva a încercat. O dată pe petrecere și persoană la 10 minute (apăsări repetate nu umplu jurnalul).
     * Se apelează înainte de tranzacție: într-o tranzacție care aruncă, rândul de jurnal s-ar pierde odată cu ea.
     */
    private static function logRefusedClosed(Party $party, int $count): void
    {
        if ($party->state() !== 'past') {
            return;   // ciornă / inactivă / alte motive: nu e „intrare la petrecere închisă”
        }

        $actorId = Auth::guard('admin')->id() ?? 0;
        if (! Cache::add('entries.refused_closed.'.$party->id.'.'.$actorId, true, now()->addMinutes(10))) {
            return;
        }

        ActivityLogger::log('entries.refused_closed', sprintf(
            'Încercare de intrare refuzată la „%s” (%d %s): petrecerea s-a încheiat și casa e închisă.',
            $party->name,
            $count,
            $count === 1 ? 'persoană' : 'persoane'
        ));
    }

    /**
     * Biletele online prezentate: existente, ale acestei petreceri, valabile, fără dubluri. Ordinea = cea primită.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Ticket>
     */
    private static function loadTickets(Party $party, array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        $found = Ticket::query()->with(['order', 'party'])->whereIn('id', $ids)->get()->keyBy('id');
        $out = collect();
        foreach ($ids as $id) {
            $t = $found->get($id) ?? throw new DomainException('Un bilet online nu mai există.');
            if ((int) $t->party_id !== (int) $party->id) {
                throw new DomainException('Un bilet este pentru altă petrecere.');
            }
            if ($t->status !== Ticket::VALID) {
                throw new DomainException($t->status === Ticket::PENDING
                    ? 'Biletul '.$t->ticket_type.' nu e plătit încă (așteaptă plata cu cardul).'
                    : 'Biletul '.$t->ticket_type.' a fost deja '.($t->status === Ticket::USED ? 'folosit' : 'anulat').'.');
            }
            $out->push($t);
        }

        return $out->values();
    }

    /** Anulează un grup întreg (cu motiv). Întoarce câte intrări s-au anulat. */
    public static function cancelBatch(string $batch, string $reason, ?int $adminId = null): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Spune de ce anulezi intrarea (motiv obligatoriu).');
        }

        $entries = PartyEntry::query()->where('batch', $batch)->active()->with('party')->get();
        if ($entries->isEmpty()) {
            throw new DomainException('Intrarea nu există sau e deja anulată.');
        }

        if ($blocked = ReceptionSession::closedBlockReason($entries->pluck('reception_session_id'), 'intrarea')) {
            throw new DomainException($blocked);
        }

        PartyEntry::query()->whereIn('id', $entries->pluck('id'))->update([
            'cancelled_at' => now(),
            'cancelled_by' => $adminId,
            'cancel_reason' => Str::limit($reason, 255, ''),
        ]);

        // DXA (runda 15): biletele online folosite la aceste intrări redevin valabile.
        Ticket::query()->whereIn('party_entry_id', $entries->pluck('id'))->where('status', Ticket::USED)
            ->update(['status' => Ticket::VALID, 'party_entry_id' => null, 'used_at' => null]);

        LoyaltyLedger::voidStampsForEntries($entries->pluck('id')->all(), 'Intrarea a fost anulată: '.$reason, $adminId);

        // DXA: adaugat (Credite): creditele plătite pentru intrare se returnează în portofel la anulare.
        CreditLedger::reversePayments(PartyEntry::class, $entries->pluck('id')->all(), $reason, $adminId);

        $first = $entries->first();
        ActivityLogger::log('entries.cancelled', sprintf(
            'A anulat %d × „%s” (%s lei) la „%s”: %s.',
            $entries->count(),
            $first->ticket_type,
            self::money((float) $entries->sum('price_paid')),
            $first->party?->name ?? '—',
            $reason
        ));

        return $entries->count();
    }

    /**
     * Participanții alesați pentru grup: fără dubluri, cel mult cât persoanele, existenți și neanonimizați, fără intrare
     * valabilă în sesiunea de recepție deschisă a petrecerii.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private static function validatedParticipants(Party $party, array $ids, int $count): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw new DomainException('Același participant e ales de două ori.');
        }
        if (count($ids) > $count) {
            throw new DomainException('Ai ales '.count($ids).' participanți pentru '.$count.' '.($count === 1 ? 'persoană' : 'persoane').'.');
        }

        $found = Participant::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $participant = $found->get($id) ?? throw new DomainException('Participantul ales nu există.');

            if ($participant->isAnonymized()) {
                throw new DomainException('Un participant ales a fost anonimizat și nu mai poate fi folosit.');
            }
            if ($prev = ParticipantRegistry::enteredInSession($participant, $party)) {
                throw new DomainException('„'.$participant->name.'” a intrat deja în această sesiune de recepție, la '.$prev->entered_at->format('H:i').'.');
            }
        }

        return $ids;
    }

    /**
     * Plata introdusa pe total -> [[metoda, centi], ...] (vezi PaymentRows::normalize). Metoda „Beneficiu"
     * (App\Support\PaymentMethods::BENEFIT) e adăugată la cele acceptate doar pe o petrecere eligibilă de
     * fidelitate — e cum se plătește intrarea gratis (card digital complet SAU card fizic, fără participant
     * ales; vezi App\Services\LoyaltyLedger).
     */
    private static function normalizePayments(Party $party, array $payments, int $totalCents): array
    {
        $allowed = self::entryMethods($party);

        return PaymentRows::normalize($allowed, $payments, $totalCents, 'la intrare', 'Intrarea e gratuită: nu se înregistrează nicio plată.');
    }

    /** @return array<string, string> */
    public static function entryMethods(Party $party): array
    {
        $allowed = PaymentMethods::forEntry($party);

        if (LoyaltyLedger::partyEligible($party)) {
            $allowed[PaymentMethods::BENEFIT] = PaymentMethods::label(PaymentMethods::BENEFIT);
        }

        return $allowed;
    }

    /**
     * Cele mai folosite metode de plată la intrări dintre cele disponibile (pentru butoanele „Tot cu…”).
     * Ordinea vine din istoricul real (ReceptionStats::entryMethodUsage); metodele nefolosite încă completează după ordinea implicită.
     * „Beneficiu” (intrarea gratis de fidelitate) nu intră aici: are butonul ei.
     *
     * @param  array<string, string>  $methods  cheie => etichetă
     * @return array<string, string>
     */
    public static function topMethods(array $methods, int $limit = 2): array
    {
        $ranked = ReceptionStats::entryMethodUsage()->pluck('method')->all();
        $candidates = array_diff_key($methods, [PaymentMethods::BENEFIT => true]);

        $order = array_merge(
            array_values(array_intersect($ranked, array_keys($candidates))),
            array_values(array_diff(array_keys($candidates), $ranked)),
        );

        $out = [];
        foreach (array_slice($order, 0, $limit) as $key) {
            $out[$key] = $candidates[$key];
        }

        return $out;
    }

    private static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
