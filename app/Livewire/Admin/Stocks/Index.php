<?php

namespace App\Livewire\Admin\Stocks;

use App\Models\StockItem;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const UNITS = [
        'buc' => 'bucată',
        'ml' => 'ml',
        'l' => 'litru',
        'kg' => 'kg',
        'g' => 'g',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $state = 'all'; // all | active | inactive

    #[Url(as: 'sub_min')]
    public bool $lowOnly = false;

    // Randul cu detalii expandat (prag/cost/valoare + istoric miscari) - un
    // singur produs deodata, ca sa nu incarcam miscari pt. toata pagina.
    public ?int $expandedId = null;

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    // --- Modal creare/editare ---
    public bool $modalOpen = false;

    // Crescut la fiecare deschidere (openCreate/openEdit) - folosit ca wire:key
    // pe calculatoarele Alpine (x-qty-helper/x-cost-helper) din modal, ca sa
    // forteze Livewire sa le recreeze de la zero (inchise) de fiecare data cand
    // popup-ul se deschide, in loc sa pastreze starea Alpine ramasa deschisa
    // de la o folosire anterioara (modalul foloseste x-show, nu distruge DOM-ul).
    public int $formNonce = 0;

    public ?int $editingId = null;

    public string $name = '';

    public string $unit = 'buc';

    public string $min_stock = '';

    // Informativ, editabil oricand (spre deosebire de stoc initial) - vezi
    // StockItem::hasPackage()/packageDisplay(). Relevant doar pt. unitati
    // fractionale (ml/l/g/kg); fara sens la unitatea "buc".
    public string $package_label = '';

    public string $package_qty = '';

    public bool $is_active = true;

    // Doar la creare — stoc initial optional (vezi recordInitialStock pe model).
    public bool $hasInitialStock = false;

    public string $initial_qty = '';

    public string $initial_cost = '';

    // Multi-unit se cumpara la ambalaj (sticla de 700ml, 1L etc.), dar costul
    // se tine pe unitate (lei/ml) - lasam persoana sa introduca fie direct
    // costul unitar, fie costul total platit, si calculam noi impartirea.
    public string $initial_cost_mode = 'unit'; // unit | total

    // --- Modal corectare cost ---
    public bool $costModalOpen = false;

    public ?int $costEditingId = null;

    public string $costEditingName = '';

    public float $costEditingCurrentQty = 0;

    public string $costEditingUnit = '';

    public string $costEditingPackageQty = '';

    public string $new_cost = '';

    // unit: introduci direct lei/unitate. total: introduci valoarea totala a
    // stocului curent, iar noi impartim la stock_qty ca sa obtinem lei/unitate
    // (util cand stii cat a costat tot ce ai pe raft, nu cat costa 1 ml/buc).
    public string $new_cost_mode = 'unit';

    public string $new_cost_total = '';

    public string $cost_reason = '';

    // --- Modal corectare CANTITATE (ex. gresit stocul initial la creare) ---
    public bool $qtyModalOpen = false;

    public ?int $qtyEditingId = null;

    public string $qtyEditingName = '';

    public string $qtyEditingUnit = '';

    public string $qtyEditingPackageQty = '';

    public string $new_qty = '';

    public string $qty_reason = '';

    public function updated($name): void
    {
        if (in_array($name, ['search', 'state', 'lowOnly'], true)) {
            $this->resetPage();
            $this->expandedId = null;
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state', 'lowOnly');
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $exists = StockItem::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                        ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                        ->exists();

                    if ($exists) {
                        $fail('Există deja un produs de stoc cu acest nume.');
                    }
                },
            ],
            'unit' => ['required', 'string', Rule::in(array_keys(self::UNITS))],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'package_label' => ['nullable', 'string', 'max:60', 'required_with:package_qty'],
            'package_qty' => ['nullable', 'numeric', 'min:0.001', 'required_with:package_label'],
            'initial_qty' => [$this->hasInitialStock && ! $this->editingId ? 'required' : 'nullable', 'numeric', 'min:0.001'],
            'initial_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Denumirea este obligatorie.',
            'initial_qty.required' => 'Completează cantitatea inițială.',
            'package_label.required_with' => 'Completează și denumirea ambalajului.',
            'package_qty.required_with' => 'Completează și cantitatea per ambalaj.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('editingId', 'name', 'min_stock', 'package_label', 'package_qty', 'hasInitialStock', 'initial_qty', 'initial_cost');
        $this->initial_cost_mode = 'unit';
        $this->unit = 'buc';
        $this->is_active = true;
        $this->formNonce++;
        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $item = StockItem::findOrFail($id);

        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->unit = $item->unit;
        $this->min_stock = $item->min_stock !== null ? (string) $item->min_stock : '';
        $this->package_label = $item->package_label ?? '';
        $this->package_qty = $item->package_qty !== null ? (string) $item->package_qty : '';
        $this->is_active = $item->is_active;
        // Stoc initial nu se mai editeaza dupa creare - doar la creare.
        $this->hasInitialStock = false;
        $this->initial_qty = '';
        $this->initial_cost = '';
        $this->formNonce++;
        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function save(): void
    {
        $this->validate();

        $adminId = Auth::guard('admin')->id();
        $minStock = $this->min_stock !== '' ? (float) $this->min_stock : null;
        $packageLabel = $this->package_label !== '' ? $this->package_label : null;
        $packageQty = $this->package_qty !== '' ? (float) $this->package_qty : null;

        if ($this->editingId) {
            $item = StockItem::findOrFail($this->editingId);
            $item->update([
                'name' => $this->name,
                'unit' => $this->unit,
                'min_stock' => $minStock,
                'package_label' => $packageLabel,
                'package_qty' => $packageQty,
                'is_active' => $this->is_active,
            ]);

            ActivityLogger::log('stock.item_updated', 'A modificat produsul de stoc „'.$item->name.'".');
        } else {
            $item = StockItem::create([
                'name' => $this->name,
                'unit' => $this->unit,
                'min_stock' => $minStock,
                'package_label' => $packageLabel,
                'package_qty' => $packageQty,
                'is_active' => $this->is_active,
                'created_by' => $adminId,
            ]);

            if ($this->hasInitialStock && $this->initial_qty !== '') {
                $qty = (float) $this->initial_qty;
                $unitCost = null;

                if ($this->initial_cost !== '') {
                    $unitCost = $this->initial_cost_mode === 'total'
                        ? round(((float) $this->initial_cost) / $qty, 4) // preț total achiziție / cantitate = cost pe unitate
                        : (float) $this->initial_cost;
                }

                $item->recordInitialStock(
                    qty: $qty,
                    unitCost: $unitCost,
                    adminId: $adminId,
                    note: 'Stoc inițial la creare.',
                );
            }

            ActivityLogger::log('stock.item_created', 'A adăugat produsul de stoc „'.$item->name.'".');
        }

        $this->modalOpen = false;
        session()->flash('status', $this->editingId ? 'Produsul de stoc a fost actualizat.' : 'Produsul de stoc a fost adăugat.');
    }

    public function toggleActive(int $id): void
    {
        $item = StockItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        ActivityLogger::log(
            'stock.item_toggled',
            ($item->is_active ? 'A activat' : 'A dezactivat').' produsul de stoc „'.$item->name.'".',
        );
    }

    public function openCostModal(int $id): void
    {
        $item = StockItem::findOrFail($id);

        $this->costEditingId = $item->id;
        $this->costEditingName = $item->name;
        $this->costEditingCurrentQty = (float) $item->stock_qty;
        $this->costEditingUnit = $item->unit;
        $this->costEditingPackageQty = $item->package_qty !== null ? (string) $item->package_qty : '';
        $this->formNonce++;
        $this->new_cost = $item->avg_cost !== null ? (string) $item->avg_cost : '';
        $this->new_cost_mode = 'unit';
        $this->new_cost_total = '';
        $this->cost_reason = '';
        $this->resetValidation();
        $this->costModalOpen = true;
    }

    public function closeCostModal(): void
    {
        $this->costModalOpen = false;
    }

    public function saveCostAdjustment(): void
    {
        $item = StockItem::findOrFail($this->costEditingId);

        if ($this->new_cost_mode === 'total') {
            $this->validate([
                'new_cost_total' => ['required', 'numeric', 'min:0'],
                'cost_reason' => ['required', 'string', 'max:255'],
            ], [
                'new_cost_total.required' => 'Completează valoarea totală a stocului curent.',
                'cost_reason.required' => 'Motivul corecției este obligatoriu (pentru audit).',
            ]);

            if ((float) $item->stock_qty <= 0) {
                $this->addError('new_cost_total', 'Stocul curent este 0 — nu se poate împărți o valoare totală. Folosește „cost pe unitate".');

                return;
            }

            $unitCost = round(((float) $this->new_cost_total) / (float) $item->stock_qty, 4);
        } else {
            $this->validate([
                'new_cost' => ['required', 'numeric', 'min:0'],
                'cost_reason' => ['required', 'string', 'max:255'],
            ], [
                'new_cost.required' => 'Completează costul nou.',
                'cost_reason.required' => 'Motivul corecției este obligatoriu (pentru audit).',
            ]);

            $unitCost = (float) $this->new_cost;
        }

        $item->recordCostAdjustment($unitCost, Auth::guard('admin')->id(), $this->cost_reason);

        ActivityLogger::log('stock.item_cost_adjusted', 'A corectat manual costul produsului de stoc „'.$item->name.'" ('.$this->cost_reason.').');

        $this->costModalOpen = false;
        session()->flash('status', 'Costul a fost corectat.');
    }

    public function openQtyModal(int $id): void
    {
        $item = StockItem::findOrFail($id);

        $this->qtyEditingId = $item->id;
        $this->qtyEditingName = $item->name;
        $this->qtyEditingUnit = $item->unit;
        $this->qtyEditingPackageQty = $item->package_qty !== null ? (string) $item->package_qty : '';
        $this->formNonce++;
        $this->new_qty = (string) $item->stock_qty;
        $this->qty_reason = '';
        $this->resetValidation();
        $this->qtyModalOpen = true;
    }

    public function closeQtyModal(): void
    {
        $this->qtyModalOpen = false;
    }

    public function saveQtyAdjustment(): void
    {
        $this->validate([
            'new_qty' => ['required', 'numeric', 'min:0'],
            'qty_reason' => ['required', 'string', 'max:255'],
        ], [
            'new_qty.required' => 'Completează cantitatea corectă.',
            'qty_reason.required' => 'Motivul corecției este obligatoriu (pentru audit).',
        ]);

        $item = StockItem::findOrFail($this->qtyEditingId);
        $item->recordQuantityAdjustment((float) $this->new_qty, Auth::guard('admin')->id(), $this->qty_reason);

        ActivityLogger::log('stock.item_qty_adjusted', 'A corectat manual cantitatea produsului de stoc „'.$item->name.'" ('.$this->qty_reason.').');

        $this->qtyModalOpen = false;
        session()->flash('status', 'Cantitatea a fost corectată.');
    }

    public function delete(int $id): void
    {
        $item = StockItem::findOrFail($id);

        // Blocam stergerea doar daca exista miscari REALE de aprovizionare/
        // vanzare (in/out, venite din Raportare). Stocul initial si corectiile
        // manuale de cost nu sunt "istoric" propriu-zis - sunt doar valori
        // declarate la creare/ulterior, nu tranzactii legate de un necesar sau
        // o raportare - deci nu au rost sa blocheze stergerea unui produs pe
        // care abia l-ai creat si nu l-ai folosit inca real.
        if ($item->movements()->whereIn('type', ['in', 'out'])->exists()) {
            session()->flash('error', 'Produsul de stoc „'.$item->name.'" are deja intrări/ieșiri înregistrate — nu poate fi șters.');

            return;
        }

        if ($item->recipeLines()->exists()) {
            session()->flash('error', 'Produsul de stoc „'.$item->name.'" e folosit în rețeta unuia sau mai multor articole de meniu — nu poate fi șters.');

            return;
        }

        $name = $item->name;

        DB::transaction(function () use ($item) {
            // Sterge orice miscari ramase (doar initial/adjustment, verificat
            // mai sus) - altfel constrangerea FK (restrictOnDelete) ar bloca
            // stergerea produsului cat timp mai exista randuri in stock_movements.
            $item->movements()->delete();
            $item->delete();
        });

        ActivityLogger::log('stock.item_deleted', 'A șters produsul de stoc „'.$name.'".');
        session()->flash('status', 'Produsul de stoc a fost șters.');
    }

    /** Filtrele curente (cautare/stare/sub minim), reutilizate pt. lista paginata si pt. totalul agregat. */
    private function filteredQuery()
    {
        return StockItem::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->state === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->state === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->lowOnly, fn ($q) => $q->whereNotNull('min_stock')->whereColumn('stock_qty', '<=', 'min_stock'));
    }

    public function render()
    {
        $items = $this->filteredQuery()->orderBy('name')->paginate(15);

        // Valoarea totala a stocului (stoc x cost mediu), pe toate produsele
        // care corespund filtrelor curente - nu doar pagina afisata - si doar
        // cele cu cost cunoscut (altfel valoarea ar fi subestimata tacit).
        $totalStockValue = (clone $this->filteredQuery())
            ->whereNotNull('avg_cost')
            ->selectRaw('SUM(stock_qty * avg_cost) as total')
            ->value('total');

        $unknownCostCount = (clone $this->filteredQuery())->whereNull('avg_cost')->count();

        return view('livewire.admin.stocks.index', [
            'items' => $items,
            'units' => self::UNITS,
            'totalStockValue' => (float) ($totalStockValue ?? 0),
            'unknownCostCount' => $unknownCostCount,
        ]);
    }
}
