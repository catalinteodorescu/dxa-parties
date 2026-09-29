<?php

namespace App\Livewire\Reception;

use App\Models\Party;
use App\Support\ReceptionApp;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție). Ecranul de alegere a petrecerii de lucru, afișat după login (și la „Schimbă petrecerea”).
 * Alegerea se reține în sesiune și duce la Acasă. Se pot alege doar petreceri publicate, active și neîncheiate.
 */
#[Layout('layouts.reception', ['tinted' => true])]
class PartyPicker extends Component
{
    /** Petrecerea aleasă acum (doar pentru evidențiere). */
    public ?int $currentId = null;

    public function mount(): void
    {
        $saved = (int) session(ReceptionApp::PARTY_SESSION_KEY);
        $this->currentId = $this->parties()->firstWhere('id', $saved)?->id;
    }

    private function parties()
    {
        return Party::query()->forReception()->limit(30)->get();
    }

    public function choose(int $id): void
    {
        abort_unless($this->parties()->contains('id', $id), 404);

        session([ReceptionApp::PARTY_SESSION_KEY => $id]);

        $this->redirectRoute('receptie.home', navigate: true);
    }

    public function render()
    {
        return view('livewire.reception.party-picker', ['parties' => $this->parties()]);
    }
}
