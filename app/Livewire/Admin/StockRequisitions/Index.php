<?php

namespace App\Livewire\Admin\StockRequisitions;

use App\Models\StockRequisition;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Bar - necesare)
 *
 * Lista necesarelor (liste de cumparaturi pe stock_items). Creare/editare se
 * face in pagina separata Form (repeater de linii, ca la reteta din meniu).
 * Necesarul se "rezolva" din Raportari (StockReport::addEntry() actualizeaza
 * qty_received si statusul) — aici doar il vedem si il inchidem manual.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $state = 'all'; // all | open | fulfilled | closed

    // Un singur necesar expandat deodata (detalii + linii).
    public ?int $expandedId = null;

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function updated($name): void
    {
        if (in_array($name, ['search', 'state'], true)) {
            $this->resetPage();
            $this->expandedId = null;
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state');
        $this->resetPage();
    }

    /** Inchidere manuala (ex. anulat / nu mai e nevoie). Nu sterge nimic si nu atinge stocul. */
    public function close(int $id): void
    {
        $requisition = StockRequisition::findOrFail($id);
        $requisition->update(['status' => 'closed']);

        ActivityLogger::log('stock.requisition_closed', 'A închis necesarul „'.$requisition->label.'".');
        session()->flash('status', 'Necesarul a fost închis.');
    }

    /**
     * Redeschidere: statusul se recalculeaza din liniile curente (deschis daca
     * mai lipseste ceva, rezolvat daca totul a fost deja primit).
     */
    public function reopen(int $id): void
    {
        $requisition = StockRequisition::findOrFail($id);
        $requisition->status = 'open';
        $requisition->save();
        $requisition->refreshStatus();

        ActivityLogger::log('stock.requisition_reopened', 'A redeschis necesarul „'.$requisition->label.'".');
        session()->flash('status', 'Necesarul a fost redeschis.');
    }

    public function delete(int $id): void
    {
        $requisition = StockRequisition::findOrFail($id);

        // La fel ca la produsele de stoc: blocam doar cand exista istoric REAL,
        // adica cel putin o linie a primit deja marfa printr-o raportare. Fara
        // asta, e doar o lista de cumparaturi neonorata - se poate sterge.
        if ($requisition->items()->where('qty_received', '>', 0)->exists()) {
            session()->flash('error', 'Necesarul „'.$requisition->label.'" are deja raportări asociate — nu poate fi șters. Îl poți închide în schimb.');

            return;
        }

        $label = $requisition->label;

        DB::transaction(function () use ($requisition) {
            $requisition->items()->delete();
            $requisition->delete();
        });

        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }

        ActivityLogger::log('stock.requisition_deleted', 'A șters necesarul „'.$label.'".');
        session()->flash('status', 'Necesarul a fost șters.');
    }

    public function render()
    {
        $requisitions = StockRequisition::query()
            ->with(['items.stockItem', 'party', 'creator'])
            ->when($this->search !== '', fn ($q) => $q->where('label', 'like', '%'.$this->search.'%'))
            ->when(
                in_array($this->state, array_keys(StockRequisition::STATUSES), true),
                fn ($q) => $q->where('status', $this->state)
            )
            // Cele deschise primele (sunt cele la care mai ai de lucru), apoi cele mai noi.
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'fulfilled' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.stock-requisitions.index', [
            'requisitions' => $requisitions,
            'statuses' => StockRequisition::STATUSES,
        ]);
    }
}
