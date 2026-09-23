<?php

namespace App\Livewire\Admin\StockReports;

use App\Livewire\Admin\Stocks\Index as StocksIndex;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Models\StockReportLine;
use App\Models\SalesGroup;
use App\Services\SalesAggregator;
use App\Models\StockRequisition;
use App\Models\StockRequisitionItem;
use App\Services\ActivityLogger;
use App\Services\StockReportPdfExporter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Bar - raportari)
 *
 * Formular de Raportare (draft -> finalizat). Cat timp raportul e 'draft',
 * fiecare linie completa se salveaza AUTOMAT in stock_report_lines (staging) -
 * nimic real nu se scrie in stock_movements/stock_report_sales si nu se atinge
 * stock_qty/avg_cost. Abia la "Finalizeaza" (confirmare explicita, ireversibil)
 * StockReport::finalize() itereaza staging-ul si creeaza miscarile reale.
 *
 * 3 sectiuni in aceeasi pagina, fiecare cu propriul repeater (acelasi pattern
 * de "rand gol la coada" ca la Necesare/Reteta):
 *  - Intrari  (kind=entry):  produs de stoc + cantitate + cost/unitate optional;
 *             pot fi aduse dintr-un necesar deschis (grupate sub numele lui).
 *  - Vanzari  (kind=sale):   produs de meniu + cantitate (pretul e fix, din
 *             MenuItem; consumul de stoc se "explodeaza" prin reteta la finalizare).
 *  - Pierderi (kind=loss):   produs de stoc + cantitate + motiv (opțional).
 *
 * Sub formular, panoul "Impact asupra stocului" calculeaza NET, in memorie (fara
 * scriere), efectul cumulat al tuturor liniilor pe fiecare produs atins.
 *
 * Un raport finalizat se deschide read-only pe aceeasi ruta: continutul nu mai
 * vine din staging (golit la finalizare), ci din miscarile reale.
 */
#[Layout('layouts.admin')]
class Form extends Component
{
    public ?StockReport $report = null;

    public string $date = '';

    // String (nu ?int) ca sa suporte direct valoarea goala din x-select ("Fara petrecere").
    public string $party_id = '';

    // Sesiunea de vanzari (bar) adusa in sectiunea Vanzari - '' = niciunul. Doar grupuri
    // deschise; se inchide la finalizare. Vanzarile lui apar read-only, agregate pe produs.
    public string $sales_group_id = '';

    // Include si vanzarile simple (fara sesiune), neraportate. La finalizare se posteaza
    // toate cele existente in acel moment.
    public bool $include_loose_sales = false;

    public string $note = '';

    // Fiecare rand poarta 'line_id' (id din stock_report_lines) pt. autosave pe
    // diff: complet -> upsert linia, golit/scos -> delete linia din staging.
    // Intrari: pe langa produs/cantitate/cost, randurile aduse dintr-un necesar
    // poarta req_* (grupare vizuala + afisare cerut/ramas).
    public array $entries = [];

    public array $sales = [];

    public array $losses = [];

    // Aducere din necesar (Intrari): necesarul deschis selectat (panou transient).
    public string $importReqId = '';

    public ?string $importMessage = null;

    // Confirmarea explicita de finalizare (bifata in dialog inainte de finalize()).
    public bool $finalizeConfirm = false;

    // Un raport finalizat e doar de vizualizat - toate scrierile sunt blocate.
    public bool $readOnly = false;

    public function mount(?StockReport $report = null): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        if ($report && $report->exists) {
            $this->report = $report;
            $this->readOnly = $report->isFinalized();
            $this->date = $report->date->format('Y-m-d');
            $this->party_id = $report->party_id ? (string) $report->party_id : '';
            $this->sales_group_id = $report->sales_group_id ? (string) $report->sales_group_id : '';
            $this->include_loose_sales = (bool) $report->include_loose_sales;
            $this->note = (string) ($report->note ?? '');

            // Doar un draft are linii de staging; la finalizat continutul se
            // afiseaza read-only din miscarile reale (vezi render()).
            if ($report->isDraft()) {
                $this->loadDraftLines();
            }
        } else {
            $this->date = now()->format('Y-m-d');

            // Preincarcare dintr-un necesar (buton "Raporteaza din acest necesar"
            // din lista de Necesare -> create?requisition={id}). Creeaza draftul
            // si persista liniile aduse.
            $reqId = request()->integer('requisition');
            if ($reqId) {
                $this->importRequisition($reqId);
            }
        }

        $this->syncTrailingRows();
    }

    // ------------------------------------------------------------------
    // Incarcare / randuri goale
    // ------------------------------------------------------------------

    private function loadDraftLines(): void
    {
        $lines = $this->report->lines()->with(['requisitionItem.requisition'])->orderBy('id')->get();

        foreach ($lines as $line) {
            if ($line->kind === 'entry') {
                $ri = $line->requisitionItem;

                $this->entries[] = [
                    'line_id' => $line->id,
                    'stock_item_id' => $line->stock_item_id,
                    'qty' => $this->plain((float) $line->qty),
                    'unit_cost' => $line->unit_cost !== null ? $this->plainCost((float) $line->unit_cost) : '',
                    'requisition_item_id' => $line->requisition_item_id,
                    'req_id' => $ri?->requisition?->id,
                    'req_label' => $ri?->requisition?->label,
                    'req_requested' => $ri ? (float) $ri->qty_requested : null,
                    'req_remaining' => $ri ? $ri->remainingQty() : null,
                ];
            } elseif ($line->kind === 'sale') {
                $this->sales[] = [
                    'line_id' => $line->id,
                    'menu_item_id' => $line->menu_item_id,
                    'qty' => $this->plain((float) $line->qty),
                ];
            } else {
                $this->losses[] = [
                    'line_id' => $line->id,
                    'stock_item_id' => $line->stock_item_id,
                    'qty' => $this->plain((float) $line->qty),
                    'note' => (string) ($line->note ?? ''),
                ];
            }
        }
    }

    private function blankEntry(): array
    {
        return [
            'line_id' => null,
            'stock_item_id' => null,
            'qty' => '',
            'unit_cost' => '',
            'requisition_item_id' => null,
            'req_id' => null,
            'req_label' => null,
            'req_requested' => null,
            'req_remaining' => null,
        ];
    }

    private function blankSale(): array
    {
        return ['line_id' => null, 'menu_item_id' => null, 'qty' => ''];
    }

    private function blankLoss(): array
    {
        return ['line_id' => null, 'stock_item_id' => null, 'qty' => '', 'note' => ''];
    }

    /** Cate un rand liber gol la coada fiecarei sectiuni, pt. adaugare rapida. */
    private function syncTrailingRows(): void
    {
        // Intrari: garantam un singur rand LIBER (fara necesar) gol. Randurile
        // aduse dintr-un necesar nu se pun la socoteala aici.
        $freeEmpty = collect($this->entries)
            ->filter(fn ($r) => empty($r['requisition_item_id']) && empty($r['stock_item_id']))
            ->count();

        if ($freeEmpty === 0) {
            $this->entries[] = $this->blankEntry();
        }

        $lastSale = end($this->sales);
        if ($lastSale === false || ! empty($lastSale['menu_item_id'])) {
            $this->sales[] = $this->blankSale();
        }

        $lastLoss = end($this->losses);
        if ($lastLoss === false || ! empty($lastLoss['stock_item_id'])) {
            $this->losses[] = $this->blankLoss();
        }
    }

    /** Cantitate ca text pt. input (punct zecimal, fara zerouri de prisos): 12.500 -> "12.5". */
    private function plain(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private function plainCost(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    // ------------------------------------------------------------------
    // Autosave per linie
    // ------------------------------------------------------------------

    public function updated($name): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->importMessage = null;

        // Antetul: se persista doar daca raportul exista deja; altfel e capturat
        // la ensureReport() cand se salveaza prima linie.
        if ($name === 'sales_group_id') {
            $this->onSalesGroupChosen();

            return;
        }

        if ($name === 'include_loose_sales') {
            if ($this->include_loose_sales) {
                $this->ensureReport();
            }
            $this->persistHeader();

            return;
        }

        if (in_array($name, ['date', 'party_id', 'note'], true)) {
            $this->persistHeader();

            // Petrecere aleasa + niciun grup ales: propunem automat grupul deschis al petrecerii.
            if ($name === 'party_id' && $this->sales_group_id === '' && $this->party_id !== '') {
                $group = SalesGroup::open()
                    ->where('party_id', (int) $this->party_id)
                    ->whereNotIn('id', $this->claimedGroupIds())
                    ->orderBy('id')
                    ->first();

                if ($group) {
                    $this->sales_group_id = (string) $group->id;
                    $this->ensureReport();
                    $this->persistHeader();
                }
            }

            return;
        }

        if (preg_match('/^entries\.(\d+)\./', $name, $m)) {
            $this->syncRow('entries', (int) $m[1]);
        } elseif (preg_match('/^sales\.(\d+)\./', $name, $m)) {
            $this->syncRow('sales', (int) $m[1]);
        } elseif (preg_match('/^losses\.(\d+)\./', $name, $m)) {
            $this->syncRow('losses', (int) $m[1]);
        }
    }

    /** O linie e "completa" (merita persistata in staging) daca are produs + cantitate > 0. */
    private function rowFilled(string $section, array $row): bool
    {
        $qty = is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : 0.0;

        return match ($section) {
            'entries', 'losses' => ! empty($row['stock_item_id']) && $qty > 0,
            'sales' => ! empty($row['menu_item_id']) && $qty > 0,
            default => false,
        };
    }

    private function syncRow(string $section, int $i): void
    {
        if (! isset($this->{$section}[$i])) {
            return;
        }

        $row = $this->{$section}[$i];

        if ($this->rowFilled($section, $row)) {
            $this->persistRow($section, $i);
        } elseif (! empty($row['line_id'])) {
            // A fost completa, acum e incompleta -> o scoatem din staging, dar
            // pastram randul in UI ca sa poata fi completat la loc.
            $this->deleteLine((int) $row['line_id']);
            $this->{$section}[$i]['line_id'] = null;
        }

        $this->syncTrailingRows();
    }

    private function rowToLineData(string $section, array $row): array
    {
        $qty = (float) $row['qty'];

        return match ($section) {
            'entries' => [
                'kind' => 'entry',
                'stock_item_id' => (int) $row['stock_item_id'],
                'menu_item_id' => null,
                'requisition_item_id' => ! empty($row['requisition_item_id']) ? (int) $row['requisition_item_id'] : null,
                'qty' => $qty,
                'unit_cost' => (isset($row['unit_cost']) && $row['unit_cost'] !== '' && is_numeric($row['unit_cost'])) ? (float) $row['unit_cost'] : null,
                'note' => null,
            ],
            'sales' => [
                'kind' => 'sale',
                'stock_item_id' => null,
                'menu_item_id' => (int) $row['menu_item_id'],
                'requisition_item_id' => null,
                'qty' => $qty,
                'unit_cost' => null,
                'note' => null,
            ],
            'losses' => [
                'kind' => 'loss',
                'stock_item_id' => (int) $row['stock_item_id'],
                'menu_item_id' => null,
                'requisition_item_id' => null,
                'qty' => $qty,
                'unit_cost' => null,
                'note' => ($row['note'] ?? '') !== '' ? $row['note'] : null,
            ],
            default => [],
        };
    }

    private function persistRow(string $section, int $i): void
    {
        $report = $this->ensureReport();
        $row = $this->{$section}[$i];

        $data = $this->rowToLineData($section, $row);

        $line = ! empty($row['line_id']) ? StockReportLine::find($row['line_id']) : null;
        if (! $line) {
            $line = new StockReportLine;
        }

        $line->fill($data);
        $line->report_id = $report->id;
        $line->save();

        $this->{$section}[$i]['line_id'] = $line->id;
    }

    private function deleteLine(int $lineId): void
    {
        if ($this->report && $this->report->exists) {
            $this->report->lines()->whereKey($lineId)->delete();
        }
    }

    /** Grupuri deja alese de ALT draft (un grup se poate consuma intr-o singura raportare). */
    private function claimedGroupIds(): array
    {
        return StockReport::query()
            ->where('status', 'draft')
            ->whereNotNull('sales_group_id')
            ->when($this->report && $this->report->exists, fn ($q) => $q->where('id', '!=', $this->report->id))
            ->pluck('sales_group_id')
            ->all();
    }

    private function onSalesGroupChosen(): void
    {
        if ($this->sales_group_id !== '') {
            $group = SalesGroup::open()->find((int) $this->sales_group_id);

            if (! $group || in_array($group->id, $this->claimedGroupIds(), true)) {
                $this->sales_group_id = '';
                $this->importMessage = 'Sesiunea aleasă nu mai e disponibilă (e închisă sau folosită într-un alt draft).';

                return;
            }

            // Persistam imediat (creeaza draftul daca nu exista): alegerea grupului
            // trebuie sa supravietuiasca unui refresh, ca si liniile.
            $this->ensureReport();
        }

        $this->persistHeader();
    }

    private function persistHeader(): void
    {
        if (! $this->report || ! $this->report->exists) {
            return;
        }

        $this->report->update([
            'date' => $this->date !== '' ? $this->date : now()->format('Y-m-d'),
            'party_id' => $this->party_id !== '' ? (int) $this->party_id : null,
            'sales_group_id' => $this->sales_group_id !== '' ? (int) $this->sales_group_id : null,
            'include_loose_sales' => $this->include_loose_sales,
            'note' => $this->note !== '' ? $this->note : null,
        ]);
    }

    /** Creeaza (lazy) raportul draft la nevoie - ca sa nu ramana drafturi goale daca userul pleaca fara sa adauge nimic. */
    private function ensureReport(): StockReport
    {
        if ($this->report && $this->report->exists) {
            return $this->report;
        }

        $this->report = StockReport::create([
            'date' => $this->date !== '' ? $this->date : now()->format('Y-m-d'),
            'status' => 'draft',
            'party_id' => $this->party_id !== '' ? (int) $this->party_id : null,
            'sales_group_id' => $this->sales_group_id !== '' ? (int) $this->sales_group_id : null,
            'include_loose_sales' => $this->include_loose_sales,
            'note' => $this->note !== '' ? $this->note : null,
            'created_by' => Auth::guard('admin')->id(),
        ]);

        return $this->report;
    }

    // ------------------------------------------------------------------
    // Actiuni de rand (scoatere manuala)
    // ------------------------------------------------------------------

    public function removeEntry(int $i): void
    {
        $this->removeRow('entries', $i);
    }

    public function removeSale(int $i): void
    {
        $this->removeRow('sales', $i);
    }

    public function removeLoss(int $i): void
    {
        $this->removeRow('losses', $i);
    }

    private function removeRow(string $section, int $i): void
    {
        if ($this->readOnly || ! isset($this->{$section}[$i])) {
            return;
        }

        $row = $this->{$section}[$i];
        if (! empty($row['line_id'])) {
            $this->deleteLine((int) $row['line_id']);
        }

        unset($this->{$section}[$i]);
        $this->{$section} = array_values($this->{$section});
        $this->syncTrailingRows();
    }

    // ------------------------------------------------------------------
    // Aducere din necesar (Intrari)
    // ------------------------------------------------------------------

    public function importRequisition(int $reqId): void
    {
        if ($this->readOnly) {
            return;
        }

        $req = StockRequisition::with('items')->find($reqId);
        if (! $req) {
            return;
        }

        $already = $this->importedRequisitionItemIds();
        $added = 0;

        foreach ($req->items as $item) {
            if (in_array($item->id, $already, true)) {
                continue;
            }

            $remaining = $item->remainingQty();
            if ($remaining <= 0) {
                continue;
            }

            $this->pushImportedEntry($req, $item, $remaining);
            $added++;
        }

        $this->importReqId = '';
        $this->importMessage = $added > 0
            ? 'Am adus '.$added.($added === 1 ? ' produs' : ' produse').' din „'.$req->label.'".'
            : 'Nu e nimic de adus din „'.$req->label.'" (tot ce lipsea a fost deja adus sau primit).';

        $this->syncTrailingRows();
    }

    public function importRequisitionItem(int $reqItemId): void
    {
        if ($this->readOnly) {
            return;
        }

        $item = StockRequisitionItem::with('requisition')->find($reqItemId);
        if (! $item || ! $item->requisition) {
            return;
        }

        if (in_array($item->id, $this->importedRequisitionItemIds(), true)) {
            return;
        }

        $remaining = $item->remainingQty();
        if ($remaining <= 0) {
            return;
        }

        $this->pushImportedEntry($item->requisition, $item, $remaining);
        $this->syncTrailingRows();
    }

    public function removeRequisitionGroup(int $reqId): void
    {
        if ($this->readOnly) {
            return;
        }

        foreach ($this->entries as $row) {
            if ((int) ($row['req_id'] ?? 0) === $reqId && ! empty($row['line_id'])) {
                $this->deleteLine((int) $row['line_id']);
            }
        }

        $this->entries = array_values(array_filter(
            $this->entries,
            fn ($r) => (int) ($r['req_id'] ?? 0) !== $reqId
        ));

        $this->syncTrailingRows();
    }

    /** @return int[] */
    private function importedRequisitionItemIds(): array
    {
        return collect($this->entries)
            ->pluck('requisition_item_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function pushImportedEntry(StockRequisition $req, StockRequisitionItem $item, float $remaining): void
    {
        $this->entries[] = [
            'line_id' => null,
            'stock_item_id' => $item->stock_item_id,
            'qty' => $this->plain($remaining),
            'unit_cost' => '',
            'requisition_item_id' => $item->id,
            'req_id' => $req->id,
            'req_label' => $req->label,
            'req_requested' => (float) $item->qty_requested,
            'req_remaining' => $remaining,
        ];

        // "Filled" (produs + qty>0) -> persistam imediat in staging.
        $this->persistRow('entries', array_key_last($this->entries));
    }

    // ------------------------------------------------------------------
    // Salvare draft / Finalizare
    // ------------------------------------------------------------------

    /** Buton de siguranta: forteaza sincronizarea tuturor liniilor + antetului (autosave-ul face deja asta continuu). */
    public function saveDraft(): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->flushDraft();

        if (! $this->report || ! $this->report->exists) {
            $this->importMessage = 'Nu e nimic de salvat încă — adaugă cel puțin o linie.';

            return;
        }

        session()->flash('status', 'Draftul a fost salvat.');
    }

    private function flushDraft(): void
    {
        foreach (['entries', 'sales', 'losses'] as $section) {
            foreach ($this->{$section} as $i => $row) {
                if ($this->rowFilled($section, $row)) {
                    $this->persistRow($section, $i);
                } elseif (! empty($row['line_id'])) {
                    $this->deleteLine((int) $row['line_id']);
                    $this->{$section}[$i]['line_id'] = null;
                }
            }
        }

        $this->persistHeader();
    }

    /** Export PDF — disponibil doar pentru raportări finalizate (vezi StockReportPdfExporter). */
    public function exportPdf()
    {
        abort_unless($this->readOnly && $this->report, 404);

        return StockReportPdfExporter::stream($this->report);
    }

    public function finalize(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        if ($this->readOnly) {
            return;
        }

        // Ne asiguram ca staging-ul reflecta exact ce e in formular chiar acum
        // (in caz ca ultimul input debounced n-a apucat sa faca round-trip).
        $this->flushDraft();

        if (! $this->report || ! $this->report->exists
            || ($this->report->lines()->count() === 0 && ! $this->report->hasSalesToPost())) {
            $this->addError('finalize', 'Adaugă cel puțin o linie sau alege o sesiune de vânzări cu vânzări înainte de a finaliza.');

            return;
        }

        if (! $this->finalizeConfirm) {
            $this->addError('finalize', 'Bifează confirmarea înainte de a finaliza.');

            return;
        }

        $report = $this->report;
        $report->finalize(Auth::guard('admin')->id());

        ActivityLogger::log('stock.report_finalized', 'A finalizat raportarea din '.$report->date->format('d.m.Y').'.');
        session()->flash('status', 'Raportarea a fost finalizată.');

        $this->redirectRoute('admin.stock-reports.index', navigate: true);
    }

    // ------------------------------------------------------------------
    // Impact (calcul in memorie, fara scriere)
    // ------------------------------------------------------------------

    /**
     * Efectul NET cumulat al tuturor liniilor curente pe fiecare produs de stoc
     * atins: intrari (+qty), pierderi (-qty), vanzari explodate prin reteta
     * (-qty x reteta pe fiecare ingredient). 100% in memorie - nu scrie nimic.
     *
     * @return array<int, array{name:string, unit:string, current:float, net:float, result:float, package:?string}>
     */
    private function computeImpact(): array
    {
        $delta = [];

        foreach ($this->entries as $row) {
            if (! empty($row['stock_item_id']) && is_numeric($row['qty']) && (float) $row['qty'] > 0) {
                $id = (int) $row['stock_item_id'];
                $delta[$id] = ($delta[$id] ?? 0.0) + (float) $row['qty'];
            }
        }

        foreach ($this->losses as $row) {
            if (! empty($row['stock_item_id']) && is_numeric($row['qty']) && (float) $row['qty'] > 0) {
                $id = (int) $row['stock_item_id'];
                $delta[$id] = ($delta[$id] ?? 0.0) - (float) $row['qty'];
            }
        }

        $saleRows = collect($this->sales)
            ->filter(fn ($r) => ! empty($r['menu_item_id']) && is_numeric($r['qty']) && (float) $r['qty'] > 0);

        if ($saleRows->isNotEmpty()) {
            $menuItems = MenuItem::with('recipeLines')
                ->whereIn('id', $saleRows->pluck('menu_item_id')->map(fn ($v) => (int) $v)->unique()->all())
                ->get()
                ->keyBy('id');

            foreach ($saleRows as $row) {
                $mi = $menuItems->get((int) $row['menu_item_id']);
                if (! $mi) {
                    continue;
                }

                foreach ($mi->recipeLines as $rl) {
                    $consumed = (float) $row['qty'] * (float) $rl->qty;
                    $delta[$rl->stock_item_id] = ($delta[$rl->stock_item_id] ?? 0.0) - $consumed;
                }
            }
        }

        // Vanzarile inregistrate (sesiunea aleasa + cele simple bifate), agregate pe produs,
        // consuma si ele prin reteta.
        $registered = collect();
        if ($this->sales_group_id !== '' && ($group = SalesGroup::find((int) $this->sales_group_id))) {
            $registered = $registered->merge($group->aggregatedLines());
        }
        if ($this->include_loose_sales) {
            $registered = $registered->merge(SalesAggregator::lines(StockReport::looseSalesQuery()->pluck('id')->all()));
        }

        foreach ($registered as $agg) {
            foreach ($agg->menuItem->recipeLines as $rl) {
                $delta[$rl->stock_item_id] = ($delta[$rl->stock_item_id] ?? 0.0) - ($agg->qty * (float) $rl->qty);
            }
        }

        if (empty($delta)) {
            return [];
        }

        $items = StockItem::whereIn('id', array_keys($delta))->get()->keyBy('id');
        $rows = [];

        foreach ($delta as $id => $net) {
            $si = $items->get($id);
            if (! $si) {
                continue;
            }

            $current = (float) $si->stock_qty;
            $result = $current + $net;
            $rows[] = [
                'name' => $si->name,
                'unit' => $si->unit,
                'current' => $current,
                'net' => $net,
                'result' => $result,
                'package' => $si->hasPackage() ? $si->packageDisplayFor($result) : null,
            ];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Date pentru panoul de vanzari inregistrate (sesiune / vanzari simple): randuri
     * agregate pe produs, plati, totaluri - doar vanzarile finalizate (anularile nu
     * intra in raportare; se vad in pagina Vanzari).
     */
    private function salesSourceData(array $saleIds): array
    {
        $completed = $saleIds === []
            ? null
            : \App\Models\Sale::query()->whereIn('id', $saleIds)->where('status', 'completed')
                ->selectRaw('COUNT(*) as n, SUM(total) as total')->first();

        return [
            'lines' => SalesAggregator::lines($saleIds),
            'payments' => SalesAggregator::payments($saleIds),
            'completed' => (int) ($completed?->n ?? 0),
            'revenue' => round((float) ($completed?->total ?? 0), 2),
        ];
    }

    // ------------------------------------------------------------------

    public function render()
    {
        // Raport finalizat: continut read-only din miscarile reale (staging e gol).
        if ($this->readOnly && $this->report) {
            return view('livewire.admin.stock-reports.form', [
                'readOnlyView' => true,
                'finalEntries' => $this->report->entries()->with(['stockItem', 'requisitionItem.requisition'])->orderBy('id')->get(),
                'finalSales' => $this->report->sales()->with(['menuItem', 'salesGroup.party'])->orderBy('id')->get(),
                'finalLosses' => $this->report->losses()->with('stockItem')->orderBy('id')->get(),
                'revenue' => $this->report->totalRevenue(),
                'cost' => $this->report->totalCost(),
                'profit' => $this->report->totalProfit(),
            ]);
        }

        // Produse de stoc active + cele deja alese pe randuri (chiar daca intre timp dezactivate).
        $entryLossIds = collect($this->entries)->pluck('stock_item_id')
            ->merge(collect($this->losses)->pluck('stock_item_id'))
            ->filter()->map(fn ($v) => (int) $v)->all();

        $stockItems = StockItem::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $entryLossIds))
            ->ordered()
            ->get();

        // Produse de meniu urmarite (au reteta) — doar acelea consuma stoc; le
        // afisam pe toate cele active, dar semnalam vizual daca nu-s urmarite.
        $chosenMenuIds = collect($this->sales)->pluck('menu_item_id')->filter()->map(fn ($v) => (int) $v)->all();
        $menuItems = MenuItem::query()
            ->with('recipeLines')
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $chosenMenuIds))
            ->orderBy('name')
            ->get();

        // Petreceri viitoare/in desfasurare (aceeasi logica ca la Necesare).
        $upcoming = fn ($q) => $q
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere(fn ($f) => $f->where('kind', 'festival')->where('ends_at', '>', now()));

        $parties = Party::query()->where($upcoming)->orderBy('starts_at')->get();

        if ($this->party_id !== '' && ! $parties->contains('id', (int) $this->party_id)) {
            if ($current = Party::find((int) $this->party_id)) {
                $parties->prepend($current);
            }
        }

        // Necesare deschise (pentru aducere in Intrari) + necesarul selectat in panou.
        $openRequisitions = StockRequisition::open()->orderByDesc('created_at')->orderByDesc('id')->get();

        $importRequisition = null;
        if ($this->importReqId !== '') {
            $importRequisition = StockRequisition::with(['items.stockItem'])->find((int) $this->importReqId);
        }

        // Grupuri de vanzari disponibile (deschise, nefolosite de alt draft) + cel ales acum.
        $salesGroups = SalesGroup::with('party')->open()
            ->whereNotIn('id', $this->claimedGroupIds())
            ->orderByDesc('id')
            ->get();

        $selectedGroup = null;
        if ($this->sales_group_id !== '') {
            $selectedGroup = SalesGroup::with('party')->find((int) $this->sales_group_id);

            if ($selectedGroup && ! $salesGroups->contains('id', $selectedGroup->id)) {
                $salesGroups->prepend($selectedGroup);
            }
        }

        $groupData = $selectedGroup ? $this->salesSourceData($selectedGroup->saleIds()) : null;

        // Vanzari simple (fara sesiune), neraportate: panoul apare doar daca exista
        // vanzari FINALIZATE de adus (cele anulate nu conteaza in raportare).
        $looseData = $this->salesSourceData(StockReport::looseSalesQuery()->pluck('id')->all());
        if ($looseData['completed'] === 0) {
            $looseData = null; // doar anulate (sau nimic): nu e nimic de adus in raportare
        }

        return view('livewire.admin.stock-reports.form', [
            'readOnlyView' => false,
            'salesGroups' => $salesGroups,
            'selectedGroup' => $selectedGroup,
            'groupData' => $groupData,
            'looseData' => $looseData,
            'stockItems' => $stockItems,
            'menuItems' => $menuItems,
            'parties' => $parties,
            'units' => StocksIndex::UNITS,
            'openRequisitions' => $openRequisitions,
            'importRequisition' => $importRequisition,
            'importedItemIds' => $this->importedRequisitionItemIds(),
            'impact' => $this->computeImpact(),
        ]);
    }
}
