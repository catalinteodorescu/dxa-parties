<?php

namespace App\Services;

use App\Models\StockRequisition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DXA: adaugat (Bar - necesare) — genereaza PDF-ul unui necesar, ca lista
 * de tiparit/trimis furnizorului. Analog StockReportPdfExporter, dar FARA
 * restrictia de "finalizat": un necesar e util de exportat oricand, chiar
 * mai ales inainte de a fi rezolvat (asta il face o lista de cumparaturi).
 *
 * Apelat din StockRequisitions\Index::exportPdf() (buton din lista, per
 * rand) si StockRequisitions\Form::exportPdf() (buton din formular, doar
 * la editare — un necesar nesalvat inca nu are ce exporta).
 */
class StockRequisitionPdfExporter
{
    public static function stream(StockRequisition $requisition): StreamedResponse
    {
        $requisition->loadMissing(['party', 'creator', 'items.stockItem']);

        $items = $requisition->items
            ->sortBy(fn ($item) => mb_strtolower($item->stockItem->name))
            ->values();

        $pdf = Pdf::loadView('pdf.stock-requisition', [
            'requisition' => $requisition,
            'items' => $items,
        ])->setPaper('a4');

        $filename = 'necesar-'.$requisition->id.'-'.$requisition->created_at->format('Y-m-d').'-'.Str::slug($requisition->label).'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }
}
