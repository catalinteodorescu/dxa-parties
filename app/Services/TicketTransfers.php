<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\Participant;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Support\ParticipantAppSettings;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * DXA: adaugat (runda 39). „Trimite biletul”: biletul își schimbă deținătorul (owner + holder), restul rămâne neschimbat
 * (comandă, preț, reducere, cod, combo, QR). Nu se recalculează nimic: treptele, stocul și codurile nu sunt atinse.
 *
 *  - Cine poate trimite: contul în care e biletul acum (`owner_participant_id`), cât biletul e valabil și petrecerea nu s-a încheiat.
 *    Deținătorul nou poate trimite mai departe (cumpărătorul păstrează biletul în istoric, la „Trimise de mine”). Nu există retragere / anulare de transfer.
 *  - Către un cont existent: biletul apare direct în contul lui.
 *  - Către un telefon fără cont: biletul se leagă de participantul acelui telefon (creat acum, fără cont, dacă nu există; cel
 *    din Recepție se refolosește) și destinatarul primește un SMS cu un link semnat către bilet. Când își face cont cu acel
 *    telefon, contul se leagă de același participant (vezi ParticipantAccounts::verifyRegistration), deci biletul e deja al lui.
 *  - Cel mult 10 trimiteri pe oră per cont. Eșecurile sunt DomainException cu mesaj gata de afișat.
 */
class TicketTransfers
{
    public const MAX_PER_HOUR = 10;

    public static function canSend(Ticket $ticket, Participant $me): bool
    {
        // Runda 55: se trimite orice bilet aflat în contul meu (liber, pe numele meu sau primit de la altcineva).
        return (int) $ticket->owner_participant_id === (int) $me->id
            && $ticket->status === Ticket::VALID
            && $ticket->party?->state() !== 'past';
    }

    /**
     * Cine primește biletul (pentru ecranul de confirmare): telefonul normalizat și participantul existent, dacă e cazul.
     *
     * @return array{phone: string, participant: ?Participant, has_account: bool}
     */
    public static function recipient(string $phone, Participant $from): array
    {
        $normalized = Phone::normalize($phone) ?? throw new DomainException('Telefonul nu pare valid. Scrie-l ca 07XXXXXXXX.');
        if ($normalized === $from->phone) {
            throw new DomainException('Acesta e numărul tău. Scrie telefonul persoanei căreia îi trimiți biletul.');
        }

        $participant = Participant::query()->where('phone', $normalized)->first();
        if ($participant?->isAnonymized()) {
            throw new DomainException('Acest număr nu poate primi bilete.');
        }

        return ['phone' => $normalized, 'participant' => $participant, 'has_account' => (bool) $participant?->hasAccount()];
    }

    /**
     * @return array{transfer: TicketTransfer, recipient: Participant, has_account: bool, sms: bool}
     */
    public static function send(int $ticketId, Participant $from, string $phone, SmsSender $sms): array
    {
        $key = 'ticket-transfer:'.$from->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw new DomainException('Ai trimis prea multe bilete într-o oră. Încearcă din nou mai târziu.');
        }

        $info = self::recipient($phone, $from);

        [$ticket, $transfer, $to] = DB::transaction(function () use ($ticketId, $from, $info) {
            $ticket = Ticket::query()->whereKey($ticketId)->lockForUpdate()->first();
            if (! $ticket || ! self::canSend($ticket->load('party'), $from)) {
                throw new DomainException('Biletul nu mai poate fi trimis (e folosit, anulat, expirat sau nu mai e în contul tău).');
            }

            $to = Participant::query()->where('phone', $info['phone'])->lockForUpdate()->first();
            if ($to?->isAnonymized()) {
                throw new DomainException('Acest număr nu poate primi bilete.');
            }
            $to ??= Participant::create(['name' => $info['phone'], 'phone' => $info['phone'], 'source' => 'transfer']);
            $hasAccount = $to->hasAccount();

            $ticket->forceFill([
                'owner_participant_id' => $to->id,
                'holder_participant_id' => $to->id,
                'holder_phone' => $hasAccount ? null : $to->phone,
            ])->save();

            $transfer = TicketTransfer::create([
                'ticket_id' => $ticket->id,
                'from_participant_id' => $from->id,
                'to_participant_id' => $to->id,
                'to_phone' => $to->phone,
                'to_had_account' => $hasAccount,
            ]);

            ActivityLogger::log('tickets.transferred', sprintf(
                '%s a trimis biletul „%s” la „%s” către %s (%s)%s.',
                $from->name, $ticket->ticket_type, $ticket->party?->name ?? 'petrecere', $to->name, $to->phone, $hasAccount ? '' : ', fără cont (SMS cu link)'
            ), actor: null);

            return [$ticket, $transfer, $to];
        });

        RateLimiter::hit($key, 3600);

        $hasAccount = $to->hasAccount();
        $sent = false;
        if (! $hasAccount) {
            try {
                $sms->send($to->phone, ParticipantAppSettings::smsTicket(self::linkFor($ticket), $from->name, (string) $ticket->party?->name));
                $sent = true;
            } catch (Throwable $e) {
                report($e);
            }
            $transfer->forceFill(['sms_sent' => $sent])->save();
        }

        return ['transfer' => $transfer, 'recipient' => $to, 'has_account' => $hasAccount, 'sms' => $sent];
    }

    /** Linkul scurt din SMS (/b/<cod>): se stinge când biletul își schimbă deținătorul (codul depinde de el). */
    public static function linkFor(Ticket $ticket): string
    {
        return route('app.ticket-short', ['code' => self::shortCode($ticket)]);
    }

    /** 8 caractere din uuid (pentru căutare) + 8 din semnătura HMAC (autenticitate, legată de deținător). */
    public static function shortCode(Ticket $ticket): string
    {
        return substr($ticket->uuid, 0, 8).substr(hash_hmac('sha256', $ticket->uuid.'|'.(int) $ticket->holder_participant_id.'|s', (string) config('app.key')), 0, 8);
    }

    public static function findByShortCode(string $code): ?Ticket
    {
        if (! preg_match('/^[0-9a-f]{16}$/', $code)) {
            return null;
        }

        return Ticket::query()->where('uuid', 'like', substr($code, 0, 8).'%')->get()
            ->first(fn (Ticket $t) => hash_equals(self::shortCode($t), $code));
    }

    /** Linkul semnat lung (vechi, rămas valabil pentru SMS-urile deja trimise). */
    public static function signedLinkFor(Ticket $ticket): string
    {
        return URL::signedRoute('app.ticket-link', ['ticket' => $ticket->uuid, 'v' => self::stamp($ticket)]);
    }

    public static function linkValid(Ticket $ticket, string $stamp): bool
    {
        return hash_equals(self::stamp($ticket), $stamp);
    }

    private static function stamp(Ticket $ticket): string
    {
        return substr(hash_hmac('sha256', $ticket->uuid.'|'.(int) $ticket->holder_participant_id, (string) config('app.key')), 0, 12);
    }
}
