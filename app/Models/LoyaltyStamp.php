<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DXA: adaugat (Card de fidelitate). Un rând din ledgerul imuabil al unui card: o ștampilă (un cerc completat).
 * Se creează DOAR prin App\Services\LoyaltyLedger. Nu se șterge — o corecție o anulează (voided_at/void_reason).
 */
class LoyaltyStamp extends Model
{
    public const SOURCE_ENTRY = 'entry';

    public const SOURCE_MANUAL_ADJUST = 'manual_adjust';

    public const SOURCE_LABELS = [
        self::SOURCE_ENTRY => 'Intrare',
        self::SOURCE_MANUAL_ADJUST => 'Ajustare manuală',
    ];

    protected $fillable = [
        'loyalty_card_id', 'source', 'batch', 'stamped_at', 'party_entry_id', 'reason', 'created_by',
        'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'stamped_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class, 'loyalty_card_id');
    }

    public function partyEntry(): BelongsTo
    {
        return $this->belongsTo(PartyEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
