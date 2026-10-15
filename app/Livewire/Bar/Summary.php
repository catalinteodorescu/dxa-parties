<?php

namespace App\Livewire\Bar;

use App\Livewire\Bar\Concerns\UsesBarParty;
use App\Models\SalesGroup;
use App\Services\BarSummary;
use App\Support\PaymentMethods;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 72). „Rezumat” de închidere în aplicația de bar: doar de citit. Vânzări (bonuri, venit), încasări pe metodă
 * (inclusiv plățile cu credite), produsele vândute și stocul consumat. Stocul consumat e ESTIMAT din rețetele produselor vândute
 * (stocul real se scade la Raportarea de stoc, nu la fiecare vânzare).
 */
#[Layout('layouts.bar')]
class Summary extends Component
{
    use UsesBarParty;

    public function render()
    {
        $party = $this->currentParty();
        $group = $party ? SalesGroup::open()->where('party_id', $party->id)->orderBy('id')->first() : null;
        $summary = $group ? BarSummary::for($group) : null;
        $lines = $group ? $group->aggregatedLines() : collect();

        // Stoc consumat estimat: cantitatea vândută × cantitatea din rețetă, pe produs de stoc.
        $stock = [];
        foreach ($lines as $line) {
            foreach ($line->menuItem->recipeLines as $recipe) {
                $item = $recipe->stockItem;
                if (! $item) {
                    continue;
                }
                $stock[$item->id] ??= ['name' => $item->name, 'unit' => $item->unit, 'qty' => 0.0];
                $stock[$item->id]['qty'] += $line->qty * (float) $recipe->qty;
            }
        }
        usort($stock, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return view('livewire.bar.summary', [
            'party' => $party,
            'summary' => $summary,
            'lines' => $lines,
            'stock' => $stock,
            'creditKey' => PaymentMethods::CREDIT,
        ]);
    }
}
