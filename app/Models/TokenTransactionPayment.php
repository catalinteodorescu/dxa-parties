<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (Recepție - tokeni). O plată (metodă + sumă) a unei vânzări de tokeni. */
class TokenTransactionPayment extends Model
{
    protected $fillable = ['token_transaction_id', 'method', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(TokenTransaction::class, 'token_transaction_id');
    }
}
