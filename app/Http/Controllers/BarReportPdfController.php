<?php

namespace App\Http\Controllers;

use App\Models\BarReport;
use App\Services\BarReportPdfExporter;

/** DXA: adaugat (Bar - raportări casă). Descărcarea PDF-ului unei raportări de bar (în admin). */
class BarReportPdfController extends Controller
{
    public function __invoke(BarReport $report)
    {
        return BarReportPdfExporter::stream($report);
    }
}
