<?php

namespace App\Livewire\Participant;

use App\Services\TicketTransfers;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** DXA: adaugat (runda 52d). Aceeași pagină ca TicketLink, din linkul scurt din SMS (/b/<cod>); codul conține și semnătura. */
#[Layout('layouts.participant-guest', ['title' => 'Bilet'])]
class TicketShortLink extends Component
{
    use ShowsTicketLink;

    public function mount(string $code): void
    {
        $found = TicketTransfers::findByShortCode($code);
        abort_unless($found, 404);

        $this->show($found, true);
    }
}
