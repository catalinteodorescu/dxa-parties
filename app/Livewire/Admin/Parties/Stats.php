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

    /** Clasamentul participanților: bar (cheltuit la bar) | tokens (tokeni cumpărați) | entries (intrări). */
    #[Url(as: 'top')]
    public string $topBy = 'bar';

    public bool $showAllProducts = false;

    public function mount(Party $party): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->party = $party;

        // DXA: adaugat — fara comparatie implicita; utilizatorul alege explicit din selector.
        if ($this->compare === null || $this->compare === '') {
            $this->compare = 'none';
        }
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
     * Grupuri (separare vizuala in pagina, vezi stats.blade.php): 'primary' (cost/profit, mereu
     * primul, cardurile mari) apoi 'bar', 'reception', 'tokens', 'participants' — fiecare cu
     * headerul lui, in aceasta ordine. Metricile din modulul Recepție (participanți, venit bar /
     * participant, gratuit vs. plătit) se adaugă aici, pe baza $stats->attendance, și apar automat
     * si in comparatie.
     */
    public function kpis(object $a, ?object $b): array
    {
        $rows = [
            ['revenue', 'Venit', 'money', 'up', 'primary'],
            ['cost', 'Cost', 'money', null, 'primary'],
            ['profit', 'Profit', 'money', 'up', 'primary'],
            ['margin', 'Marjă', 'percent', 'up', 'primary'],
            ['tx_count', 'Bonuri (aplicație)', 'int', 'up', 'bar'],
            ['avg_ticket', 'Bon mediu', 'money', 'up', 'bar'],
            ['losses_cost', 'Pierderi (cost)', 'money', 'down', 'bar'],
            ['inventory_cost', 'Lipsuri inventar (cost)', 'money', 'down', 'bar'],
        ];

        // Participanti la bar: ce parte din vanzarile din aplicatie au un participant.
        if ($a->bar_identified_pct !== null || ($b && $b->bar_identified_pct !== null)) {
            $rows[] = ['bar_identified_pct', 'Vânzări cu participant', 'percent', 'up', 'bar'];
        }

        // Casa de recepție: doar când există raportări de recepție finalizate la una din petreceri.
        if ($a->reception_cash_diff !== null || ($b && $b->reception_cash_diff !== null)) {
            $rows[] = ['reception_cash_diff', 'Diferență casă recepție', 'money_signed', null, 'reception'];
        }

        // Tokeni (Receptie): doar cand exista vanzari/incasari de tokeni la una din petreceri.
        if ($a->tokens_sold !== null || ($b && $b->tokens_sold !== null)) {
            $rows[] = ['tokens_sold', 'Tokeni vânduți', 'int', null, 'tokens'];
            $rows[] = ['tokens_collected', 'Tokeni încasați', 'int', null, 'tokens'];
        }

        // Participanti & intrari (Receptie): doar cand exista intrari la una din cele doua petreceri comparate.
        if ($a->entries_count !== null || ($b && $b->entries_count !== null)) {
            $rows[] = ['entries_count', 'Participanți', 'int', 'up', 'participants'];
            $rows[] = ['entries_revenue', 'Venit intrări', 'money', 'up', 'participants'];
            $rows[] = ['entries_free_pct', 'Intrări gratuite', 'percent', null, 'participants'];
            $rows[] = ['entries_identified', 'Identificați', 'int', 'up', 'participants'];
            $rows[] = ['bar_per_participant', 'Venit bar / participant', 'money', 'up', 'participants'];
        }

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

        // Clasamentul participantilor (doar cei cu valoare > 0 la metrica aleasa), primii 10.
        $metric = fn ($r) => match ($this->topBy) {
            'tokens' => $r->tokens,
            'entries' => $r->entries,
            default => $r->bar_spent,
        };
        $ranked = $stats->participants
            ? $stats->participants->rows->filter(fn ($r) => $metric($r) > 0)->sortByDesc($metric)->values()
            : collect();

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
            'topRows' => $ranked->take(10),
            'topTotal' => $ranked->count(),
            'entryAxis' => PartyStats::hourAxis($stats->attendance?->hours ?? [], $cmp?->attendance?->hours ?? []),
            'unreported' => PartyStats::unreported($this->party),
        ]);
    }
}
