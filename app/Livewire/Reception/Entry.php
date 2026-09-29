<?php

namespace App\Livewire\Reception;

use App\Livewire\Concerns\HandlesEntryForm;
use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Services\EntryRecorder;
use App\Support\PaymentMethods;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - Etapa 2). Ecranul „Intrare”: tip de bilet, număr de persoane, participanți (opțional),
 * plata pe total (poate fi mixtă). Aceeași logică ca ecranul din admin (trait HandlesEntryForm → EntryRecorder).
 * Diferență: prețul îl poate schimba DOAR un admin (rolul din tabelul admins), nu orice recepționer.
 */
#[Layout('layouts.reception')]
class Entry extends Component
{
    use HandlesEntryForm;
    use UsesReceptionParty;

    protected function canOverridePrice(): bool
    {
        return Auth::guard('admin')->user()?->canAccess('admin') ?? false;
    }

    public function render()
    {
        $party = $this->currentParty();
        $this->syncTicket();

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

        return view('livewire.reception.entry', [
            ...$this->participantPickerData($party, true, true),
            'party' => $party,
            'tickets' => $tickets,
            'methods' => $this->entryMethods(),
            'topMethods' => EntryRecorder::topMethods($this->entryMethods()),
            'methodLabels' => PaymentMethods::labels(),
            'total' => $total / 100,
            'unit' => $this->unitCents() / 100,
            'paid' => $paid / 100,
            'rest' => ($total - $paid) / 100,
            'graceMinutes' => EntryRecorder::graceMinutes(),
            'canOverride' => $this->canOverridePrice(),
            'tokensSellable' => PaymentMethods::tokensSellable(),
            'creditsSellable' => PaymentMethods::creditsPurchasable(),
        ]);
    }
}
