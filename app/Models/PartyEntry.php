<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'discount_code_id',
        'discount_amount',
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
            'discount_amount' => 'decimal:2',
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

    /** Codul de reducere folosit (null = fără cod). FK doar în cod, nu în DB. */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(PartyDiscountCode::class, 'discount_code_id');
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

    /** Biletul online folosit la această intrare (null dacă a intrat fără bilet sau intrarea a fost anulată: biletul se eliberează). */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class, 'party_entry_id');
    }

    /** Runda 58: intrarea s-a făcut cu un bilet cu preț, achitat online (card / credite) — deci e plătită, chiar dacă la intrare nu s-a încasat nimic. */
    public function isPaidOnline(): bool
    {
        $t = $this->ticket;

        return $t && (float) $t->price > 0 && $t->loadMissing('order')->order?->payment_status !== Order::PAY_AT_ENTRY;
    }

    public function isFree(): bool
    {
        return (float) $this->price_paid <= 0 && ! $this->isPaidOnline();
    }

    /** Intrări cu bilet cu preț achitat online. */
    public function scopePaidOnline(Builder $query): Builder
    {
        return $query->whereHas('ticket', fn ($t) => $t->where('price', '>', 0)
            ->whereHas('order', fn ($o) => $o->where('payment_status', '!=', Order::PAY_AT_ENTRY)));
    }

    /** Prețul a fost schimbat manual de recepționer (față de ce calcula sistemul, cu toleranță inclusă). */
    public function isOverridden(): bool
    {
        return $this->override_reason !== null;
    }
}
