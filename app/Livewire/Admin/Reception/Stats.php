<?php

namespace App\Livewire\Admin\Reception;

use App\Services\ReceptionStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Receptie - statistici agregate). Pagina "Receptie > Statistici": intrari/% gratuite
 * in timp, tokeni vanduti vs. incasati, diferente de casa recurente. Numerele vin din
 * App\Services\ReceptionStats, care le ia din PartyStats::attendance()/reception() si
 * TokenLedger::partyTotals() (aceeasi sursa ca pagina de detaliu per petrecere).
 */
#[Layout('layouts.admin')]
class Stats extends Component
{
    /** all = tot istoricul; recent = ultimele 3 luni (dupa data petrecerii). Afecteaza doar tokenii. */
    #[Url]
    public string $period = 'all';

    public function render()
    {
        $since = $this->period === 'recent' ? now()->subMonths(3) : null;

        return view('livewire.admin.reception.stats', [
            'entries' => ReceptionStats::entriesTimeline(),
            'methodUsage' => ReceptionStats::entryMethodUsage(),
            'tokensOverview' => ReceptionStats::tokensOverview($since),
            'tokensTimeline' => ReceptionStats::tokensTimeline($since),
            'cashDiff' => ReceptionStats::cashDiffTimeline(),
        ]);
    }
}
