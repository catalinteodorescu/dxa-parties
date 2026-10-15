<?php

namespace App\Livewire\Reception;

use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\ReceptionSession;
use App\Services\ReceptionSummary;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 72). „Rezumat” de închidere în aplicația de recepție: ecran doar de citit (fără câmpuri de completat) cu
 * ce s-a întâmplat în sesiunea deschisă: intrări pe tip de bilet, gratuite, anulate, încasări pe metodă, tokeni și credite vândute.
 * Cifrele vin din App\Services\ReceptionSummary (aceleași ca la Raportare).
 */
#[Layout('layouts.reception')]
class Summary extends Component
{
    use UsesReceptionParty;

    public function render()
    {
        $party = $this->currentParty();
        $session = $party ? ReceptionSession::currentFor($party->id) : null;
        $summary = $session ? ReceptionSummary::for($session) : null;

        return view('livewire.reception.summary', [
            'party' => $party,
            'session' => $session,
            'summary' => $summary,
            'collected' => $summary ? round(array_sum($summary->methods), 2) : 0.0,
        ]);
    }
}
