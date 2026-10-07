<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\Participant;
use App\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * DXA: adaugat (runda 52). Participantul își șterge singur contul din aplicație. Ștergerea = anonimizare (ParticipantRegistry::anonymize):
 * numele, telefonul, parola, poza și petrecerile salvate dispar; intrările și consumațiile rămân anonime (statistici); telefonul se eliberează
 * și se poate înregistra din nou. Creditele rămase se pierd (ajustare de sistem, fără refund automat); biletele valabile rămân valabile, anonime.
 */
class AccountDeletion
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Ce pierde participantul, cu cifre reale (afișate înainte de confirmare).
     *
     * @return array{balance: float, tickets: int, loyalty_stamps: int|null, loyalty_required: int|null}
     */
    public static function summary(Participant $participant): array
    {
        $card = LoyaltyLedger::activeCard($participant);

        return [
            'balance' => round(CreditLedger::balance($participant), 2),
            'tickets' => self::upcomingTickets($participant),
            'loyalty_stamps' => $card ? $card->stamps()->whereNull('voided_at')->count() : null,
            'loyalty_required' => $card?->stamps_required,
        ];
    }

    /** Biletele valabile ale participantului însuși (pe numele lui sau fără nume), la petreceri neîncheiate; biletele date altcuiva nu intră. */
    public static function upcomingTickets(Participant $participant): int
    {
        return self::upcomingTicketsQuery($participant)->count();
    }

    private static function upcomingTicketsQuery(Participant $participant)
    {
        return Ticket::query()
            ->where('status', Ticket::VALID)
            ->where(fn ($q) => $q->where('holder_participant_id', $participant->id)
                ->orWhere(fn ($w) => $w->where('owner_participant_id', $participant->id)->whereNull('holder_participant_id')->whereNull('holder_phone')))   // nu și biletele date altui număr (holder_phone) sau altui cont
            ->whereHas('party', fn ($q) => $q->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())));
    }

    /** @throws DomainException parolă greșită / prea multe încercări / cont deja șters */
    public static function delete(Participant $participant, string $password, ?SmsSender $sms = null): void
    {
        $key = 'delete-account:'.$participant->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new DomainException('Prea multe încercări. Mai încearcă peste '.ceil(RateLimiter::availableIn($key) / 60).' minute.');
        }
        if ($participant->isAnonymized()) {
            throw new DomainException('Contul a fost deja șters.');
        }
        if ($password === '' || ! Hash::check($password, (string) $participant->password)) {
            RateLimiter::hit($key, 3600);
            throw new DomainException('Parola nu este corectă.');
        }
        RateLimiter::clear($key);

        $phone = (string) $participant->phone;
        $ticketIds = self::upcomingTicketsQuery($participant)->pluck('tickets.id')->all();

        DB::transaction(function () use ($participant) {
            CreditLedger::forfeit($participant, 'cont șters de participant');
            ParticipantRegistry::anonymize($participant, 'participants.self_deleted', 'Participantul și-a șters singur contul');   // biletele valabile rămân fără deținător
        });

        // Un singur SMS, cu un link către o pagină cu toate biletele rămase (runda 53). Numărul NU rămâne legat de bilete (linkul e biletele).
        if ($ticketIds !== []) {
            $sms ??= app(SmsSender::class);
            $n = count($ticketIds);

            try {
                $sms->send($phone, 'DXA: contul tău a fost șters, dar '.($n === 1 ? 'biletul tău rămâne valabil' : 'cele '.$n.' bilete ale tale rămân valabile').'. Le vezi aici: '.route('app.deleted-tickets', ['code' => self::ticketsCode($participant)]));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Codul din linkul SMS al unui cont șters: 8 caractere din uuid + 8 din semnătura HMAC (/c/<cod>). */
    public static function ticketsCode(Participant $participant): string
    {
        return substr($participant->uuid, 0, 8).substr(hash_hmac('sha256', $participant->uuid.'|deleted-tickets', (string) config('app.key')), 0, 8);
    }

    /** Contul șters al codului, sau null. */
    public static function findByTicketsCode(string $code): ?Participant
    {
        if (! preg_match('/^[0-9a-f]{16}$/', $code)) {
            return null;
        }

        return Participant::query()->whereNotNull('anonymized_at')->where('uuid', 'like', substr($code, 0, 8).'%')->get()
            ->first(fn (Participant $p) => hash_equals(self::ticketsCode($p), $code));
    }

    /** Biletele valabile rămase pe un cont șters (le arată pagina din SMS). */
    public static function remainingTickets(Participant $participant)
    {
        return self::upcomingTicketsQuery($participant)->with('party')->orderBy('party_id')->orderBy('id')->get();
    }
}
