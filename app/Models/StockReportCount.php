<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DXA: adaugat (Bar - raportari) — o linie din foaia de inventar a numărătorii de final de seară.
 *
 * Draft: doar `counted_qty` (autosave). La finalizare se completează snapshot-ul:
 *  - expected_qty = stocul teoretic după postarea raportării;
 *  - diff_qty     = counted_qty − expected_qty (negativ = lipsă, pozitiv = surplus);
 *  - unit_cost    = CMP-ul de la acel moment (null = cost necunoscut).
 */
class StockReportCount extends Model
{
    /** Sub acest prag (în unitatea produsului) o diferență se consideră zero. */
    public const EPSILON = 0.0005;

    protected $fillable = [
        'report_id',
        'stock_item_id',
        'counted_qty',
        'expected_qty',
        'diff_qty',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'counted_qty' => 'decimal:3',
            'expected_qty' => 'decimal:3',
            'diff_qty' => 'decimal:3',
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

    public function hasDifference(): bool
    {
        return $this->diff_qty !== null && abs((float) $this->diff_qty) >= self::EPSILON;
    }

    /** Valoarea diferenței în lei (negativ = lipsă); null dacă nu există diferență calculată sau costul e necunoscut. */
    public function diffValue(): ?float
    {
        if ($this->diff_qty === null || $this->unit_cost === null) {
            return null;
        }

        return round((float) $this->diff_qty * (float) $this->unit_cost, 2);
    }
}
