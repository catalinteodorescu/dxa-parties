<?php

namespace App\Livewire\Participant;

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16b). Anunțurile din listă (`in_list`), cu lazy load la defilare (câte 10); publice; cele „doar logați” apar doar cu cont.
 */
#[Layout('layouts.participant', ['title' => 'Anunțuri'])]
class Announcements extends Component
{
    public int $limit = 10;

    public function more(): void
    {
        $this->limit += 10;
    }

    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        $rows = Announcement::query()->visible($audience)->where('in_list', true)->limit($this->limit + 1)->get();

        return view('livewire.participant.announcements', [
            'announcements' => $rows->take($this->limit),
            'hasMore' => $rows->count() > $this->limit,
        ]);
    }
}
