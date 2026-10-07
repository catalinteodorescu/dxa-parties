<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Coduri de reducere). Un cod al unei petreceri (de obicei unul pe promotor). Logica de aplicare
 * e în App\Services\DiscountCodes; utilizările se numără din intrările valabile (o utilizare = un bilet).
 */
class PartyDiscountCode extends Model
{
    public const TYPES = [
        'percent' => 'Procent din prețul curent',
        'amount' => 'Sumă în lei din prețul curent',
        'tier' => 'Deblochează o treaptă (ex. Early bird)',
    ];

    protected $fillable = [
        'party_id',
        'code',
        'promoter_id',
        'type',
        'value',
        'tier_label',
        'ticket_types',
        'valid_from',
        'valid_until',
        'max_uses',
        'max_uses_per_participant',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'ticket_types' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_participant' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** Promotorul căruia îi aparține codul (null = fără promotor). */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartyEntry::class, 'discount_code_id');
    }

    /** Biletele cumpărate din aplicație cu acest cod (DXA: runda 14); fiecare bilet cu reducere e o utilizare. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'discount_code_id');
    }

    /**
     * Câte bilete au folosit codul = câte reduceri s-au dat: intrările valabile de la Recepție + biletele online neanulate.
     * (Un bilet online devenit intrare NU e numărat de două ori: intrarea creată din bilet nu poartă codul, reducerea e pe bilet.)
     */
    public function usesCount(): int
    {
        return (int) $this->entries()->active()->count() + (int) $this->tickets()->counted()->count();
    }

    /**
     * Câte FOLOSIRI ale codului are participantul (runda 53): o comandă online cu codul = o folosire, oricâte bilete cu reducere ar avea;
     * la Recepție, fiecare intrare valabilă cu codul pe numele lui = o folosire. Limita per participant se numără pe folosiri, nu pe bilete.
     * Comenzile cu toate biletele anulate nu se numără.
     */
    public function usesBy(int $participantId): int
    {
        return (int) $this->entries()->active()->where('participant_id', $participantId)->count()
            + (int) $this->tickets()->counted()
                ->whereHas('order', fn ($q) => $q->where('participant_id', $participantId))
                ->distinct()->count('order_id');
    }
}
