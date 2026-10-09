<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (runda 47 - Scanner Recepție). Ce se întâmplă când recepționerul scanează un cod QR de pe ecranul principal:
 *  - bilet (`DXA:T:`) valid, fără nimic de încasat → intrarea se înregistrează direct (EntryRecorder);
 *  - bilet cu sumă de încasat (comandă „la intrare", termen depășit) → se deschide formularul „Intrare” cu biletul încărcat;
 *  - QR personal (`DXA:P:`) → biletele valabile ale persoanei: unul = ca la bilet, mai multe = alegere, niciunul = formularul cu persoana aleasă.
 * Rezultatul e mereu o listă simplă (status: entered | choose | open | error), ca ecranul să nu conțină logică de business.
 */
class ReceptionScan
{
    public const ENTERED = 'entered';

    public const CHOOSE = 'choose';

    public const OPEN = 'open';

    public const ERROR = 'error';

    /**
     * @return array{status: string, message: string, tickets?: array<int, array{id: int, label: string, due: float}>, query?: array<string, string>}
     */
    public static function resolve(Party $party, string $code, ?int $adminId): array
    {
        $code = trim($code);

        if (str_starts_with($code, Ticket::QR_PREFIX)) {
            $ticket = Ticket::findByQrPayload($code);
            if (! $ticket) {
                return self::error('Bilet necunoscut. Încearcă din nou.');
            }
            if ((int) $ticket->party_id !== (int) $party->id) {
                return self::error('Biletul e pentru altă petrecere.');
            }
            if ($ticket->status !== Ticket::VALID) {
                return self::error(match ($ticket->status) {
                    Ticket::USED => 'Biletul a fost deja folosit'.($ticket->used_at ? ' (la '.$ticket->used_at->format('H:i').')' : '').'.',
                    Ticket::PENDING => 'Biletul nu e plătit încă (așteaptă plata cu cardul).',
                    default => 'Biletul a fost anulat.',
                });
            }

            return self::enter($party, collect([$ticket]), $adminId);
        }

        $participant = Participant::findByQrPayload($code);
        if (! $participant) {
            return self::error('Cod necunoscut. Încearcă din nou.');
        }

        $tickets = self::validTicketsFor($party, $participant);

        if ($tickets->isEmpty()) {
            return [
                'status' => self::OPEN,
                'message' => 'Persoana nu are bilete online la această petrecere.',
                'query' => ['participant' => (string) $participant->id],
            ];
        }

        if ($tickets->count() === 1) {
            return self::enter($party, $tickets, $adminId, $participant);
        }

        return [
            'status' => self::CHOOSE,
            'message' => 'Persoana are '.$tickets->count().' bilete. Alege cele care intră acum.',
            // Fără nume pe ecranul de scanare: biletele se deosebesc prin număr (și suma de încasat, dacă are).
            'tickets' => $tickets->values()->map(fn (Ticket $t, int $i) => [
                'id' => $t->id,
                'label' => $t->ticket_type.' · nr. '.($i + 1),
                'due' => EntryRecorder::ticketDue($t)['due'] / 100,
            ])->values()->all(),
            'participant' => (string) $participant->id,
        ];
    }

    /**
     * Intrarea biletelor alese (din alegerea de după QR-ul personal). Biletele se reîncarcă din baza de date și se verifică
     * (petrecerea curentă, valabile): ce vine de la client nu e de încredere.
     *
     * @param  array<int, mixed>  $ids
     * @return array{status: string, message: string, query?: array<string, string>}
     */
    public static function enterTickets(Party $party, array $ids, ?int $adminId): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, EntryRecorder::MAX_GROUP);
        if ($ids === []) {
            return self::error('Alege cel puțin un bilet.');
        }

        $tickets = Ticket::query()->with(['order', 'party', 'holder'])->where('party_id', $party->id)
            ->where('status', Ticket::VALID)->whereIn('id', $ids)->orderBy('id')->get();

        if ($tickets->count() !== count($ids)) {
            return self::error('Un bilet nu mai e valabil (folosit sau anulat). Scanează din nou.');
        }

        return self::enter($party, $tickets, $adminId);
    }

    /** Biletele valabile ale participantului la petrecere: cumpărate de el, pe numele lui sau pe telefonul lui. */
    public static function validTicketsFor(Party $party, Participant $participant): Collection
    {
        $phone = Phone::normalize($participant->phone);

        return Ticket::query()->with(['order', 'party', 'holder'])->where('party_id', $party->id)->where('status', Ticket::VALID)
            ->where(function ($q) use ($participant, $phone) {
                $q->where('owner_participant_id', $participant->id)->orWhere('holder_participant_id', $participant->id);
                if ($phone) {
                    $q->orWhere('holder_phone', $phone);
                }
            })
            ->orderBy('id')->get();
    }

    /** Înregistrează direct dacă nu e nimic de încasat; altfel trimite la formular, unde se alege plata. */
    private static function enter(Party $party, Collection $tickets, ?int $adminId, ?Participant $participant = null): array
    {
        $tickets->each(fn (Ticket $t) => $t->loadMissing(['order', 'party', 'holder']));

        if ($tickets->contains(fn (Ticket $t) => EntryRecorder::ticketDue($t)['due'] > 0)) {
            $query = ['bilete' => $tickets->pluck('id')->implode(',')];
            if ($participant) {
                $query['participant'] = (string) $participant->id;
            }

            return ['status' => self::OPEN, 'message' => 'Are de încasat la intrare. Alege plata.', 'query' => $query];
        }

        try {
            EntryRecorder::record(
                $party,
                (string) $tickets->first()->ticket_type,
                $tickets->count(),
                [],
                null,
                null,
                $adminId,
                ticketIds: $tickets->pluck('id')->all(),
            );
        } catch (DomainException $e) {
            return self::error($e->getMessage());
        }

        // Fără nume pe ecranul de scanare (intrarea se vede în „Tranzacții”).
        return [
            'status' => self::ENTERED,
            'message' => $tickets->count() === 1 ? 'Intrare înregistrată' : $tickets->count().' intrări înregistrate',
        ];
    }

    private static function error(string $message): array
    {
        return ['status' => self::ERROR, 'message' => $message];
    }
}
