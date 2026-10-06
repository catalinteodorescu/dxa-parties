<?php

namespace App\Livewire\Participant;

use App\Livewire\Concerns\TogglesPartyInterest;
use App\Models\Announcement;
use App\Models\Party;
use App\Support\ParticipantAppSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Acasă, publică: caruselul (`in_carousel`: petreceri + anunțuri), anunțurile
 * (`in_list`) și petrecerile următoare. Conținutul cu audiența „doar logați” apare doar cu cont.
 */
#[Layout('layouts.participant', ['title' => 'Acasă'])]
class Home extends Component
{
    use TogglesPartyInterest;

    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        $carousel = collect()
            ->concat(Party::query()->visible($audience)->where('in_carousel', true)->limit(5)->get()->map(fn (Party $p) => ['kind' => 'party', 'model' => $p]))
            ->concat(Announcement::query()->visible($audience)->where('in_carousel', true)->limit(5)->get()->map(fn (Announcement $a) => ['kind' => 'announcement', 'model' => $a]));

        return view('livewire.participant.home', [
            'savedIds' => $this->savedIds(),
            'carousel' => $carousel->values(),
            'announcements' => ParticipantAppSettings::homeAnnouncements() > 0
                ? Announcement::query()->visible($audience)->where('in_list', true)->limit(ParticipantAppSettings::homeAnnouncements())->get()
                : collect(),
            'eyebrow' => ParticipantAppSettings::homeEyebrow(),
            'title' => ParticipantAppSettings::homeTitle(),
            'parties' => Party::query()->visible($audience)->limit(ParticipantAppSettings::homeParties())->get(),
        ]);
    }
}
