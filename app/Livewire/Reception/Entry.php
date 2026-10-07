<?php

namespace App\Livewire\Reception;

use App\Livewire\Concerns\HandlesEntryForm;
use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\Ticket;
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

    /** DXA: adaugat (runda 47). Formularul s-a deschis o dată din scanare (bilete / participant în adresă): pre-completarea nu se mai repetă. */
    public bool $scanPrefilled = false;

    /**
     * DXA: adaugat (runda 47). Scanerul de pe ecranul principal trimite aici ce nu poate înregistra singur: `?bilete=1,2` (au de încasat)
     * și/sau `?participant=ID` (persoană fără bilete → flux de vânzare). Biletele se reverifică (petrecerea curentă, valabile).
     */
    protected function prefillFromScan(): void
    {
        $party = $this->currentParty();
        if (! $party) {
            return;
        }

        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string) request()->query('bilete')))))), 0, EntryRecorder::MAX_GROUP);
        if ($ids !== []) {
            $this->addTickets(Ticket::query()->where('party_id', $party->id)->where('status', Ticket::VALID)->whereIn('id', $ids)->orderBy('id')->get());
        }

        $participantId = (int) request()->query('participant');
        if ($participantId > 0 && ! $this->participantError) {
            $this->pickParticipant($participantId);
        }
    }

    protected function canOverridePrice(): bool
    {
        return Auth::guard('admin')->user()?->canAccess('admin') ?? false;
    }

    public function render()
    {
        $party = $this->currentParty();
        if (! $this->scanPrefilled) {
            $this->scanPrefilled = true;
            $this->prefillFromScan();
        }
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
