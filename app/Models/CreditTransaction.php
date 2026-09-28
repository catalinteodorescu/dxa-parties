<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DXA: adaugat (Portofelul de credite). Un rând din ledgerul imuabil al soldului de credite.
 * Se creează DOAR prin App\Services\CreditLedger. Vezi migrarea pentru semnificația tipurilor/surselor.
 *
 * DXA: adaugat (Etapa 2 - vânzare la recepție). Un rând `load`/`reception` poartă party_id + reception_session_id
 * (ca la tokeni) și se poate ANULA (cancelled_at/cancelled_by/cancel_reason), fără să se șteargă; soldul
 * participantului exclude rândurile anulate la recalculare (vezi App\Services\CreditLedger::recalc()).
 */
class CreditTransaction extends Model
{
    public const LOAD = 'load';

    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    public const ADJUSTMENT = 'adjustment';

    public const SOURCE_APP = 'participant_app';

    public const SOURCE_RECEPTION = 'reception';

    public const SOURCE_BAR = 'bar';

    public const SOURCE_MANUAL = 'manual';

    public const TYPE_LABELS = [
        self::LOAD => 'Încărcare',
        self::PAYMENT => 'Plată',
        self::REFUND => 'Refund',
        self::ADJUSTMENT => 'Ajustare',
    ];

    public const SOURCE_LABELS = [
        self::SOURCE_APP => 'Aplicație participant',
        self::SOURCE_RECEPTION => 'Recepție',
        self::SOURCE_BAR => 'Bar',
        self::SOURCE_MANUAL => 'Manual (admin)',
    ];

    protected $fillable = [
        'participant_id', 'type', 'amount', 'source', 'party_id', 'reception_session_id',
        'reference_type', 'reference_id', 'note', 'occurred_at', 'created_by',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** Sesiunea de recepție în care s-a făcut vânzarea (doar pentru `load`/`reception`; DXA: Recepție - sesiuni). */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReceptionSession::class, 'reception_session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditTransactionPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cancelled_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
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

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
