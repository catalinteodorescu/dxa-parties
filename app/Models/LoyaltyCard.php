<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Card de fidelitate). Un card (activ sau completat/arhivat) al unui participant. Se creează/
 * modifică DOAR prin App\Services\LoyaltyLedger. `stamps_required` e fixat la crearea cardului (nu se schimbă
 * dacă setarea globală se modifică ulterior).
 */
class LoyaltyCard extends Model
{
    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    protected $fillable = ['participant_id', 'stamps_required', 'status', 'completed_at'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /** DXA: adaugat (runda 20). Numărul afișat al cardului = id-ul lui, minim 4 cifre (0001, 0023, 1023); la fel în aplicație și în admin. */
    public function number(): string
    {
        return str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function stamps(): HasMany
    {
        return $this->hasMany(LoyaltyStamp::class);
    }

    /** Ștampilele valide (nevanulate), în ordinea în care au fost adunate — un cerc per rând. */
    public function scopeWithActiveStamps(Builder $query): Builder
    {
        return $query->with(['stamps' => fn ($q) => $q->whereNull('voided_at')->orderBy('stamped_at')->orderBy('id')]);
    }

    public function activeStampsCount(): int
    {
        return $this->stamps()->whereNull('voided_at')->count();
    }

    /** Numărul total de cercuri ale cardului: X ștampile + 1 „intrare gratis". */
    public function totalCircles(): int
    {
        return $this->stamps_required + 1;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }
}
