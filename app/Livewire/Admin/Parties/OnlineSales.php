<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Party;
use App\Services\OnlineSalesReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 36). Raportul vânzărilor online pe o petrecere (bilete pe tip, combo, oferite, trepte, coduri).
 * Calculele sunt în App\Services\OnlineSalesReport. Doar citire.
 */
#[Layout('layouts.admin')]
class OnlineSales extends Component
{
    public Party $party;

    public function mount(Party $party): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->party = $party;
    }

    public function render()
    {
        return view('livewire.admin.parties.online-sales', ['report' => OnlineSalesReport::forParty($this->party)]);
    }
}
