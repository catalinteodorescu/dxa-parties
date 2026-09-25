<?php

namespace App\Livewire\Admin\Sales;

use App\Models\MenuItem;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Bar - vanzari)
 *
 * Toate vanzarile barului, oricand sau filtrate pe sesiune de vanzari (ex. o petrecere) / fara sesiune. Vanzarile
 * nu se sterg: se anuleaza (cu motiv), si doar cat timp grupul lor e deschis - dupa
 * finalizarea Raportarii stocul e deja postat. Creare: Form (acum manual din admin,
 * mai tarziu din aplicatia DXA - Bar prin SaleRecorder).
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $group = 'all';    // all | none (fara sesiune) | {sales_group_id}

    #[Url]
    public string $state = 'all';    // all | completed | cancelled

    #[Url]
    public string $method = 'all';   // all | cash | token | credit | benefit

    #[Url]
    public string $product = 'all';  // all | {menu_item_id}

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updated($name): void
    {
        if (in_array($name, ['group', 'state', 'method', 'product', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('group', 'state', 'method', 'product', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function cancel(int $id, string $reason): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            session()->flash('error', 'Motivul anulării este obligatoriu.');

            return;
        }

        $sale = Sale::with('group')->findOrFail($id);

        try {
            $sale->cancel(Auth::guard('admin')->id(), mb_substr($reason, 0, 250));
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        ActivityLogger::log('sales.sale_cancelled', 'A anulat vânzarea #'.$sale->id.' ('.number_format((float) $sale->total, 2, ',', '.').' lei): '.$reason);
        session()->flash('status', 'Vânzarea a fost anulată.');
    }

    private function filtered()
    {
        return Sale::query()
            ->when($this->group === 'none', fn ($q) => $q->whereNull('sales_group_id'))
            ->when($this->group !== 'all' && $this->group !== 'none', fn ($q) => $q->where('sales_group_id', (int) $this->group))
            ->when($this->state !== 'all', fn ($q) => $q->where('status', $this->state))
            ->when($this->method !== 'all', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $this->method)))
            ->when($this->product !== 'all', fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('menu_item_id', (int) $this->product)))
            ->when($this->dateFrom !== '', fn ($q) => $q->where('sold_at', '>=', Carbon::parse($this->dateFrom)->startOfDay()))
            ->when($this->dateTo !== '', fn ($q) => $q->where('sold_at', '<=', Carbon::parse($this->dateTo)->endOfDay()));
    }

    public function render()
    {
        $openSessions = SalesGroup::query()->open()
            ->with('party')
            ->withCount(['sales as sales_count' => fn ($q) => $q->completed()])
            ->withSum(['sales as sales_revenue' => fn ($q) => $q->completed()], 'total')
            ->orderByDesc('id')
            ->get();

        $sales = $this->filtered()
            ->with(['group.party', 'lines.menuItem', 'payments', 'creator', 'customer'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(15);

        // Rezumat pe ce e filtrat (doar vanzari finalizate; cele anulate nu conteaza in totaluri).
        $completedIds = $this->filtered()->where('status', 'completed')->select('sales.id');

        $summary = [
            'count' => $this->filtered()->where('status', 'completed')->count(),
            'revenue' => round((float) $this->filtered()->where('status', 'completed')->sum('total'), 2),
            'payments' => SalePayment::query()
                ->whereIn('sale_id', $completedIds)
                ->groupBy('method')
                ->selectRaw('method, SUM(amount) as amount, SUM(tokens) as tokens')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->method => ['amount' => round((float) $r->amount, 2), 'tokens' => (int) $r->tokens]])
                ->all(),
        ];

        $groups = SalesGroup::with('party')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.admin.sales.index', [
            'openSessions' => $openSessions,
            'sales' => $sales,
            'summary' => $summary,
            'groups' => $groups,
            'menuItems' => MenuItem::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
