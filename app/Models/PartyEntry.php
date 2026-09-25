<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Recepție - intrări). O persoană intrată la o petrecere, cu prețul înghețat.
 * Imuabil: se anulează (cu motiv), nu se șterge. Se creează DOAR prin App\Services\EntryRecorder.
 */
class PartyEntry extends Model
{
    protected $fillable = [
        'party_id',
        'reception_session_id',
        'batch',
        'ticket_type',
        'list_price',
        'price_paid',
        'grace_applied',
        'override_reason',
        'entered_at',
        'participant_id',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'price_paid' => 'decimal:2',
            'grace_applied' => 'boolean',
            'entered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** Sesiunea de recepție în care a fost înregistrată (DXA: Recepție - sesiuni). */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ReceptionSession::class, 'reception_session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PartyEntryPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** Participantul identificat (null = intrare anonimă). FK doar în cod, nu în DB. */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** Intrările valabile (neanulate). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isFree(): bool
    {
        return (float) $this->price_paid <= 0;
    }

    /** Prețul a fost schimbat manual de recepționer (față de ce calcula sistemul, cu toleranță inclusă). */
    public function isOverridden(): bool
    {
        return $this->override_reason !== null;
    }
}
