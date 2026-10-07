<?php

namespace App\Livewire\Concerns;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Participant;
use App\Models\ReceptionSession;
use App\Models\Ticket;
use App\Services\EntryRecorder;
use App\Services\LoyaltyLedger;
use App\Services\ReceptionScan;
use App\Support\PaymentMethods;
use App\Support\RecentEntryParticipants;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * DXA: adaugat (PWA Recepție - Etapa 2). Logica formularului „Intrare nouă”, comună ecranului din admin
 * (App\Livewire\Admin\Reception\Form) și celui din PWA (App\Livewire\Reception\Entry): tip de bilet, număr de
 * persoane, participanți, plata pe total (poate fi mixtă), prețul suprascris (cu motiv) și intrarea gratis de fidelitate.
 * Toată logica de business stă în App\Services\EntryRecorder; aici doar starea formularului.
 *
 * Componenta gazdă trebuie să definească currentParty(): ?Party. Poate suprascrie canOverridePrice().
 */
trait HandlesEntryForm
{
    use GuardsResubmit;
    use PicksParticipants {
        // DXA: adaugat (runda 17). addParticipant din trait rămâne pentru bar/tokeni; aici îl extindem cu biletele.
        addParticipant as protected pickParticipant;
    }

    public string $ticket = '';

    public int|string $count = 1;

    public bool $override = false;

    public string $overridePrice = '';

    public string $overrideReason = '';

    /** DXA: adaugat (runda 15). Biletele online scanate la intrare (ordinea = a rândurilor; fiecare acoperă o persoană). @var array<int, int> */
    public array $ticketIds = [];

    /** @var array<int, array{method: string, amount: string}> plata pe TOTALUL grupului */
    public array $payments = [['method' => 'cash', 'amount' => '']];

    /** Mesajele formularului „Intrare nouă” (afișate în card, lângă buton). */
    public ?string $message = null;

    public ?string $error = null;

    /** Confirmarea de după o intrare înregistrată (ecran plin în PWA): count, ticket, total (lei, text), note. */
    public ?array $done = null;

    public function dismissDone(): void
    {
        $this->done = null;
    }

    /** Poate utilizatorul să schimbe prețul unei intrări? (În PWA: doar adminii.) */
    protected function canOverridePrice(): bool
    {
        return true;
    }

    protected function participantLimit(): int
    {
        return EntryRecorder::MAX_GROUP;
    }

    /** Dacă sunt mai mulți participanți decât persoane, crește numărul de persoane. */
    protected function participantsChanged(): void
    {
        if (count($this->participantIds) > (int) $this->count) {
            $this->count = count($this->participantIds);
        }
    }

    public function updatedOverride(): void
    {
        $this->overrideReason = '';
        $this->overridePrice = $this->override ? $this->fmt($this->quote()?->price ?? 0) : '';
    }

    public function updatedTicket(): void
    {
        if ($this->override) {
            $this->overridePrice = $this->fmt($this->quote()?->price ?? 0);
        }
    }

    public function selectTicket(string $name): void
    {
        $this->ticket = $name;
        $this->updatedTicket();
    }

    public function stepCount(int $delta): void
    {
        // Nu coborî sub numărul de participanți deja aleși.
        $min = max(1, count($this->participantIds), count($this->ticketIds));
        $this->count = max($min, min(EntryRecorder::MAX_GROUP, (int) $this->count + $delta));
    }

    /** „Restul”: completează în rândul $i suma rămasă de încasat (ca la bar). */
    public function fillRemaining(int $i): void
    {
        $others = 0;
        foreach ($this->payments as $k => $p) {
            if ($k === $i) {
                continue;
            }
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $others += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }

        $remaining = max(0, $this->totalCents() - $others);
        $this->payments[$i]['amount'] = $remaining > 0 ? $this->fmt($remaining / 100) : '';
    }

    public function addPayment(): void
    {
        $this->payments[] = ['method' => array_key_first($this->entryMethods()) ?? 'cash', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['method' => 'cash', 'amount' => '']];
    }

    /** „Tot cu <metodă>”: o singură plată egală cu totalul. */
    public function payAll(string $method): void
    {
        $this->payments = [['method' => $method, 'amount' => $this->fmt($this->totalCents() / 100)]];
    }

    /**
     * DXA: adaugat (runda 15). Scanarea la Recepție: QR de bilet (DXA:T:) sau QR personal (DXA:P:).
     *  - bilet: se adaugă biletul (dacă e valabil și al acestei petreceri) și deținătorul lui devine participant;
     *  - personal: se alege participantul și se adaugă biletele lui valabile la petrecere (cumpărate de el sau pe numele lui).
     * Numărul de persoane crește ca să acopere biletele. Nu înregistrează nimic.
     *
     * @return array{ok: bool, message: string}
     */
    public function scanParticipant(string $code): array
    {
        $this->participantError = null;
        $party = $this->currentParty();
        $code = trim($code);

        if (str_starts_with($code, Ticket::QR_PREFIX)) {
            $ticket = Ticket::findByQrPayload($code);
            if (! $ticket) {
                return ['ok' => false, 'message' => 'Bilet necunoscut. Încearcă din nou.'];
            }
            if (! $party || (int) $ticket->party_id !== (int) $party->id) {
                return ['ok' => false, 'message' => 'Biletul e pentru altă petrecere.'];
            }
            if ($ticket->status !== Ticket::VALID) {
                return ['ok' => false, 'message' => $ticket->status === Ticket::USED ? 'Biletul a fost deja folosit.' : 'Biletul a fost anulat.'];
            }

            // Același bilet scanat a doua oară: nu se adaugă nimic, deci nu e „succes”.
            if (in_array($ticket->id, $this->ticketIds, true)) {
                return ['ok' => false, 'message' => 'Biletul e deja adăugat.'];
            }

            $this->addTickets(collect([$ticket]));

            return $this->participantError
                ? ['ok' => false, 'message' => (string) tap($this->participantError, fn () => $this->participantError = null)]
                : ['ok' => true, 'message' => $ticket->holder?->name ?? 'Bilet '.$ticket->ticket_type];
        }

        $participant = Participant::findByQrPayload($code);
        if (! $participant) {
            return ['ok' => false, 'message' => 'Cod necunoscut. Încearcă din nou.'];
        }

        $tickets = $this->validTicketsFor($participant);

        // Același cod scanat a doua oară (participantul e deja în listă și nu are bilete noi de adăugat): nu e „succes”.
        $newTickets = $tickets->reject(fn (Ticket $t) => in_array($t->id, $this->ticketIds, true));
        if (in_array($participant->id, $this->participantIds, true) && $newTickets->isEmpty()) {
            return ['ok' => false, 'message' => 'Participantul e deja adăugat.'];
        }

        if ($tickets->isNotEmpty()) {
            $this->addTickets($tickets);
            $this->pickParticipant($participant->id);
        } else {
            $this->pickParticipant($participant->id);
        }

        if ($this->participantError) {
            $message = $this->participantError;
            $this->participantError = null;

            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => true, 'message' => $participant->name.($tickets->isNotEmpty() ? ' · '.$tickets->count().' '.($tickets->count() === 1 ? 'bilet' : 'bilete') : '')];
    }

    /**
     * DXA: adaugat (runda 17). Alegerea manuală (căutare) încarcă și biletele participantului, ca scanarea QR personal.
     * Restul aplicațiilor care folosesc picker-ul (bar, tokeni) păstrează comportamentul vechi din trait.
     */
    public function addParticipant(int $id): void
    {
        $alreadyPicked = in_array($id, $this->participantIds, true);
        $this->pickParticipant($id);

        if ($alreadyPicked || $this->participantError || ! in_array($id, $this->participantIds, true)) {
            return;
        }

        $participant = Participant::find($id);
        $tickets = $participant ? $this->validTicketsFor($participant) : collect();
        if ($tickets->isNotEmpty()) {
            $this->addTickets($tickets);
        }
    }

    /**
     * Biletele valabile ale participantului la petrecerea curentă: cumpărate de el, pe numele lui sau pe telefonul lui
     * (bilete luate de un prieten înainte ca el să aibă cont au doar holder_phone).
     *
     * @return Collection<int, Ticket>
     */
    protected function validTicketsFor(Participant $participant): Collection
    {
        $party = $this->currentParty();

        return $party ? ReceptionScan::validTicketsFor($party, $participant) : collect();
    }

    /** Adaugă biletele (fără dubluri) și deținătorii lor cu nume ca participanți; ridică numărul de persoane la nevoie. */
    protected function addTickets(Collection $tickets): void
    {
        foreach ($tickets as $t) {
            if (in_array($t->id, $this->ticketIds, true)) {
                continue;
            }
            if ($t->holder_participant_id) {
                $this->pickParticipant((int) $t->holder_participant_id);
                if ($this->participantError) {
                    return;
                }
            }
            $this->ticketIds[] = $t->id;
        }

        if ((int) $this->count < count($this->ticketIds)) {
            $this->count = count($this->ticketIds);
        }
    }

    public function removeTicket(int $id): void
    {
        $this->ticketIds = array_values(array_filter($this->ticketIds, fn ($t) => $t !== $id));
    }

    /**
     * Biletele scanate, cu ce se încasează pentru fiecare (folosit de partialul livewire.partials.entry-tickets).
     *
     * @return Collection<int, array{ticket: Ticket, holder: string, due: float, expired: bool, current: ?float}>
     */
    public function ticketRows(): Collection
    {
        if ($this->ticketIds === []) {
            return collect();
        }

        $found = Ticket::query()->with(['holder', 'order', 'party'])->whereIn('id', $this->ticketIds)->get()->keyBy('id');

        return collect($this->ticketIds)->map(fn ($id) => $found->get($id))->filter(fn ($t) => $t && $t->status === Ticket::VALID)
            ->map(function (Ticket $t) {
                $d = EntryRecorder::ticketDue($t);

                return ['ticket' => $t, 'holder' => $t->holder?->name ?? ('Fără nume'.($t->holder_phone ? ' · '.$t->holder_phone : '')), 'due' => $d['due'] / 100, 'expired' => $d['expired'], 'current' => $d['current']];
            })->values();
    }

    /** Textul rândului „Total": la biletele online, împărțit pe bilete și persoane la intrare. */
    public function totalLabel(): string
    {
        $n = max(1, (int) $this->count);
        $t = $this->ticketRows()->count();
        $unit = number_format($this->unitCents() / 100, 2, ',', '.');

        return $t === 0 ? 'Total ('.$n.' × '.$unit.' lei)' : 'Total ('.$t.' '.($t === 1 ? 'bilet' : 'bilete').' online'.($n > $t ? ' + '.($n - $t).' × '.$unit.' lei' : '').')';
    }

    public function save(): void
    {
        $this->message = $this->error = null;
        $this->participantError = null;
        $party = $this->currentParty();

        try {
            if (! $party) {
                throw new DomainException('Alege o petrecere.');
            }

            if ($this->override && ! $this->canOverridePrice()) {
                throw new DomainException('Doar un admin poate schimba prețul.');
            }

            $override = null;
            if ($this->override && trim($this->overridePrice) !== '') {
                $raw = str_replace(',', '.', trim($this->overridePrice));
                if (! is_numeric($raw)) {
                    throw new DomainException('Prețul trebuie să fie un număr.');
                }
                $override = (float) $raw;
            }

            $hadSession = ReceptionSession::currentFor($party->id) !== null;

            $entries = $this->once(fn () => EntryRecorder::record(
                $party,
                $this->ticket,
                (int) $this->count,
                $this->payments,
                $override,
                $this->overrideReason,
                Auth::guard('admin')->id(),
                participants: $this->participantIds,
                ticketIds: $this->ticketIds,
            ));

            $this->message = sprintf(
                'Înregistrat: %d × %s — %s lei.',
                $entries->count(),
                $this->ticket,
                number_format((float) $entries->sum('price_paid'), 2, ',', '.')
            );
            $this->done = [
                'count' => $entries->count(),
                'ticket' => $this->ticket,
                'total' => number_format((float) $entries->sum('price_paid'), 2, ',', '.'),
                'note' => $hadSession ? null : ReceptionSession::openedNotice($party->id),
            ];
            if (! $hadSession) {
                $this->message .= ' '.ReceptionSession::openedNotice($party->id);
            }
            RecentEntryParticipants::put($party->id, $this->participantIds);
            if ($this->participantIds !== []) {
                $this->dispatch('entry-recorded', partyId: $party->id, participantIds: $this->participantIds);
            }
            $this->resetEntryForm();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    protected function resetEntryForm(): void
    {
        $this->count = 1;
        $this->override = false;
        $this->overridePrice = '';
        $this->overrideReason = '';
        $this->payments = [['method' => 'cash', 'amount' => '']];
        $this->ticketIds = [];
        $this->resetParticipants();
    }

    /** @return array<string, string> */
    protected function entryMethods(): array
    {
        $party = $this->currentParty();

        return $party ? EntryRecorder::entryMethods($party) : PaymentMethods::forEntry(null);
    }

    /**
     * DXA: adaugat (Card de fidelitate). Pre-completează plata cu metoda „Beneficiu" pe tot grupul, pentru
     * participantul al cărui card activ e gata de intrare gratis (buton rapid din picker).
     */
    public function applyLoyaltyFreeEntry(int $participantId): void
    {
        $this->message = $this->error = null;
        $party = $this->currentParty();

        if (! $party || ! LoyaltyLedger::partyEligible($party)) {
            $this->error = 'Petrecerea nu acordă fidelitate.';

            return;
        }
        if (! in_array($participantId, $this->participantIds, true)) {
            $this->error = 'Alege mai întâi participantul.';

            return;
        }

        $participant = Participant::find($participantId);
        if (! $participant || ! LoyaltyLedger::readyForFreeEntry($participant)) {
            $this->error = 'Participantul nu are dreptul la intrare gratis chiar acum.';

            return;
        }

        $this->payAll(PaymentMethods::BENEFIT);
    }

    protected function quote(): ?object
    {
        $party = $this->currentParty();

        if (! $party || $this->ticket === '') {
            return null;
        }

        return EntryRecorder::quote($party, $this->ticket);
    }

    protected function unitCents(): int
    {
        $quote = $this->quote();
        $unit = $quote?->price ?? 0.0;

        if ($this->override && trim($this->overridePrice) !== '') {
            $raw = str_replace(',', '.', trim($this->overridePrice));
            $unit = is_numeric($raw) && (float) $raw >= 0 ? (float) $raw : $unit;
        }

        return (int) round($unit * 100);
    }

    protected function totalCents(): int
    {
        $rows = $this->ticketRows();
        $people = max(1, min(EntryRecorder::MAX_GROUP, (int) $this->count));
        $ticketCents = (int) $rows->sum(fn ($r) => (int) round($r['due'] * 100));

        return $ticketCents + $this->unitCents() * max(0, $people - $rows->count());
    }

    protected function fmt(float $n): string
    {
        return $n == floor($n) ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Păstrează un tip de bilet valid pentru petrecerea aleasă. */
    protected function syncTicket(): void
    {
        $names = array_column($this->currentParty()?->entryTicketTypes() ?? [], 'name');

        if (! in_array($this->ticket, $names, true)) {
            $this->ticket = $names[0] ?? '';
        }
    }
}
