<?php

namespace App\Livewire\Admin\Reception;

use App\Livewire\Concerns\HandlesEntryForm;
use App\Models\CreditTransaction;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\TokenTransaction;
use App\Services\CreditLedger;
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
 *
 * DXA: adaugat (Portofelul de credite - Etapa 2). Vânzarea de credite (cardul CreditSale, în oglindă cu
 * TokenSale) e a treia acțiune a ecranului, cu lista ei de „Ultimele vânzări de credite” + anulare.
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    use HandlesEntryForm;

    public ?int $partyId = null;

    /** Mesaje ale anulărilor din „Ultimele intrări” / „Ultimele vânzări de tokeni” (afișate în cardul listei). */
    public ?string $entryCancelMessage = null;

    public ?string $entryCancelError = null;

    public ?string $tokenCancelMessage = null;

    public ?string $tokenCancelError = null;

    public ?string $creditCancelMessage = null;

    public ?string $creditCancelError = null;

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
        return $this->selectable ??= Party::query()->forReception()->limit(30)->get();
    }

    private function currentParty(): ?Party
    {
        return $this->partyId ? $this->selectableParties()->firstWhere('id', $this->partyId) : null;
    }

    public function updatedPartyId(): void
    {
        $this->message = $this->error = null;
        $this->entryCancelMessage = $this->entryCancelError = $this->tokenCancelMessage = $this->tokenCancelError = null;
        $this->creditCancelMessage = $this->creditCancelError = null;
        $this->resetEntryForm();
        $this->syncTicket();
    }

    /** Vânzare de tokeni făcută în cardul TokenSale (sau anulată aici): reafișează listele din dreapta. */
    #[On('tokens-changed')]
    public function refreshTokens(): void
    {
        // doar re-randare
    }

    /** Vânzare de credite făcută în cardul CreditSale (sau anulată aici): reafișează listele din dreapta. */
    #[On('credits-changed')]
    public function refreshCredits(): void
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

    /** DXA: adaugat (Etapa 2). Anulează o vânzare de credite din lista din dreapta (motiv obligatoriu; doar dacă creditele nu au fost deja folosite). */
    public function cancelCreditSale(int $id, string $reason): void
    {
        $this->creditCancelMessage = $this->creditCancelError = null;

        try {
            CreditLedger::cancel($id, $reason, Auth::guard('admin')->id());
            $this->creditCancelMessage = 'Vânzarea de credite a fost anulată.';
            $this->dispatch('credits-changed');
        } catch (DomainException $e) {
            $this->creditCancelError = $e->getMessage();
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

    public function render()
    {
        $parties = $this->selectableParties();
        $party = $this->currentParty();

        $partyOptions = $parties->mapWithKeys(fn (Party $p) => [
            $p->id => $p->name.' · '.$p->start_date->format('d.m.Y').' · '.match ($p->state()) {
                'live' => 'în desfășurare',
                'past' => 'încheiată · casa deschisă',
                default => 'urmează',
            },
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

        // DXA: adaugat (Etapa 2). Ultimele vanzari de credite ale petrecerii (coloana din dreapta).
        $recentCredits = $party
            ? CreditTransaction::query()
                ->where('party_id', $party->id)->where('type', CreditTransaction::LOAD)->where('source', CreditTransaction::SOURCE_RECEPTION)
                ->with(['payments', 'createdBy', 'participant'])
                ->orderByDesc('occurred_at')->orderByDesc('id')
                ->limit(30)->get()
            : collect();

        $picker = $this->participantPickerData($party, true, true);

        return view('livewire.admin.reception.form', [
            ...$picker,
            'recentTokens' => $recentTokens,
            'recentCredits' => $recentCredits,
            'party' => $party,
            'partyOptions' => $partyOptions,
            'tickets' => $tickets,
            'quote' => $this->quote(),
            'methods' => $this->entryMethods(),
            'topMethods' => EntryRecorder::topMethods($this->entryMethods()),
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
