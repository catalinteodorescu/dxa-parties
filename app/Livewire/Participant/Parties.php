<?php

namespace App\Livewire\Participant;

use App\Models\Party;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Lista petrecerilor publicate, active și neîncheiate (cele „doar logați” apar
 * doar cu cont).
 */
#[Layout('layouts.participant', ['title' => 'Petreceri'])]
class Parties extends Component
{
    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        return view('livewire.participant.parties', [
            'parties' => Party::query()->visible($audience)->limit(50)->get(),
        ]);
    }
}
