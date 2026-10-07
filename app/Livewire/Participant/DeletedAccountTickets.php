<?php

namespace App\Livewire\Participant;

use App\Models\Ticket;
use App\Services\AccountDeletion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 53). Pagina din SMS-ul trimis la ștergerea contului: toate biletele rămase, cu codul QR al fiecăruia. Fără cont;
 * linkul (/c/<cod>) conține semnătura, deci cine îl are vede biletele. Biletele folosite, anulate sau de la petreceri încheiate dispar singure.
 */
#[Layout('layouts.participant-guest', ['title' => 'Biletele tale'])]
class DeletedAccountTickets extends Component
{
    /** @var Collection<int, Ticket> */
    public Collection $tickets;

    public function mount(string $code): void
    {
        $account = AccountDeletion::findByTicketsCode($code);
        abort_unless($account, 404);

        $this->tickets = AccountDeletion::remainingTickets($account);
    }

    public function render()
    {
        return view('livewire.participant.deleted-account-tickets');
    }
}
