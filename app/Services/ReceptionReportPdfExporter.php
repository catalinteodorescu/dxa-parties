<?php

namespace App\Services;

use App\Models\ReceptionReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DXA: adaugat (Recepție - raportări) — PDF-ul unei raportări de recepție FINALIZATE (închiderea casei).
 * Aceleași cifre ca pe ecran (ReceptionReport::figures(), înghețate la finalizare); același antet ca la bar (pdf._brand).
 */
class ReceptionReportPdfExporter
{
    public static function stream(ReceptionReport $report): StreamedResponse
    {
        abort_unless($report->isFinalized(), 404, 'Doar raportările finalizate pot fi exportate.');

        $report->loadMissing(['party', 'finalizer', 'session']);

        $pdf = Pdf::loadView('pdf.reception-report', [
            'report' => $report,
            'fig' => $report->figures(),
        ])->setPaper('a4');

        $filename = 'raportare-receptie-'.$report->id.'-'.$report->date->format('Y-m-d').($report->party ? '-'.Str::slug($report->party->name) : '').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename);
    }
}
