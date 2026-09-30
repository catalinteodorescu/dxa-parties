<?php

namespace App\Livewire\Participant;

use App\Models\Party;
use App\Support\PartyPublic;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Detaliul unei petreceri: program, invitați, locație și prețuri cu trepte live.
 * Ciornele și petrecerile dezactivate nu se văd (404); cele „doar logați” trimit la login. Cumpărarea de bilete vine în
 * runda 3 (butonul e deocamdată doar informativ).
 */
#[Layout('layouts.participant')]
class PartyShow extends Component
{
    public int $partyId;

    public function mount(Party $party): void
    {
        abort_if($party->isDraft() || ! $party->is_active, 404);

        if ($party->audience === 'auth' && ! auth('participant')->check()) {
            session()->put('url.intended', route('app.party', $party));
            session()->flash('status', 'Petrecerea e vizibilă doar cu cont. Intră în cont sau creează unul.');
            $this->redirectRoute('app.login', navigate: true);

            return;
        }

        $this->partyId = $party->id;
    }

    public function render()
    {
        $party = Party::findOrFail($this->partyId);

        return view('livewire.participant.party-show', [
            'party' => $party,
            'tickets' => PartyPublic::tickets($party),
            'state' => $party->state(),
        ])->title($party->name);
    }
}
