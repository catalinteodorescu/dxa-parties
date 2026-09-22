<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRequisition extends Model
{
    public const STATUSES = [
        'open' => 'Deschis',
        'fulfilled' => 'Rezolvat',
        'closed' => 'Închis',
    ];

    protected $fillable = [
        'label',
        'status',
        'party_id',
        'created_by',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequisitionItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Rezolvat complet = toate liniile au primit cel putin cat s-a cerut. */
    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (StockRequisitionItem $item) => (float) $item->qty_received >= (float) $item->qty_requested
        );
    }

    /**
     * Actualizeaza statusul dupa o raportare: fulfilled daca toate liniile
     * sunt acoperite, altfel ramane open (acoperire partiala).
     */
    public function refreshStatus(): void
    {
        $this->load('items');

        if ($this->status !== 'closed') {
            $this->status = $this->isFullyReceived() ? 'fulfilled' : 'open';
            $this->save();
        }
    }
}
