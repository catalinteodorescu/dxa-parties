<?php

namespace App\Livewire\Admin\Bar;

use App\Models\Party;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Support\PaymentMethods;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Bar - statistici pe petrecere, live). Spre deosebire de „Bar > Statistici” (doar petreceri cu raportare de stoc
 * finalizată), aici se văd cifrele oricărei petreceri cu vânzări, inclusiv a celei în desfășurare: vânzări pe oră, produse
 * (top + pe oră) și metode de plată. Sursa e direct vânzările NEANULATE ale petrecerii (toate sesiunile ei).
 */
class PartyLive extends Component
{
    #[Url]
    public string $party = '';

    public function mount(): void
    {
        // Petrecerea afișată e și cea selectată în listă: implicit, cea mai recentă cu vânzări.
        if ($this->party === '') {
            $first = Party::whereIn('id', SalesGroup::query()->whereNotNull('party_id')->whereHas('sales')->distinct()->pluck('party_id'))
                ->orderByDesc('starts_at')->orderByDesc('id')->first();
            $this->party = $first ? (string) $first->id : '';
        }
    }

    public function render()
    {
        $partyIds = SalesGroup::query()->whereNotNull('party_id')->whereHas('sales')->distinct()->pluck('party_id');
        $parties = Party::whereIn('id', $partyIds)->orderByDesc('starts_at')->orderByDesc('id')->get();
        $selected = $parties->firstWhere('id', (int) $this->party) ?? $parties->first();

        $stats = null;
        if ($selected) {
            $sales = Sale::query()->completed()
                ->whereHas('group', fn ($g) => $g->where('party_id', $selected->id))
                ->with(['lines.menuItem', 'payments'])
                ->orderBy('sold_at')->get();

            $hours = [];
            $products = [];
            $byHour = [];   // ora => produs => qty
            $methods = [];

            foreach ($sales as $sale) {
                $h = (int) $sale->sold_at->format('G');
                $hours[$h] ??= ['count' => 0, 'total' => 0.0];
                $hours[$h]['count']++;
                $hours[$h]['total'] = round($hours[$h]['total'] + (float) $sale->total, 2);

                foreach ($sale->lines as $l) {
                    $name = $l->menuItem?->name ?? '—';
                    $products[$name] ??= ['qty' => 0.0, 'revenue' => 0.0];
                    $products[$name]['qty'] += (float) $l->qty;
                    $products[$name]['revenue'] = round($products[$name]['revenue'] + (float) $l->total_price, 2);
                    $byHour[$h][$name] = ($byHour[$h][$name] ?? 0) + (float) $l->qty;
                }

                foreach ($sale->payments as $p) {
                    $methods[$p->method] ??= ['amount' => 0.0, 'tokens' => 0];
                    $methods[$p->method]['amount'] = round($methods[$p->method]['amount'] + (float) $p->amount, 2);
                    $methods[$p->method]['tokens'] += (int) $p->tokens;
                }
            }

            ksort($hours);
            uasort($products, fn ($a, $b) => $b['qty'] <=> $a['qty']);
            $top = array_slice($products, 0, 8, true);
            $topNames = array_slice(array_keys($products), 0, 5);
            $revenue = round($sales->sum(fn ($s) => (float) $s->total), 2);

            $stats = (object) [
                'sales_count' => $sales->count(),
                'revenue' => $revenue,
                'avg_ticket' => $sales->count() ? round($revenue / $sales->count(), 2) : null,
                'hours' => $hours,
                'top' => $top,
                'top_names' => $topNames,
                'by_hour' => $byHour,
                'methods' => $methods,
                'cancelled' => Sale::query()->where('status', 'cancelled')->whereHas('group', fn ($g) => $g->where('party_id', $selected->id))->count(),
                'labels' => PaymentMethods::labels(),
            ];
        }

        return view('livewire.admin.bar.party-live', ['parties' => $parties, 'selected' => $selected, 'stats' => $stats]);
    }
}
