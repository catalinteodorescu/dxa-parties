<?php

namespace App\Livewire\Reception;

use App\Livewire\Reception\Concerns\UsesReceptionParty;
use App\Models\ReceptionSession;
use App\Services\ReceptionSummary;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție - Acasă). Petrecerea de lucru (aleasă pe un ecran separat, schimbabilă), acțiunile disponibile
 * (Intrare / Vânzare tokeni / Vânzare credite) și un rezumat al sesiunii de recepție deschise.
 */
#[Layout('layouts.reception')]
class Home extends Component
{
    use UsesReceptionParty;

    public function render()
    {
        $party = $this->currentParty();
        $session = $party ? ReceptionSession::currentFor($party->id) : null;
        $summary = $session ? ReceptionSummary::for($session) : null;

        return view('livewire.reception.home', [
            'party' => $party,
            'session' => $session,
            'reportSubmitted' => $session?->reportSubmitted() ?? false,
            'summary' => $summary,
            'collected' => $summary ? round(array_sum($summary->methods), 2) : 0.0,
            'tokensSellable' => PaymentMethods::tokensSellable(),
            'creditsSellable' => PaymentMethods::creditsPurchasable(),
        ]);
    }
}
