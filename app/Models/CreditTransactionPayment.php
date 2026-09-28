<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (Portofelul de credite - Etapa 2). O plată (metodă + sumă) a unei vânzări de credite la recepție. */
class CreditTransactionPayment extends Model
{
    protected $fillable = ['credit_transaction_id', 'method', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'credit_transaction_id');
    }
}
