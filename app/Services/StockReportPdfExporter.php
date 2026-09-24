<?php

namespace App\Services;

use App\Models\StockReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DXA: adaugat (Bar - Raportari) — genereaza PDF-ul unei raportari FINALIZATE.
 *
 * Refoloseste exact aceleasi relatii/metode ca vizualizarea read-only din
 * Livewire\Admin\StockReports\Form (entries()/sales()/losses() + totalRevenue/
 * totalCost/totalProfit din StockReport), ca PDF-ul sa reflecte mereu ce se
 * vede pe ecran, fara logica duplicata.
 *
 * Foloseste pachetul barryvdh/laravel-dompdf — daca nu e deja instalat:
 *   composer require barryvdh/laravel-dompdf
 *
 * Apelat din Form::exportPdf() (butonul din detalii) si Index::exportPdf()
 * (butonul din listă, per rand).
 */
class StockReportPdfExporter
{
    public static function stream(StockReport $report): StreamedResponse
    {
        abort_unless($report->isFinalized(), 404, 'Doar raportările finalizate pot fi exportate.');

        $report->loadMissing(['party', 'finalizer']);

        $pdf = Pdf::loadView('pdf.stock-report', [
            'report' => $report,
            'entries' => $report->entries()->with(['stockItem', 'requisitionItem.requisition'])->orderBy('id')->get(),
            'sales' => $report->sales()->with('menuItem')->orderBy('id')->get(),
            'losses' => $report->losses()->with('stockItem')->orderBy('id')->get(),
            'revenue' => $report->totalRevenue(),
            'cost' => $report->totalCost(),
            'profit' => $report->totalProfit(),
            // DXA: adaugat (Bar - numaratoare de final de seara)
            'closing' => $report->closingSummary(),
            'counts' => $report->counts()->with('stockItem')->get()
                ->sortBy(fn ($c) => mb_strtolower($c->stockItem?->name ?? ''))->values(),
        ])->setPaper('a4');

        $filename = 'raportare-'.$report->id.'-'.$report->date->format('Y-m-d').($report->party ? '-'.\Illuminate\Support\Str::slug($report->party->name) : '').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }
}
