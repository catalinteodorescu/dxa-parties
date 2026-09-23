<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O vanzare la bar (un "bon"): linii de produse + una sau mai multe plati.
 * Se creeaza prin App\Services\SaleRecorder. Nu se sterge niciodata - doar se anuleaza,
 * si doar cat timp nu a fost raportata (dupa finalizarea Raportarii stocul e deja postat).
 * Poate apartine unei sesiuni de vanzari (ex. o petrecere) sau nu (vanzare simpla, ex. la un curs).
 */
class Sale extends Model
{
    public const STATUSES = [
        'completed' => 'Finalizată',
        'cancelled' => 'Anulată',
    ];

    public const SOURCES = [
        'app' => 'Aplicație',
        'manual' => 'Admin',
    ];

    protected $fillable = [
        'sales_group_id',
        'report_id',
        'source',
        'client_uuid',
        'bartender_id',
        'customer_id',
        'identified_by',
        'status',
        'total',
        'sold_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SalesGroup::class, 'sales_group_id');
    }

    /** Raportarea care a postat vanzarea (null = neraportata inca). */
    public function report(): BelongsTo
    {
        return $this->belongsTo(StockReport::class, 'report_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Se poate anula doar o vanzare finalizata si NEraportata: dupa finalizarea
     * Raportarii stocul e deja postat, iar o corectie ar cere inversarea lui.
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isCancelled() && $this->report_id === null;
    }

    public function isReported(): bool
    {
        return $this->report_id !== null;
    }

    public function cancel(?int $adminId, string $reason): void
    {
        if (! $this->canBeCancelled()) {
            throw new \DomainException('Vânzarea nu mai poate fi anulată (e deja anulată sau raportată).');
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $adminId,
            'cancel_reason' => $reason,
        ]);
    }
}
