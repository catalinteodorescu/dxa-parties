<?php

namespace App\Livewire\Bar;

use App\Models\Party;
use App\Support\BarApp;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Bar). Ecranul de alegere a petrecerii de lucru, afișat după login (și la „Schimbă petrecerea”).
 * Alegerea se reține în sesiune și duce la Acasă. Se pot alege petreceri publicate, active și neîncheiate, plus cele cu
 * o sesiune de vânzări încă deschisă (Party::forBar()).
 */
#[Layout('layouts.bar', ['tinted' => true])]
class PartyPicker extends Component
{
    /** Petrecerea aleasă acum (doar pentru evidențiere). */
    public ?int $currentId = null;

    public function mount(): void
    {
        $saved = (int) session(BarApp::PARTY_SESSION_KEY);
        $this->currentId = $this->parties()->firstWhere('id', $saved)?->id;
    }

    private function parties()
    {
        return Party::query()->forBar()->limit(30)->get();
    }

    public function choose(int $id): void
    {
        abort_unless($this->parties()->contains('id', $id), 404);

        session([BarApp::PARTY_SESSION_KEY => $id]);

        $this->redirectRoute('bar.home', navigate: true);
    }

    public function render()
    {
        return view('livewire.bar.party-picker', ['parties' => $this->parties()]);
    }
}
