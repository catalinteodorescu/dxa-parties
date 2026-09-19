<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequisitionItem extends Model
{
    protected $fillable = [
        'stock_requisition_id',
        'stock_item_id',
        'qty_requested',
        'qty_received',
    ];

    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:3',
            'qty_received' => 'decimal:3',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->qty_received >= (float) $this->qty_requested;
    }

    public function remainingQty(): float
    {
        return max(0.0, (float) $this->qty_requested - (float) $this->qty_received);
    }
}
