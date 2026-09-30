<?php

namespace App\Livewire\Participant;

use App\Models\Announcement;
use App\Models\Party;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Acasă, publică: caruselul (`in_carousel`: petreceri + anunțuri), anunțurile
 * (`in_list`) și petrecerile următoare. Conținutul cu audiența „doar logați” apare doar cu cont.
 */
#[Layout('layouts.participant', ['title' => 'Acasă'])]
class Home extends Component
{
    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        $carousel = collect()
            ->concat(Party::query()->visible($audience)->where('in_carousel', true)->limit(5)->get()->map(fn (Party $p) => ['kind' => 'party', 'model' => $p]))
            ->concat(Announcement::query()->visible($audience)->where('in_carousel', true)->limit(5)->get()->map(fn (Announcement $a) => ['kind' => 'announcement', 'model' => $a]));

        return view('livewire.participant.home', [
            'carousel' => $carousel->values(),
            'announcements' => Announcement::query()->visible($audience)->where('in_list', true)->limit(5)->get(),
            'parties' => Party::query()->visible($audience)->limit(6)->get(),
        ]);
    }
}
