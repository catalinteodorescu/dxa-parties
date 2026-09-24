<?php

namespace App\Livewire\Admin\StockRequisitions;

use App\Livewire\Admin\Stocks\Index as StocksIndex;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockRequisition;
use App\Services\ActivityLogger;
use App\Services\StockRequisitionPdfExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Bar - necesare)
 *
 * Creare/editare necesar: denumire + petrecere (optional) + linii
 * (stock_item + cantitate ceruta). Acelasi pattern de repeater ca la reteta
 * din Meniu → Form (rand gol mereu la coada, dropdown-select, cantitate cu
 * unitatea afisata), plus <x-qty-helper> pe fiecare linie cu unitate != buc.
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    public ?StockRequisition $requisition = null;

    public string $label = '';

    // Denumirea implicita precompletata la un necesar nou ("5 / 24.09.2026"). Daca ramane neschimbata
    // pana la salvare, se recalculeaza cu id-ul real (estimarea din formular poate fi depasita).
    public string $autoLabel = '';

    // String (nu ?int) ca sa suporte direct valoarea goala din x-select ("Fara petrecere").
    public string $party_id = '';

    // [['stock_item_id' => int|string|null, 'qty' => string], ...]. Ramane
    // mereu un rand gol la coada pt. adaugare rapida — vezi syncLineRows().
    public array $lines = [];

    // Mesaj informativ dupa "Adauga produsele sub minim" (ex. nu e nimic de adaugat).
    public ?string $lowStockMessage = null;

    // Mesaj informativ dupa "Sugereaza cantitati" (ex. nu exista istoric).
    public ?string $suggestQtyMessage = null;

    public function mount(?StockRequisition $requisition = null): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        if ($requisition && $requisition->exists) {
            $this->requisition = $requisition;
            $this->label = $requisition->label;
            $this->party_id = $requisition->party_id ? (string) $requisition->party_id : '';

            $this->lines = $requisition->items()->with('stockItem')->get()
                ->sortBy(fn ($item) => mb_strtolower($item->stockItem->name))
                ->map(fn ($item) => [
                    'stock_item_id' => $item->stock_item_id,
                    'qty' => $this->plain((float) $item->qty_requested),
                ])
                ->values()
                ->all();
        } else {
            $this->autoLabel = StockRequisition::defaultLabel();
            $this->label = $this->autoLabel;
        }

        $this->syncLineRows();
    }

    /** Cantitate ca text pt. input (punct zecimal, fara zerouri de prisos): 12.500 -> "12.5". */
    private function plain(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    /** Pastreaza mereu un rand gol la coada listei, pt. adaugare rapida. */
    private function syncLineRows(): void
    {
        $last = end($this->lines);

        if ($last === false || ! empty($last['stock_item_id'])) {
            $this->lines[] = ['stock_item_id' => null, 'qty' => ''];
        }
    }

    public function updated($name): void
    {
        $this->lowStockMessage = null;
        $this->suggestQtyMessage = null;

        if (preg_match('/^lines\.\d+\.stock_item_id$/', $name)) {
            $this->syncLineRows();
        }
    }

    /** Cantitatea deja primita (din Raportari) pt. un produs al acestui necesar. Mereu din DB, nu din starea formularului. */
    private function receivedFor(int $stockItemId): float
    {
        if (! $this->requisition) {
            return 0.0;
        }

        return (float) $this->requisition->items()
            ->where('stock_item_id', $stockItemId)
            ->value('qty_received');
    }

    public function removeLine(int $i): void
    {
        $stockItemId = (int) ($this->lines[$i]['stock_item_id'] ?? 0);

        // Linia care a primit deja marfa nu se scoate — pastram istoricul (vezi si save()).
        if ($stockItemId && $this->receivedFor($stockItemId) > 0) {
            $this->addError('lines', 'Produsul are deja cantități primite — nu poate fi scos din necesar.');

            return;
        }

        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
        $this->syncLineRows();
    }

    /**
     * Adauga liniile pentru toate produsele active aflate la/sub pragul minim si
     * inca absente din lista, precompletate cu PRAGUL MINIM ca si cantitate
     * (StockItem::suggestedRequisitionQty()): dupa aprovizionare stocul urca peste
     * prag cu o marja egala cu pragul, deci petrecerea consuma fara sa coboare
     * iar sub prag. Doar daca pragul e 0 (nu iese nicio sugestie) cantitatea
     * ramane goala, ca sa fie completata constient.
     */
    public function addLowStock(): void
    {
        $chosen = collect($this->lines)
            ->pluck('stock_item_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $low = StockItem::active()
            ->whereNotNull('min_stock')
            ->whereColumn('stock_qty', '<=', 'min_stock')
            ->when($chosen, fn ($q) => $q->whereNotIn('id', $chosen))
            ->ordered()
            ->get();

        if ($low->isEmpty()) {
            $this->lowStockMessage = 'Nu există produse sub minim care să nu fie deja în listă.';

            return;
        }

        // Scoatem randul (complet) gol de la coada; syncLineRows() il repune dupa adaugare.
        $this->lines = array_values(array_filter(
            $this->lines,
            fn ($l) => ! empty($l['stock_item_id']) || ($l['qty'] ?? '') !== ''
        ));

        foreach ($low as $stockItem) {
            $suggested = $stockItem->suggestedRequisitionQty();

            $this->lines[] = [
                'stock_item_id' => $stockItem->id,
                'qty' => $suggested > 0 ? $this->plain($suggested) : '',
            ];
        }

        $this->lowStockMessage = 'Am adăugat '.$low->count().($low->count() === 1 ? ' produs' : ' produse').' aflate la sau sub pragul minim, cu cantitatea egală cu pragul.';

        $this->syncLineRows();
    }

    /** Rotunjire cu marjă (10%) peste media brută de consum, ca necesarul să nu iasă chiar "pe muchie". */
    private function suggestedQtyFromAverage(float $avg, string $unit): float
    {
        if ($avg <= 0) {
            return 0.0;
        }

        $withMargin = $avg * 1.1;

        return match ($unit) {
            // "buc" e discret - nu are sens 3,2 bucati.
            'buc' => ceil($withMargin),
            // l/kg au de obicei cantitati mari - o zecime e o granularitate rezonabila.
            'l', 'kg' => ceil($withMargin * 10) / 10,
            // ml/g raman intregi (cantitatile tipice sunt oricum in sute/mii).
            default => ceil($withMargin),
        };
    }

    /**
     * DXA: adaugat (Bar - necesare) — "Sugerează cantități" pe baza istoricului
     * de consum (vânzări + pierderi din raportări FINALIZATE) al TUTUROR
     * petrecerilor anterioare de același `kind` (basic/festival) ca petrecerea
     * aleasă pe acest necesar. Cantitatea sugerată = media de consum per
     * produs (peste toate petrecerile calificate, chiar dacă un produs
     * lipsește din unele) + marjă de 10%, rotunjită sensibil pe unitate.
     *
     * Nu suprascrie liniile deja completate manual (la fel ca addLowStock()).
     * Un produs cu istoric care rotunjește totuși la 0 rămâne cu cantitate
     * goală, nu 0 (validarea cere min:0.001).
     */
    public function suggestFromHistory(): void
    {
        if ($this->party_id === '') {
            return;
        }

        $party = Party::find((int) $this->party_id);

        if (! $party) {
            $this->suggestQtyMessage = 'Petrecerea aleasă nu mai există.';

            return;
        }

        // Alte petreceri (nu aceasta), de acelasi tip, care au macar o raportare finalizata.
        $reports = StockReport::query()
            ->where('status', 'finalized')
            ->whereNotNull('party_id')
            ->where('party_id', '!=', $party->id)
            ->whereHas('party', fn ($q) => $q->where('kind', $party->kind))
            ->get(['id', 'party_id']);

        $partyCount = $reports->pluck('party_id')->unique()->count();

        if ($partyCount === 0) {
            $this->suggestQtyMessage = 'Nu există raportări finalizate pentru alte petreceri de tipul „'
                .($party->kind === 'festival' ? 'festival' : 'petrecere').'" — nu am de unde calcula o medie.';

            return;
        }

        // Consum real = iesiri (out) din raportarile calificate: vanzari explodate prin reteta + pierderi.
        // NU intrari (in) - alea sunt aprovizionare, nu ce s-a consumat efectiv.
        $consumedByItem = StockMovement::query()
            ->where('type', 'out')
            ->whereIn('report_id', $reports->pluck('id'))
            ->selectRaw('stock_item_id, SUM(qty) as total_qty')
            ->groupBy('stock_item_id')
            ->pluck('total_qty', 'stock_item_id');

        if ($consumedByItem->isEmpty()) {
            $this->suggestQtyMessage = 'Petrecerile anterioare de acest tip nu au niciun consum înregistrat — nu am de unde calcula o medie.';

            return;
        }

        $chosen = collect($this->lines)
            ->pluck('stock_item_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $stockItems = StockItem::query()
            ->whereIn('id', $consumedByItem->keys())
            ->whereNotIn('id', $chosen)
            ->ordered()
            ->get();

        if ($stockItems->isEmpty()) {
            $this->suggestQtyMessage = 'Toate produsele din istoricul acestui tip de petrecere sunt deja în listă.';

            return;
        }

        // Scoatem randul (complet) gol de la coada; syncLineRows() il repune dupa adaugare.
        $this->lines = array_values(array_filter(
            $this->lines,
            fn ($l) => ! empty($l['stock_item_id']) || ($l['qty'] ?? '') !== ''
        ));

        foreach ($stockItems as $stockItem) {
            $avg = (float) $consumedByItem[$stockItem->id] / $partyCount;
            $suggested = $this->suggestedQtyFromAverage($avg, $stockItem->unit);

            $this->lines[] = [
                'stock_item_id' => $stockItem->id,
                'qty' => $suggested > 0 ? $this->plain($suggested) : '',
            ];
        }

        $this->suggestQtyMessage = 'Am adăugat '.$stockItems->count().($stockItems->count() === 1 ? ' produs' : ' produse')
            .', cu cantitatea sugerată pe baza mediei de consum din '.$partyCount
            .($partyCount === 1 ? ' petrecere anterioară' : ' petreceri anterioare').' de același tip.';

        $this->syncLineRows();
    }

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'party_id' => ['nullable', 'exists:parties,id'],
            // Randurile complet goale (cel de la coada) sunt permise; daca ai ales un
            // produs, cantitatea devine obligatorie si invers. distinct: un produs
            // poate aparea o singura data intr-un necesar (unique in DB).
            'lines.*.stock_item_id' => ['nullable', 'required_with:lines.*.qty', 'distinct', 'exists:stock_items,id'],
            'lines.*.qty' => ['nullable', 'required_with:lines.*.stock_item_id', 'numeric', 'min:0.001'],
        ];
    }

    protected function messages(): array
    {
        return [
            'label.required' => 'Denumirea este obligatorie.',
            'party_id.exists' => 'Petrecerea aleasă nu mai există.',
            'lines.*.stock_item_id.required_with' => 'Alege produsul.',
            'lines.*.stock_item_id.distinct' => 'Produsul e adăugat de două ori.',
            'lines.*.stock_item_id.exists' => 'Produsul ales nu mai există.',
            'lines.*.qty.required_with' => 'Completează cantitatea.',
            'lines.*.qty.numeric' => 'Cantitatea trebuie să fie un număr.',
            'lines.*.qty.min' => 'Cantitatea trebuie să fie mai mare ca 0.',
        ];
    }

    /**
     * Export PDF — disponibil doar la editare (un necesar nesalvat inca nu are
     * ce exporta; vezi StockRequisitionPdfExporter). Butonul apare doar atunci
     * in formular.
     */
    public function exportPdf()
    {
        abort_unless($this->requisition && $this->requisition->exists, 404);

        return StockRequisitionPdfExporter::stream($this->requisition);
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $this->validate();

        $filled = collect($this->lines)
            ->filter(fn ($l) => ! empty($l['stock_item_id']) && ($l['qty'] ?? '') !== '')
            ->values();

        if ($filled->isEmpty()) {
            $this->addError('lines', 'Adaugă cel puțin un produs în necesar.');

            return;
        }

        $isEditing = $this->requisition && $this->requisition->exists;
        $partyId = $this->party_id !== '' ? (int) $this->party_id : null;

        $requisition = DB::transaction(function () use ($isEditing, $partyId, $filled) {
            if ($isEditing) {
                $requisition = $this->requisition;
                $requisition->update(['label' => $this->label, 'party_id' => $partyId]);
            } else {
                $requisition = StockRequisition::create([
                    'label' => $this->label,
                    'status' => 'open',
                    'party_id' => $partyId,
                    'created_by' => Auth::guard('admin')->id(),
                ]);

                // Denumire lasata pe implicit => chiar numarul real al documentului.
                if ($this->autoLabel !== '' && trim($this->label) === $this->autoLabel) {
                    $requisition->update(['label' => StockRequisition::defaultLabel($requisition->id, $requisition->created_at)]);
                }
            }

            $this->syncItems($requisition, $filled->all());

            // Liniile s-au schimbat: open <-> fulfilled se recalculeaza (un necesar
            // inchis manual ramane inchis - vezi StockRequisition::refreshStatus()).
            $requisition->refreshStatus();

            return $requisition;
        });

        if ($isEditing) {
            ActivityLogger::log('stock.requisition_updated', 'A modificat necesarul '.$requisition->numberedLabel().'.');
            session()->flash('status', 'Necesarul a fost actualizat.');
        } else {
            ActivityLogger::log('stock.requisition_created', 'A creat necesarul '.$requisition->numberedLabel().'.');
            session()->flash('status', 'Necesarul a fost creat.');
        }

        $this->redirectRoute('admin.stock-requisitions.index', navigate: true);
    }

    /**
     * Sincronizare pe diff, NU stergere+recreare ca la reteta: liniile pastreaza
     * qty_received (actualizat de Raportari), deci nu le putem recrea de la zero.
     * O linie care a primit deja marfa nu se sterge niciodata.
     */
    private function syncItems(StockRequisition $requisition, array $filled): void
    {
        $existing = $requisition->items()->get()->keyBy('stock_item_id');
        $keptIds = [];

        foreach ($filled as $line) {
            $stockItemId = (int) $line['stock_item_id'];
            $qty = (float) $line['qty'];

            if ($existing->has($stockItemId)) {
                $existing[$stockItemId]->update(['qty_requested' => $qty]);
            } else {
                $requisition->items()->create([
                    'stock_item_id' => $stockItemId,
                    'qty_requested' => $qty,
                ]);
            }

            $keptIds[] = $stockItemId;
        }

        foreach ($existing as $stockItemId => $item) {
            if (! in_array((int) $stockItemId, $keptIds, true) && (float) $item->qty_received <= 0) {
                $item->delete();
            }
        }
    }

    public function render()
    {
        $lineIds = collect($this->lines)
            ->pluck('stock_item_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        // Produsele active + cele deja din lista (chiar daca intre timp au fost dezactivate).
        $stockItems = StockItem::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $lineIds))
            ->ordered()
            ->get();

        // Doar petrecerile cu data de azi inainte (nu se face necesar pt. una trecuta). Se
        // compara DATA de start, nu ends_at: o petrecere de ieri seara terminata azi la 04:00
        // e deja trecuta. Exceptie: un festival pe mai multe zile aflat in desfasurare.
        $upcoming = fn ($q) => $q
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere(fn ($f) => $f->where('kind', 'festival')->where('ends_at', '>', now()));

        $parties = Party::query()
            ->where($upcoming)
            ->orderBy('starts_at')
            ->get();

        // La editare: daca necesarul e legat de o petrecere care intre timp a trecut, o pastram in lista.
        if ($this->party_id !== '' && ! $parties->contains('id', (int) $this->party_id)) {
            $current = Party::find((int) $this->party_id);

            if ($current) {
                $parties->prepend($current);
            }
        }

        return view('livewire.admin.stock-requisitions.form', [
            'stockItems' => $stockItems,
            'units' => StocksIndex::UNITS,
            'parties' => $parties,
            // stock_item_id => qty_received, mereu din DB (nu din starea publica a formularului).
            'received' => $this->requisition
                ? $this->requisition->items()->pluck('qty_received', 'stock_item_id')
                    ->map(fn ($v) => (float) $v)->all()
                : [],
        ]);
    }
}
