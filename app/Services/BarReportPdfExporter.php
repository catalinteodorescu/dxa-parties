<?php

namespace App\Services;

use App\Models\BarReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DXA: adaugat (Bar - raportări casă). PDF-ul unei raportări de bar TRIMISE (sau a unei sesiuni închise): aceleași cifre ca pe ecran
 * (BarReport::figures()), același antet ca la recepție (pdf._brand).
 */
class BarReportPdfExporter
{
    public static function stream(BarReport $report): StreamedResponse
    {
        $report->loadMissing(['party', 'submitter', 'group']);

        abort_unless($report->isSubmitted() || ! ($report->group?->isOpen() ?? false), 404, 'Doar raportările trimise pot fi exportate.');

        $pdf = Pdf::loadView('pdf.bar-report', [
            'report' => $report,
            'fig' => $report->figures(),
        ])->setPaper('a4');

        $filename = 'raportare-bar-'.$report->id.'-'.$report->date->format('Y-m-d').($report->party ? '-'.Str::slug($report->party->name) : '').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename);
    }
}
