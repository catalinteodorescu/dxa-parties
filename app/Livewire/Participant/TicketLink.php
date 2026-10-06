<?php

namespace App\Livewire\Participant;

use App\Models\Ticket;
use App\Services\TicketTransfers;
use Illuminate\Http\Request;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 39). Pagina din linkul SMS „ți-a trimis un bilet”: arată biletul (cu QR) unui destinatar fără cont și îl
 * îndrumă să instaleze aplicația și să-și facă cont cu același telefon. Linkul e semnat; nu cere cont.
 * Stări: `ok` (biletul e valabil, fără cont încă), `account` (numărul are deja cont), `gone` (link stins / bilet folosit sau anulat).
 */
#[Layout('layouts.participant-guest', ['title' => 'Bilet primit'])]
class TicketLink extends Component
{
    public Ticket $ticket;

    public string $state = 'gone';

    public function mount(Ticket $ticket, Request $request): void
    {
        $this->ticket = $ticket->load(['party', 'holder']);

        if (! TicketTransfers::linkValid($ticket, (string) $request->query('v', ''))) {
            return;
        }

        $holder = $ticket->holder;
        if ($holder?->hasAccount()) {
            $this->state = 'account';
        } elseif ($ticket->status === Ticket::VALID && $holder) {
            $this->state = 'ok';
        }
    }

    public function render()
    {
        return view('livewire.participant.ticket-link');
    }
}
