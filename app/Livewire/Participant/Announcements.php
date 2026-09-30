<?php

namespace App\Livewire\Participant;

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16b). Toate anunțurile din listă (`in_list`), publice; cele „doar logați” apar doar cu cont.
 */
#[Layout('layouts.participant', ['title' => 'Anunțuri'])]
class Announcements extends Component
{
    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        return view('livewire.participant.announcements', [
            'announcements' => Announcement::query()->visible($audience)->where('in_list', true)->limit(100)->get(),
        ]);
    }
}
