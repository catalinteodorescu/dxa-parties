<?php

namespace App\Livewire\Bar;

use App\Livewire\Bar\Concerns\UsesBarParty;
use App\Models\SalesGroup;
use App\Services\BarSummary;
use App\Services\Plan\PlanGate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Bar - Acasă). Petrecerea de lucru (aleasă pe un ecran separat, schimbabilă), informațiile ei,
 * acțiunile (Vânzare / Raportare) și un rezumat al sesiunii de vânzări deschise.
 */
#[Layout('layouts.bar')]
class Home extends Component
{
    use UsesBarParty;

    public function render()
    {
        $party = $this->currentParty();
        $group = $party ? SalesGroup::open()->where('party_id', $party->id)->orderBy('id')->with('barReport')->first() : null;

        return view('livewire.bar.home', [
            'party' => $party,
            'group' => $group,
            'reportSubmitted' => $group?->reportSubmitted() ?? false,
            'summary' => $group ? BarSummary::for($group) : null,
            // DXA: runda 80 — dalele din meniu urmează planul instalării
            'can' => [
                'sale' => PlanGate::allows('bar_sales'),
                'reports' => PlanGate::allows('bar_reports'),
            ],
        ]);
    }
}
