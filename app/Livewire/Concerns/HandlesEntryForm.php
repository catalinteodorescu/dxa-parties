<?php

namespace App\Livewire\Concerns;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Participant;
use App\Models\ReceptionSession;
use App\Services\EntryRecorder;
use App\Services\LoyaltyLedger;
use App\Support\PaymentMethods;
use App\Support\RecentEntryParticipants;
use DomainException;
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
    use PicksParticipants;

    public string $ticket = '';

    public int|string $count = 1;

    public bool $override = false;

    public string $overridePrice = '';

    public string $overrideReason = '';

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
        $min = max(1, count($this->participantIds));
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

        return $party && $this->ticket !== '' ? EntryRecorder::quote($party, $this->ticket) : null;
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
        return $this->unitCents() * max(1, min(EntryRecorder::MAX_GROUP, (int) $this->count));
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
