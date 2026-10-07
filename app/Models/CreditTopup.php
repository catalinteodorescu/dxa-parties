<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DXA: adaugat (runda 51). O încărcare de credite începută din aplicație, în așteptarea plății cu cardul.
 * Se creează și se finalizează DOAR prin App\Services\CreditTopups. Creditele ajung în ledger numai la `paid`.
 */
class CreditTopup extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'uuid', 'participant_id', 'amount', 'bonus', 'fee', 'status', 'provider', 'provider_ref',
        'credit_transaction_id', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'bonus' => 'decimal:2', 'fee' => 'decimal:2',
            'expires_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    /** Creditele primite: suma plătită + bonusul. */
    public function credited(): float
    {
        return round((float) $this->amount + (float) $this->bonus, 2);
    }

    /** Cât se plătește efectiv cu cardul: suma + taxa. */
    public function toPay(): float
    {
        return round((float) $this->amount + (float) $this->fee, 2);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
