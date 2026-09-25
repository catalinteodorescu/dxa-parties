<?php

namespace App\Livewire\Admin\Reception;

use App\Livewire\Admin\Concerns\PicksParticipants;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Models\TokenTransaction;
use App\Services\EntryRecorder;
use App\Services\PartyStats;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * DXA: adaugat (Recepție - intrări). „Intrare nouă” — ecranul recepționerului: alege tipul de bilet și numărul de persoane,
 * introduce plata PE TOTAL (poate fi mixtă), înregistrează. Suprascrierea prețului cere motiv.
 *
 * Toată logica stă în App\Services\EntryRecorder (o va refolosi și PWA-ul); aici doar interfața.
 * Petrecerea se preselectează automat (cea în desfășurare, altfel cea care urmează); se pot alege doar
 * petreceri publicate și neîncheiate.
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    use PicksParticipants;

    public ?int $partyId = null;

    public string $ticket = '';

    public int|string $count = 1;

    public bool $override = false;

    public string $overridePrice = '';

    public string $overrideReason = '';

    /** @var array<int, array{method: string, amount: string}> plata pe TOTALUL grupului */
    public array $payments = [['method' => 'cash', 'amount' => '']];

    /** Mesaje ale formularului „Intrare nouă” (afișate în acel card, lângă buton). */
    public ?string $message = null;

    public ?string $error = null;

    /** Mesaje ale anulărilor din „Ultimele intrări” / „Ultimele vânzări de tokeni” (afișate în cardul listei). */
    public ?string $entryCancelMessage = null;

    public ?string $entryCancelError = null;

    public ?string $tokenCancelMessage = null;

    public ?string $tokenCancelError = null;

    public function mount(): void
    {
        $this->partyId = $this->selectableParties()->first()?->id;
        $this->syncTicket();
    }

    /** Memo pe request (private: nu se serializează în Livewire). */
    private $selectable = null;

    /** Petrecerile la care se pot înregistra intrări: publicate, active, neîncheiate; cea în desfășurare prima. */
    private function selectableParties()
    {
        return $this->selectable ??= Party::query()
            ->where('status', '!=', 'draft')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(30)
            ->get();
    }

    private function currentParty(): ?Party
    {
        return $this->partyId ? $this->selectableParties()->firstWhere('id', $this->partyId) : null;
    }

    /** Păstrează un tip de bilet valid pentru petrecerea aleasă. */
    private function syncTicket(): void
    {
        $names = array_column($this->currentParty()?->entryTicketTypes() ?? [], 'name');

        if (! in_array($this->ticket, $names, true)) {
            $this->ticket = $names[0] ?? '';
        }
    }

    public function updatedPartyId(): void
    {
        $this->message = $this->error = null;
        $this->entryCancelMessage = $this->entryCancelError = $this->tokenCancelMessage = $this->tokenCancelError = null;
        $this->resetEntryForm();
        $this->syncTicket();
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

            $override = null;
            if ($this->override && trim($this->overridePrice) !== '') {
                $raw = str_replace(',', '.', trim($this->overridePrice));
                if (! is_numeric($raw)) {
                    throw new DomainException('Prețul trebuie să fie un număr.');
                }
                $override = (float) $raw;
            }

            $hadSession = ReceptionSession::currentFor($party->id) !== null;

            $entries = EntryRecorder::record(
                $party,
                $this->ticket,
                (int) $this->count,
                $this->payments,
                $override,
                $this->overrideReason,
                Auth::guard('admin')->id(),
                participants: $this->participantIds,
            );

            $this->message = sprintf(
                'Înregistrat: %d × %s — %s lei.',
                $entries->count(),
                $this->ticket,
                number_format((float) $entries->sum('price_paid'), 2, ',', '.')
            );
            if (! $hadSession) {
                $this->message .= ' '.ReceptionSession::openedNotice($party->id);
            }
            if ($this->participantIds !== []) {
                $this->dispatch('entry-recorded', partyId: $party->id, participantIds: $this->participantIds);
            }
            $this->resetEntryForm();
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Vânzare de tokeni făcută în cardul TokenSale (sau anulată aici): reafișează listele din dreapta. */
    #[On('tokens-changed')]
    public function refreshTokens(): void
    {
        // doar re-randare
    }

    /** Anulează o vânzare de tokeni din lista din dreapta (motiv obligatoriu; doar dacă tokenii nu au fost folosiți la bar). */
    public function cancelTokenSale(int $id, string $reason): void
    {
        $this->tokenCancelMessage = $this->tokenCancelError = null;

        try {
            TokenLedger::cancel($id, $reason, Auth::guard('admin')->id());
            $this->tokenCancelMessage = 'Vânzarea de tokeni a fost anulată.';
            $this->dispatch('tokens-changed');
        } catch (DomainException $e) {
            $this->tokenCancelError = $e->getMessage();
        }
    }

    public function cancel(string $batch, string $reason): void
    {
        $this->entryCancelMessage = $this->entryCancelError = null;

        try {
            $n = EntryRecorder::cancelBatch($batch, $reason, Auth::guard('admin')->id());
            $this->entryCancelMessage = $n === 1 ? 'Intrarea a fost anulată.' : "Cele {$n} intrări au fost anulate.";
        } catch (DomainException $e) {
            $this->entryCancelError = $e->getMessage();
        }
    }

    private function resetEntryForm(): void
    {
        $this->count = 1;
        $this->override = false;
        $this->overridePrice = '';
        $this->overrideReason = '';
        $this->payments = [['method' => 'cash', 'amount' => '']];
        $this->resetParticipants();
    }

    /** @return array<string, string> */
    private function entryMethods(): array
    {
        return PaymentMethods::forEntry($this->currentParty());
    }

    private function quote(): ?object
    {
        $party = $this->currentParty();

        return $party && $this->ticket !== '' ? EntryRecorder::quote($party, $this->ticket) : null;
    }

    private function unitCents(): int
    {
        $quote = $this->quote();
        $unit = $quote?->price ?? 0.0;

        if ($this->override && trim($this->overridePrice) !== '') {
            $raw = str_replace(',', '.', trim($this->overridePrice));
            $unit = is_numeric($raw) && (float) $raw >= 0 ? (float) $raw : $unit;
        }

        return (int) round($unit * 100);
    }

    private function totalCents(): int
    {
        return $this->unitCents() * max(1, min(EntryRecorder::MAX_GROUP, (int) $this->count));
    }

    private function fmt(float $n): string
    {
        return $n == floor($n) ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        $parties = $this->selectableParties();
        $party = $this->currentParty();

        $partyOptions = $parties->mapWithKeys(fn (Party $p) => [
            $p->id => $p->name.' · '.$p->start_date->format('d.m.Y').' · '.($p->state() === 'live' ? 'în desfășurare' : 'urmează'),
        ])->all();

        $tickets = [];
        foreach ($party?->entryTicketTypes() ?? [] as $t) {
            $tickets[] = ['name' => $t['name'], 'quote' => EntryRecorder::quote($party, $t['name'])];
        }

        $paid = 0;
        foreach ($this->payments as $p) {
            $raw = str_replace(',', '.', trim((string) ($p['amount'] ?? '')));
            $paid += is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw * 100) : 0;
        }
        $total = $this->totalCents();

        // Ultimele intrari, grupate pe batch.
        $recent = collect();
        if ($party) {
            $recent = PartyEntry::query()
                ->where('party_id', $party->id)
                ->with(['payments', 'creator', 'participant'])
                ->orderByDesc('entered_at')->orderByDesc('id')
                ->limit(300)
                ->get()
                ->groupBy('batch')
                ->take(30)
                ->map(function ($group) {
                    $first = $group->first();
                    $byMethod = [];
                    foreach ($group->flatMap->payments as $pay) {
                        $byMethod[$pay->method] = round(($byMethod[$pay->method] ?? 0) + (float) $pay->amount, 2);
                    }

                    return (object) [
                        'batch' => $first->batch,
                        'count' => $group->count(),
                        'ticket' => $first->ticket_type,
                        'at' => $first->entered_at,
                        'total' => round((float) $group->sum('price_paid'), 2),
                        'payments' => $byMethod,
                        'by' => $first->creator?->name,
                        'participants' => $group->sortBy('id')->pluck('participant.name')->filter()->values()->all(),
                        'override_reason' => $first->override_reason,
                        'grace' => $first->grace_applied,
                        'cancelled' => $first->isCancelled(),
                        'cancel_reason' => $first->cancel_reason,
                    ];
                })
                ->values();
        }

        // Ultimele vanzari de tokeni ale petrecerii (coloana din dreapta).
        $recentTokens = $party
            ? TokenTransaction::query()
                ->where('party_id', $party->id)->where('type', TokenTransaction::SOLD)
                ->with(['payments', 'creator', 'participant'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit(30)->get()
            : collect();

        $picker = $this->participantPickerData($party, true);

        return view('livewire.admin.reception.form', [
            ...$picker,
            'recentTokens' => $recentTokens,
            'party' => $party,
            'partyOptions' => $partyOptions,
            'tickets' => $tickets,
            'quote' => $this->quote(),
            'methods' => $this->entryMethods(),
            'methodLabels' => PaymentMethods::labels(),
            'total' => $total / 100,
            'unit' => $this->unitCents() / 100,
            'paid' => $paid / 100,
            'rest' => ($total - $paid) / 100,
            'stats' => $party ? PartyStats::attendance($party) : null,
            'recent' => $recent,
            'graceMinutes' => EntryRecorder::graceMinutes(),
        ]);
    }
}
