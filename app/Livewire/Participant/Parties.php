<?php

namespace App\Livewire\Participant;

use App\Models\Party;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Toate petrecerile publicate și active: cele următoare, apoi cele trecute (cele „doar logați” apar
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
            'past' => Party::query()->visiblePast($audience)->limit(30)->get(),
        ]);
    }
}
