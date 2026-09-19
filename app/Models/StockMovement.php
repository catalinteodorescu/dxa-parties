<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jurnal unic de miscari de stoc, per stock_item. Nu se creeaza direct - se
 * genereaza exclusiv prin metodele StockItem::recordInitialStock/recordEntry/
 * recordExit/recordCostAdjustment, care tin si cache-ul stock_qty/avg_cost
 * sincronizat pe StockItem.
 */
class StockMovement extends Model
{
    public const TYPES = [
        'initial' => 'Stoc inițial',
        'in' => 'Intrare',
        'out' => 'Ieșire',
        'adjustment' => 'Corecție cost',
    ];

    protected $fillable = [
        'stock_item_id',
        'report_id',
        'sale_id',
        'type',
        'qty',
        'unit_cost',
        'total_cost',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'report_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(StockReportSale::class, 'sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeForStockItem(Builder $query, int $stockItemId): Builder
    {
        return $query->where('stock_item_id', $stockItemId);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function typeLabel(): string
    {
        // "adjustment" acopera atat corectii de cost (qty=0, doar unit_cost),
        // cat si corectii de cantitate (qty=delta, unit_cost null) - le
        // diferentiem la afisare dupa care camp e completat, fara o coloana
        // noua in schema.
        if ($this->type === 'adjustment' && (float) $this->qty !== 0.0) {
            return 'Corecție stoc';
        }

        return self::TYPES[$this->type] ?? $this->type;
    }
}
