<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemRecipe extends Model
{
    protected $fillable = [
        'menu_item_id',
        'stock_item_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
