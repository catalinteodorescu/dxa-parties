<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReportSale extends Model
{
    protected $fillable = [
        'report_id',
        'menu_item_id',
        'sales_group_id',
        'is_loose',
        'qty',
        'unit_price',
        'total_price',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'is_loose' => 'boolean',
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'report_id');
    }

    /** Sesiunea de vanzari din care provine randul (null = vanzare manuala sau simpla). */
    public function salesGroup(): BelongsTo
    {
        return $this->belongsTo(SalesGroup::class, 'sales_group_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /** Miscarile de stoc (consum ingrediente) generate de aceasta vanzare. */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'sale_id');
    }

    public function profit(): ?float
    {
        if ($this->total_cost === null) {
            return null;
        }

        return round((float) $this->total_price - (float) $this->total_cost, 2);
    }
}
