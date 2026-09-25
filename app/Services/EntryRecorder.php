<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * @return object{name: string, list_price: float, price: float, grace: bool}|null
     */
    public static function quote(Party $party, string $ticketName, ?Carbon $at = null): ?object
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

            return (object) [
                'name' => $t['name'],
                'list_price' => round($list, 2),
                'price' => round($price, 2),
                'grace' => $price < $list - 0.004,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array{method?: string, amount?: mixed}>  $payments  pe TOTALUL grupului
     * @param  bool  $enforceState  false doar pentru seedere/importuri (altfel: doar petreceri viitoare sau în desfășurare)
     * @param  array<int, int>  $participants  id-uri de participanți identificați (opțional; restul persoanelor rămân anonime).
     *                                         Nu pot fi mai mulți decât persoane, fără dubluri, iar un participant nu poate avea
     *                                         două intrări valabile în aceeași sesiune de recepție (ParticipantRegistry::enteredInSession).
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
    ): Collection {
        if ($count < 1 || $count > self::MAX_GROUP) {
            throw new DomainException('Numărul de persoane trebuie să fie între 1 și '.self::MAX_GROUP.'.');
        }
        if ($enforceState && ! in_array($party->state(), ['upcoming', 'live'], true)) {
            throw new DomainException('Petrecerea nu primește intrări (nu e publicată sau s-a încheiat).');
        }

        $at ??= now();
        $quote = self::quote($party, $ticketName, $at)
            ?? throw new DomainException('Tipul de bilet „'.$ticketName.'” nu există sau nu are preț.');

        // Pret per persoana: implicit cel cu toleranta; suprascrierea cere motiv.
        $unit = $quote->price;
        $reason = null;
        if ($overridePrice !== null) {
            if ($overridePrice < 0 || $overridePrice > 10000) {
                throw new DomainException('Prețul trebuie să fie între 0 și 10.000 lei.');
            }
            if (abs($overridePrice - $quote->price) >= 0.005) {
                $reason = trim((string) $overrideReason);
                if ($reason === '') {
                    throw new DomainException('Spune de ce schimbi prețul (motiv obligatoriu).');
                }
                $unit = round($overridePrice, 2);
            }
        }
        $overridden = $reason !== null;

        $participantIds = self::validatedParticipants($party, $participants, $count);

        $unitCents = self::cents($unit);
        $totalCents = $unitCents * $count;
        $queue = self::normalizePayments($party, $payments, $totalCents);

        $entries = DB::transaction(function () use ($party, $quote, $count, $unit, $unitCents, $queue, $reason, $overridden, $adminId, $at, $participantIds) {
            // Sesiunea de recepție deschisă a petrecerii (se deschide singură la prima înregistrare).
            $session = ReceptionSession::openFor($party->id, $adminId);
            $batch = (string) Str::uuid();
            $qi = 0;
            $rows = collect();

            for ($i = 0; $i < $count; $i++) {
                $entry = PartyEntry::create([
                    'party_id' => $party->id,
                    'reception_session_id' => $session->id,
                    'batch' => $batch,
                    'ticket_type' => $quote->name,
                    'list_price' => $quote->list_price,
                    'price_paid' => $unit,
                    'grace_applied' => $quote->grace && ! $overridden,
                    'override_reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
                    'entered_at' => $at,
                    'participant_id' => $participantIds[$i] ?? null,
                    'created_by' => $adminId,
                ]);

                // Distribuie plata pe acest rand, in ordinea metodelor introduse.
                $need = $unitCents;
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

            return $rows;
        });

        ActivityLogger::log('entries.recorded', sprintf(
            'A înregistrat %d × „%s” (%s lei) la „%s”%s%s.',
            $count,
            $quote->name,
            self::money($unitCents * $count / 100),
            $party->name,
            $participantIds ? ' — '.count($participantIds).' identificați' : '',
            $overridden ? ' — preț suprascris: '.$reason : ''
        ));

        return $entries;
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
                throw new DomainException('„'.$participant->name.'” a intrat deja în această sesiune de recepție, la '.$prev->entered_at->format('H:i').'. Dacă e o seară nouă, închide mai întâi casa (Recepție › Raportări).');
            }
        }

        return $ids;
    }

    /** Plata introdusa pe total -> [[metoda, centi], ...] (vezi PaymentRows::normalize). */
    private static function normalizePayments(Party $party, array $payments, int $totalCents): array
    {
        return PaymentRows::normalize(
            PaymentMethods::forEntry($party),
            $payments,
            $totalCents,
            'la intrare',
            'Intrarea e gratuită: nu se înregistrează nicio plată.'
        );
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
