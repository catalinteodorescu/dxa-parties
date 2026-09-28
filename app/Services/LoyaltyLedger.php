<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyStamp;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Support\Settings\Settings;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Card de fidelitate). Singura cale de a înrola, ștampila și ajusta cardul de fidelitate al unui
 * participant. La X ștampile (`loyalty_cards.stamps_required`, fixat la crearea cardului), următoarea ștampilă
 * (a X+1-a) completează cardul — acela e cercul de „intrare gratis". Cardul completat se arhivează (status
 * `completed`) și se creează automat unul nou, `active`, cu X-ul curent din Setări.
 *
 * Nu orice participant are card (înrolare explicită — manual acum, din aplicație mai târziu). Nu orice
 * petrecere acordă ștampile: vezi Party::loyalty_eligible și App\Services\EntryRecorder (ștampilare automată +
 * gardul metodei „Beneficiu" la intrare).
 */
class LoyaltyLedger
{
    public const MAX_ADJUST = 50;

    // ---- Citire ------------------------------------------------------

    public static function enabled(): bool
    {
        return (bool) Settings::get('loyalty_enabled');
    }

    public static function stampsRequired(): int
    {
        return max(2, (int) Settings::get('loyalty_stamps_required'));
    }

    /** O petrecere acordă ștampile ȘI acceptă plata „Beneficiu" la intrare doar dacă e bifată ȘI fidelitatea e activă global. */
    public static function partyEligible(?Party $party): bool
    {
        return $party !== null && (bool) $party->loyalty_eligible && self::enabled();
    }

    public static function activeCard(Participant $participant): ?LoyaltyCard
    {
        return LoyaltyCard::query()
            ->where('participant_id', $participant->id)
            ->where('status', LoyaltyCard::ACTIVE)
            ->latest('id')
            ->first();
    }

    /** Cardurile completate/arhivate, cel mai recent primul (istoricul din fișa participantului). */
    public static function archivedCards(Participant $participant)
    {
        return LoyaltyCard::query()
            ->where('participant_id', $participant->id)
            ->where('status', LoyaltyCard::COMPLETED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Cardul activ e complet mai puțin ultimul cerc (adică următoarea intrare identificată e gratis)? */
    public static function readyForFreeEntry(Participant $participant): bool
    {
        $card = self::activeCard($participant);

        return $card !== null && $card->activeStampsCount() === $card->stamps_required;
    }

    // ---- Scriere -------------------------------------------------------

    /**
     * Înrolează un participant (o singură dată — nu se poate re-înrola). $initialStamps: ștampile deja adunate
     * pe un card fizic, preluate la înrolare (0 până la X-1 — nu poate porni deja complet).
     */
    public static function enroll(Participant $participant, ?int $adminId = null, int $initialStamps = 0, ?string $note = null): LoyaltyCard
    {
        if ($participant->isAnonymized()) {
            throw new DomainException('Participantul a fost anonimizat și nu mai poate fi înrolat.');
        }
        if ($participant->isLoyaltyEnrolled()) {
            throw new DomainException('Participantul e deja înrolat la fidelitate.');
        }

        $required = self::stampsRequired();
        if ($initialStamps < 0 || $initialStamps >= $required) {
            throw new DomainException('Ștampilele preluate trebuie să fie între 0 și '.($required - 1).' (cardul nu poate porni deja complet).');
        }

        $card = DB::transaction(function () use ($participant, $required, $initialStamps, $note, $adminId) {
            $card = LoyaltyCard::create([
                'participant_id' => $participant->id,
                'stamps_required' => $required,
                'status' => LoyaltyCard::ACTIVE,
            ]);

            if ($initialStamps > 0) {
                $batch = (string) Str::uuid();
                for ($i = 0; $i < $initialStamps; $i++) {
                    LoyaltyStamp::create([
                        'loyalty_card_id' => $card->id,
                        'source' => LoyaltyStamp::SOURCE_MANUAL_ADJUST,
                        'batch' => $batch,
                        'stamped_at' => now(),
                        'reason' => Str::limit(trim((string) $note) !== '' ? $note : 'Preluate de pe cardul fizic la înrolare', 255, ''),
                        'created_by' => $adminId,
                    ]);
                }
            }

            return $card;
        });

        ActivityLogger::log('loyalty.enrolled', sprintf(
            'A înrolat la fidelitate pe %s%s.',
            $participant->label(),
            $initialStamps > 0 ? ' (cu '.$initialStamps.' ștampile preluate)' : ''
        ));

        return $card;
    }

    /**
     * Ștampilare automată la o intrare identificată (participant + petrecere eligibilă). Nu face nimic dacă
     * participantul nu e înrolat — nu e o eroare, majoritatea participanților nu au card.
     */
    public static function recordEntryStamp(Participant $participant, PartyEntry $entry, ?int $adminId = null, ?Carbon $at = null): void
    {
        if (! $participant->isLoyaltyEnrolled()) {
            return;
        }

        $card = self::activeCard($participant) ?? self::startNextCard($participant);

        self::addStamp($card, LoyaltyStamp::SOURCE_ENTRY, $adminId, $at ?? $entry->entered_at, partyEntryId: $entry->id);
    }

    /**
     * Anulează ștampilele legate de intrările anulate (batch de la Recepție). Când completarea unui card crea
     * automat unul nou, gol: dacă ștampila anulată era exact cea care completase cardul ȘI cardul următor n-a
     * primit între timp nicio ștampilă (nici măcar una anulată ulterior), cardul următor se șterge și cel
     * completat redevine activ — altfel amândouă rămân așa cum sunt (nu se creează două carduri active).
     *
     * @param  array<int, int>  $entryIds
     */
    public static function voidStampsForEntries(array $entryIds, string $reason, ?int $adminId = null): void
    {
        if ($entryIds === []) {
            return;
        }

        $stamps = LoyaltyStamp::query()
            ->whereIn('party_entry_id', $entryIds)
            ->whereNull('voided_at')
            ->with('card')
            ->get();

        if ($stamps->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($stamps, $reason, $adminId) {
            foreach ($stamps as $stamp) {
                $stamp->update([
                    'voided_at' => now(),
                    'void_reason' => Str::limit($reason, 255, ''),
                    'created_by' => $stamp->created_by ?? $adminId,
                ]);

                $card = $stamp->card;
                if (! $card || ! $card->isCompleted()) {
                    continue;
                }

                $next = LoyaltyCard::query()->where('participant_id', $card->participant_id)->latest('id')->first();
                if ($next && $next->id !== $card->id && $next->stamps()->count() === 0) {
                    $next->delete();
                    $card->update(['status' => LoyaltyCard::ACTIVE, 'completed_at' => null]);
                }
            }
        });
    }

    /**
     * Ajustare manuală (+N adaugă, -N scoate ultimele N ștampile valide de pe cardul activ), motiv obligatoriu.
     * Folosită și pentru migrarea ștampilelor unui card fizic după înrolare (adminul le adaugă când vede cardul).
     */
    public static function adjustStamps(Participant $participant, int $delta, string $reason, ?int $adminId = null): void
    {
        $reason = trim($reason);
        if ($delta === 0 || abs($delta) > self::MAX_ADJUST) {
            throw new DomainException('Ajustarea trebuie să fie diferită de 0 și cel mult '.self::MAX_ADJUST.' ștampile.');
        }
        if ($reason === '') {
            throw new DomainException('Spune de ce ajustezi ștampilele (motiv obligatoriu).');
        }
        if (! $participant->isLoyaltyEnrolled()) {
            throw new DomainException('Participantul nu e înrolat la fidelitate. Înrolează-l mai întâi.');
        }

        DB::transaction(function () use ($participant, $delta, $reason, $adminId) {
            if ($delta > 0) {
                $card = self::activeCard($participant) ?? self::startNextCard($participant);
                $batch = (string) Str::uuid();
                for ($i = 0; $i < $delta; $i++) {
                    $card = self::addStamp($card, LoyaltyStamp::SOURCE_MANUAL_ADJUST, $adminId, now(), reason: $reason, batch: $batch);
                }

                return;
            }

            $card = self::activeCard($participant)
                ?? throw new DomainException('Nu există niciun card activ de pe care să scoți ștampile (ultimul e deja arhivat).');

            $toRemove = abs($delta);
            $active = $card->stamps()->whereNull('voided_at')->orderByDesc('stamped_at')->orderByDesc('id')->limit($toRemove)->get();

            if ($active->count() < $toRemove) {
                throw new DomainException('Cardul activ are doar '.$active->count().' ștampile — nu poți scoate '.$toRemove.'.');
            }

            foreach ($active as $stamp) {
                $stamp->update(['voided_at' => now(), 'void_reason' => Str::limit($reason, 255, '')]);
            }
        });

        ActivityLogger::log('loyalty.stamps_adjusted', sprintf(
            'A ajustat ștampilele de fidelitate ale lui %s cu %+d: %s.',
            $participant->label(),
            $delta,
            $reason
        ));
    }

    // ---- Intern ----------------------------------------------------------

    /**
     * Adaugă o ștampilă pe card; dacă îl completează, îl arhivează și creează următorul. Întoarce cardul pe
     * care s-a scris efectiv (poate diferi de $card, dacă intra deja completat — nu ar trebui să se întâmple,
     * dar rămâne sigur pentru ajustări în buclă).
     */
    private static function addStamp(LoyaltyCard $card, string $source, ?int $adminId, Carbon $at, ?int $partyEntryId = null, ?string $reason = null, ?string $batch = null): LoyaltyCard
    {
        if ($card->activeStampsCount() >= $card->totalCircles()) {
            $card = self::startNextCard($card->participant);
        }

        LoyaltyStamp::create([
            'loyalty_card_id' => $card->id,
            'source' => $source,
            'batch' => $batch,
            'stamped_at' => $at,
            'party_entry_id' => $partyEntryId,
            'reason' => $reason !== null ? Str::limit($reason, 255, '') : null,
            'created_by' => $adminId,
        ]);

        $card->refresh();
        if ($card->activeStampsCount() >= $card->totalCircles()) {
            $card->update(['status' => LoyaltyCard::COMPLETED, 'completed_at' => now()]);
            self::startNextCard($card->participant);
        }

        return LoyaltyCard::query()->where('participant_id', $card->participant_id)->where('status', LoyaltyCard::ACTIVE)->latest('id')->firstOrFail();
    }

    private static function startNextCard(Participant $participant): LoyaltyCard
    {
        return LoyaltyCard::create([
            'participant_id' => $participant->id,
            'stamps_required' => self::stampsRequired(),
            'status' => LoyaltyCard::ACTIVE,
        ]);
    }
}
