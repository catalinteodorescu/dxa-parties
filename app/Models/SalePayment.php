<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'tokens',
        'token_rate',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tokens' => 'integer',
            'token_rate' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
