<?php

namespace App\Livewire\Participant;

use App\Models\Ticket;

/** DXA: adaugat (runda 52d). Starea paginii de bilet din SMS, comună linkului lung (semnat) și celui scurt. */
trait ShowsTicketLink
{
    public Ticket $ticket;

    public string $state = 'gone';

    protected function show(Ticket $ticket, bool $valid): void
    {
        $this->ticket = $ticket->load(['party', 'holder']);

        if (! $valid || $ticket->status !== Ticket::VALID) {
            return;
        }

        $holder = $ticket->holder;
        if ($holder?->hasAccount()) {
            $this->state = 'account';
        } else {
            $this->state = 'ok';   // fără deținător (cont șters) sau deținător fără cont
        }
    }

    public function render()
    {
        return view('livewire.participant.ticket-link');
    }
}
