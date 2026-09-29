<?php

namespace App\Livewire\Admin\Bar;

use App\Services\BarStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Bar - statistici agregate). Pagina "Bar > Statistici": privire generala peste
 * TOATE petrecerile (nu una singura, ca la Parties\Stats), clasament petreceri, top produse, staff.
 * Numerele vin din App\Services\BarStats, care le ia din PartyStats::for() (aceeasi sursa ca pagina
 * de detaliu per petrecere) si doar le aduna.
 */
#[Layout('layouts.admin')]
class Stats extends Component
{
    /** general = peste toate petrecerile; party = o petrecere anume (și cea în desfășurare). */
    #[Url]
    public string $tab = 'general';

    /** all = tot istoricul; recent = ultimele 3 luni (dupa data petrecerii). Afecteaza privirea generala + staff. */
    #[Url]
    public string $period = 'all';

    /** Criteriul clasamentului de petreceri (all-time, nu urmeaza $period). */
    #[Url]
    public string $rankBy = 'revenue';

    public function render()
    {
        $since = $this->period === 'recent' ? now()->subMonths(3) : null;

        return view('livewire.admin.bar.stats', [
            'overview' => BarStats::overview($since),
            'trend' => BarStats::trend($since),
            'products' => BarStats::products(),
            'ranking' => BarStats::topParties($this->rankBy),
            'staff' => BarStats::staff($since),
        ]);
    }
}
