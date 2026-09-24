<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Party;
use App\Services\PartyStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Statistici pe petrecere (doar raportari finalizate) + comparatie cu alta petrecere, pe aceeasi pagina.
 * Comparatia implicita = petrecerea precedenta de acelasi tip, cu raportari finalizate; se poate schimba
 * sau dezactiva din selector. Calculele sunt in App\Services\PartyStats.
 */
#[Layout('layouts.admin')]
class Stats extends Component
{
    public Party $party;

    /** Id-ul petrecerii cu care se compara, sau 'none'. Null doar pana la mount (apoi primeste implicitul). */
    #[Url(as: 'compara')]
    public ?string $compare = null;

    /** Ordinea produselor: revenue | qty | profit. */
    #[Url(as: 'sortare')]
    public string $sort = 'revenue';

    public bool $showAllProducts = false;

    /** True cat timp comparatia e cea propusa automat (nu aleasa de utilizator); afisat ca explicatie in pagina. */
    public bool $compareIsDefault = false;

    public function mount(Party $party): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->party = $party;

        if ($this->compare === null || $this->compare === '') {
            $default = PartyStats::defaultCompare($party);
            $this->compare = (string) ($default?->id ?? 'none');
            $this->compareIsDefault = $default !== null;
        }
    }

    public function updatedCompare(): void
    {
        $this->compareIsDefault = false;
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['revenue', 'qty', 'profit'], true) ? $sort : 'revenue';
    }

    /** Petrecerea aleasa pentru comparatie (validata: alta petrecere, cu raportari finalizate). */
    private function compareParty($candidates): ?Party
    {
        if ($this->compare === null || $this->compare === 'none') {
            return null;
        }

        return $candidates->firstWhere('id', (int) $this->compare);
    }

    /**
     * Randurile de KPI, dirijate de date: cheie, eticheta, tip (money|int|percent), directie
     * ('up' = mai mare e mai bine, 'down' = mai mic e mai bine, null = neutru), valoare per petrecere.
     * Metricile din modulul Recepție (participanți, venit bar / participant, gratuit vs. plătit)
     * se adaugă aici, pe baza $stats->attendance, și apar automat si in comparatie.
     */
    public function kpis(object $a, ?object $b): array
    {
        $rows = [
            ['revenue', 'Venit', 'money', 'up', 'primary'],
            ['cost', 'Cost', 'money', null, 'primary'],
            ['profit', 'Profit', 'money', 'up', 'primary'],
            ['margin', 'Marjă', 'percent', 'up', 'primary'],
            ['tx_count', 'Bonuri (aplicație)', 'int', 'up', 'secondary'],
            ['avg_ticket', 'Bon mediu', 'money', 'up', 'secondary'],
            ['losses_cost', 'Pierderi (cost)', 'money', 'down', 'secondary'],
            ['inventory_cost', 'Lipsuri inventar (cost)', 'money', 'down', 'secondary'],
        ];

        return array_map(fn ($r) => (object) [
            'key' => $r[0],
            'label' => $r[1],
            'type' => $r[2],
            'direction' => $r[3],
            'group' => $r[4],
            'value' => $a->{$r[0]},
            'compare' => $b?->{$r[0]},
        ], $rows);
    }

    private function sortedProducts(object $stats)
    {
        $key = in_array($this->sort, ['revenue', 'qty', 'profit'], true) ? $this->sort : 'revenue';

        return $stats->products->sortByDesc(fn ($p) => $p->{$key} ?? -INF)->values();
    }

    public function render()
    {
        $candidates = PartyStats::comparableParties($this->party);
        $compareParty = $this->compareParty($candidates);

        $stats = PartyStats::for($this->party);
        $cmp = $compareParty ? PartyStats::for($compareParty) : null;

        $compareOptions = ['none' => 'Fără comparație'];
        foreach ($candidates as $p) {
            $compareOptions[$p->id] = $p->name.' · '.$p->start_date->format('d.m.Y').' · '.($p->isFestival() ? 'Festival' : 'Simplă');
        }

        $allProducts = $this->sortedProducts($stats);
        $products = $this->showAllProducts ? $allProducts : $allProducts->take(10);
        $compareQty = $cmp ? $cmp->products->keyBy('menu_item_id') : collect();

        $axis = PartyStats::hourAxis($stats->hours, $cmp?->hours ?? []);

        return view('livewire.admin.parties.stats', [
            'stats' => $stats,
            'cmp' => $cmp,
            'compareParty' => $compareParty,
            'compareOptions' => $compareOptions,
            'kpis' => $this->kpis($stats, $cmp),
            'products' => $products,
            'productsTotal' => $allProducts->count(),
            'compareProducts' => $compareQty,
            'axis' => $axis,
            'unreported' => PartyStats::unreported($this->party),
        ]);
    }
}
