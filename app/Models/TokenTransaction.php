<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Recepție - tokeni). O mișcare din ledgerul tokenilor: vânzare, ajustare sau casare.
 * Imuabilă: se anulează (doar vânzările, cu motiv), nu se șterge. Se creează DOAR prin App\Services\TokenLedger.
 */
class TokenTransaction extends Model
{
    public const SOLD = 'sold';

    public const ADJUSTMENT = 'adjustment';

    public const WRITE_OFF = 'write_off';

    public const TYPES = [
        self::SOLD => 'Vânzare',
        self::ADJUSTMENT => 'Ajustare',
        self::WRITE_OFF => 'Casare',
    ];

    protected $fillable = [
        'type',
        'party_id',
        'reception_session_id',
        'tokens',
        'token_rate',
        'amount',
        'note',
        'occurred_at',
        'participant_id',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'token_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** Sesiunea de recepție în care s-a făcut vânzarea (doar pentru „sold”; DXA: Recepție - sesiuni). */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReceptionSession::class, 'reception_session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TokenTransactionPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** Participantul care a cumpărat tokenii (null = anonim). FK doar în cod, nu în DB. */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** Mișcările valabile (neanulate). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
