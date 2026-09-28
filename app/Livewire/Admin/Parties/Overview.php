<?php

namespace App\Livewire\Admin\Parties;

use App\Services\BarStats;
use App\Services\PartiesOverview;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Petreceri - statistici agregate). Pagina "Petreceri > Statistici": privirea de
 * ansamblu peste toate petrecerile ca evenimente (totaluri, tip, frecvență) + rezumat bani/intrări
 * și topuri, all-time. Diferă de Bar\Stats/Reception\Stats (care au propriul toggle de perioadă și
 * intră în detaliu pe bani/tokeni/intrări) — aici e „pagina de start" cu linkuri către acelea.
 */
#[Layout('layouts.admin')]
class Overview extends Component
{
    public function render()
    {
        return view('livewire.admin.parties.overview', [
            'totals' => PartiesOverview::totals(),
            'frequency' => PartiesOverview::frequency(),
            'summary' => PartiesOverview::summary(),
            'topRevenue' => BarStats::topParties('revenue')->take(5),
            'topMargin' => BarStats::topParties('margin')->take(5),
            'topPerParticipant' => BarStats::topParties('per_participant')->take(5),
            'topAttendance' => PartiesOverview::topByAttendance(5),
        ]);
    }
}
