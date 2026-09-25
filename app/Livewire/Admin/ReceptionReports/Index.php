<?php

namespace App\Livewire\Admin\ReceptionReports;

use App\Models\Party;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Services\ActivityLogger;
use App\Services\ReceptionReportPdfExporter;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * DXA: adaugat (Recepție - raportări). Lista raportărilor de recepție (închiderea casei) + sesiunile deschise, de unde
 * se începe „Închide casa” (creează draftul sesiunii). Un draft se poate continua sau șterge (sesiunea rămâne deschisă);
 * o raportare finalizată se poate doar vizualiza / exporta.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all'; // all | draft | finalized

    #[Url]
    public string $party = ''; // '' | id

    /** Mesaje ale cardului „Sesiuni deschise” și ale listei (fiecare în secțiunea ei). */
    public ?string $sessionError = null;

    public ?string $listMessage = null;

    public ?string $listError = null;

    public function updated($name): void
    {
        if (in_array($name, ['status', 'party'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'party');
        $this->resetPage();
    }

    /** „Închide casa”: draftul sesiunii (îl creează dacă nu există) și trece la pagina lui. */
    public function startReport(int $sessionId)
    {
        $this->sessionError = null;

        try {
            $session = ReceptionSession::query()->open()->with('party')->findOrFail($sessionId);
            $report = ReceptionReport::startFor($session, Auth::guard('admin')->id());
        } catch (DomainException $e) {
            $this->sessionError = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('admin.reception.reports.show', $report, navigate: true);
    }

    /** Se șterg doar drafturile; sesiunea rămâne deschisă și nu se pierde nicio înregistrare. */
    public function delete(int $id): void
    {
        $this->listMessage = $this->listError = null;
        $report = ReceptionReport::findOrFail($id);

        if ($report->isFinalized()) {
            $this->listError = 'Raportarea nr. '.$report->number().' e finalizată — nu poate fi ștearsă.';

            return;
        }

        $number = $report->number();
        $report->delete();

        ActivityLogger::log('reception.report_deleted', 'A șters raportarea de recepție (draft) nr. '.$number.'.');
        $this->listMessage = 'Draftul de raportare a fost șters. Sesiunea rămâne deschisă.';
    }

    public function exportPdf(int $id)
    {
        return ReceptionReportPdfExporter::stream(ReceptionReport::findOrFail($id));
    }

    public function render()
    {
        $openSessions = ReceptionSession::query()->open()
            ->with(['party', 'report'])
            ->withCount([
                'entries as entries_count' => fn ($q) => $q->whereNull('cancelled_at'),
                'tokenSales as token_sales_count' => fn ($q) => $q->whereNull('cancelled_at'),
            ])
            ->orderBy('created_at')
            ->get();

        $reports = ReceptionReport::query()
            ->with(['party', 'creator', 'session'])
            ->when(in_array($this->status, ['draft', 'finalized'], true), fn ($q) => $q->where('status', $this->status))
            ->when($this->party !== '', fn ($q) => $q->where('party_id', (int) $this->party))
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);

        $partyIds = ReceptionReport::query()->distinct()->pluck('party_id');
        $parties = Party::whereIn('id', $partyIds)->orderByDesc('starts_at')->get();

        return view('livewire.admin.reception-reports.index', [
            'openSessions' => $openSessions,
            'reports' => $reports,
            'parties' => $parties,
            'statuses' => ReceptionReport::STATUSES,
        ]);
    }
}
