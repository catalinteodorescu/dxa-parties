<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (runda 43). Evidența persoanelor la o petrecere, doar cele identificabile:
 *  - după cont/participant (bilete în care e deținător + intrări înregistrate pe el);
 *  - după telefon, pentru biletele trimise unui număr fără cont (încă fără participant).
 * Intrările fără persoană identificată și biletele fără deținător identificat nu apar pe rânduri, ci în sumar.
 * Biletele anulate (`void`) și intrările anulate nu se numără.
 */
class PartyAttendees
{
    public const ENTERED = 'entered';     // are cel puțin o intrare valabilă

    public const WAITING = 'waiting';     // are bilete valabile și nicio intrare

    public const PHONE = 'phone';         // doar telefon (fără cont), cu bilet

    /**
     * @return object{rows: Collection<int, object>, summary: object}
     */
    public static function for(Party $party): object
    {
        $tickets = Ticket::query()->where('party_id', $party->id)->counted()
            ->get(['id', 'holder_participant_id', 'holder_phone', 'status']);
        $entries = PartyEntry::query()->where('party_id', $party->id)->active()->get(['id', 'participant_id']);

        $people = [];   // cheie => [participant_id, phone, tickets, valid, used, entries]
        $blank = fn (?int $pid, ?string $phone) => ['participant_id' => $pid, 'phone' => $phone, 'tickets' => 0, 'valid' => 0, 'used' => 0, 'entries' => 0];

        $unnamedTickets = 0;
        foreach ($tickets as $t) {
            if ($t->holder_participant_id) {
                $k = 'p'.$t->holder_participant_id;
                $people[$k] ??= $blank((int) $t->holder_participant_id, null);
            } elseif ($t->holder_phone) {
                $k = 't'.$t->holder_phone;
                $people[$k] ??= $blank(null, $t->holder_phone);
            } else {
                $unnamedTickets++;

                continue;
            }
            $people[$k]['tickets']++;
            $t->status === Ticket::USED ? $people[$k]['used']++ : $people[$k]['valid']++;
        }

        $anonymousEntries = 0;
        foreach ($entries as $e) {
            if (! $e->participant_id) {
                $anonymousEntries++;

                continue;
            }
            $k = 'p'.$e->participant_id;
            $people[$k] ??= $blank((int) $e->participant_id, null);
            $people[$k]['entries']++;
        }

        $participants = Participant::query()->whereIn('id', collect($people)->pluck('participant_id')->filter()->all())->get()->keyBy('id');

        $rows = collect($people)->map(function (array $r) use ($participants) {
            $p = $r['participant_id'] ? $participants->get($r['participant_id']) : null;
            $status = $r['entries'] > 0 ? self::ENTERED : ($r['participant_id'] === null ? self::PHONE : self::WAITING);

            return (object) [
                'participant_id' => $r['participant_id'],
                'name' => $p?->name ?? 'Fără cont',
                'phone' => $p ? $p->phone : $r['phone'],
                'has_account' => (bool) $p?->hasAccount(),
                'tickets' => $r['tickets'],
                'valid' => $r['valid'],
                'used' => $r['used'],
                'entries' => $r['entries'],
                'status' => $status,
            ];
        })->sortBy(fn ($r) => mb_strtolower($r->name.'|'.$r->phone))->values();

        return (object) [
            'rows' => $rows,
            'summary' => (object) [
                'entries' => $entries->count(),
                'identified_entries' => $entries->count() - $anonymousEntries,
                'anonymous_entries' => $anonymousEntries,
                'people_entered' => $rows->where('status', self::ENTERED)->count(),
                'people_waiting' => $rows->whereIn('status', [self::WAITING, self::PHONE])->count(),
                'tickets_valid' => $tickets->where('status', Ticket::VALID)->count(),
                'unnamed_tickets' => $unnamedTickets,
            ],
        ];
    }

    /** Filtru + căutare (după nume sau telefon, fără diacritice/majuscule). */
    public static function filter(Collection $rows, string $status, string $search): Collection
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/[\x{0300}-\x{036f}]/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D) ?: $s));
        $needle = $norm(trim($search));
        $digits = preg_replace('/\D+/', '', $search);

        return $rows
            ->when($status !== '', fn ($c) => $c->where('status', $status))
            ->when($needle !== '', fn ($c) => $c->filter(fn ($r) => str_contains($norm((string) $r->name), $needle)
                || ($digits !== '' && str_contains(preg_replace('/\D+/', '', (string) $r->phone), $digits))))
            ->values();
    }
}
