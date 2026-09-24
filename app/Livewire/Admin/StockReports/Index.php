<?php

namespace App\Livewire\Admin\StockReports;

use App\Models\Party;
use App\Models\StockReport;
use App\Services\ActivityLogger;
use App\Services\StockReportPdfExporter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Bar - raportari)
 *
 * Lista raportarilor (draft + finalizate). Creare/editare in pagina separata
 * Form. Un draft se poate continua sau sterge (nu a atins stocul real); un
 * raport finalizat se poate doar vizualiza (read-only) — mișcările lui de stoc
 * sunt imuabile, ca tot restul jurnalului.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all'; // all | draft | finalized

    #[Url]
    public string $party = ''; // '' | id

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    public function updated($name): void
    {
        if (in_array($name, ['status', 'party', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'party', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    /** Se sterg doar drafturile — nu au atins stocul real, deci nu e nimic de reversat. */
    public function delete(int $id): void
    {
        $report = StockReport::findOrFail($id);

        if ($report->isFinalized()) {
            session()->flash('error', 'Raportarea nr. '.$report->number().' e finalizată — nu poate fi ștearsă.');

            return;
        }

        $number = $report->number();

        DB::transaction(function () use ($report) {
            $report->lines()->delete();
            $report->counts()->delete(); // DXA: numaratoarea (draft) se sterge odata cu raportarea
            $report->delete();
        });

        ActivityLogger::log('stock.report_deleted', 'A șters raportarea (draft) nr. '.$number.'.');
        session()->flash('status', 'Draftul de raportare a fost șters.');
    }

    /** Export PDF — disponibil doar pentru raportări finalizate (vezi StockReportPdfExporter). */
    public function exportPdf(int $id)
    {
        return StockReportPdfExporter::stream(StockReport::findOrFail($id));
    }

    public function render()
    {
        $reports = StockReport::query()
            ->with(['party', 'creator', 'counts'])
            ->withCount([
                'lines as entry_count' => fn ($q) => $q->where('kind', 'entry'),
                'lines as sale_count' => fn ($q) => $q->where('kind', 'sale'),
                'lines as loss_count' => fn ($q) => $q->where('kind', 'loss'),
            ])
            ->when(
                in_array($this->status, ['draft', 'finalized'], true),
                fn ($q) => $q->where('status', $this->status)
            )
            ->when($this->party !== '', fn ($q) => $q->where('party_id', (int) $this->party))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            // Drafturile primele (au de lucru pe ele), apoi cele mai recente.
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);

        // Petrecerile care apar pe raportari (pt. filtrul de party).
        $partyIds = StockReport::whereNotNull('party_id')->distinct()->pluck('party_id');
        $parties = Party::whereIn('id', $partyIds)->orderByDesc('starts_at')->get();

        return view('livewire.admin.stock-reports.index', [
            'reports' => $reports,
            'parties' => $parties,
            'statuses' => StockReport::STATUSES,
        ]);
    }
}
