<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O linie din formularul de Raportare, cat timp raportul e 'draft' (staging).
 * Nu atinge stock_qty/avg_cost si nu are echivalent in stock_movements pana
 * la StockReport::finalize() - vezi comentariul din migratia tabelului.
 */
class StockReportLine extends Model
{
    public const KINDS = [
        'entry' => 'Intrare',
        'sale' => 'Vânzare',
        'loss' => 'Pierdere',
    ];

    protected $fillable = [
        'report_id',
        'kind',
        'stock_item_id',
        'menu_item_id',
        'requisition_item_id',
        'qty',
        'unit_cost',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'report_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(StockRequisitionItem::class, 'requisition_item_id');
    }

    public function isEntry(): bool
    {
        return $this->kind === 'entry';
    }

    public function isSale(): bool
    {
        return $this->kind === 'sale';
    }

    public function isLoss(): bool
    {
        return $this->kind === 'loss';
    }
}
