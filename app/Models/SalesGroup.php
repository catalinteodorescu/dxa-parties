<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\SalesAggregator;
use Illuminate\Support\Collection;

/**
 * Sesiune de vanzari (tabelul se numeste sales_groups din motive istorice): sesiunea comuna de vanzare a barului. Un singur grup DESCHIS
 * per petrecere (partajat intre barmani) + eventual unul "fara petrecere" pentru
 * vanzarile din afara petrecerilor. Se inchide la finalizarea Raportarii care il
 * consuma (StockReport::finalize()).
 */
class SalesGroup extends Model
{
    public const STATUSES = [
        'open' => 'Deschis',
        'closed' => 'Închis',
    ];

    protected $fillable = [
        'party_id',
        'status',
        'closed_at',
        'created_by',
        'bartender_id',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Raportarea care consuma (sau a consumat) acest grup. */
    public function report(): HasOne
    {
        return $this->hasOne(StockReport::class, 'sales_group_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Numele sesiunii: petrecerea + data (sau „Fără petrecere"). */
    public function title(): string
    {
        $name = $this->party?->name ?? 'Fără petrecere';
        $date = ($this->party?->starts_at ?? $this->created_at)?->format('d.m.Y');

        return $name.($date ? ' · '.$date : '');
    }

    /** Ca title(), plus „· închis" cand sesiunea nu mai primeste vanzari. */
    public function label(): string
    {
        return $this->title().($this->isOpen() ? '' : ' · închis');
    }

    /**
     * Grupul deschis al unei petreceri (sau cel "fara petrecere" cand $partyId e null);
     * daca nu exista, il creeaza. Un singur grup deschis per petrecere.
     */
    public static function openFor(?int $partyId, ?int $adminId = null): self
    {
        $existing = static::open()
            ->when($partyId === null, fn ($q) => $q->whereNull('party_id'), fn ($q) => $q->where('party_id', $partyId))
            ->orderBy('id')
            ->first();

        return $existing ?? static::create([
            'party_id' => $partyId,
            'status' => 'open',
            'created_by' => $adminId,
        ]);
    }

    /** Id-urile tuturor vanzarilor sesiunii (finalizate si anulate). */
    public function saleIds(): array
    {
        return $this->sales()->pluck('id')->all();
    }

    /** Vanzarile finalizate ale sesiunii, agregate pe produs (vezi SalesAggregator). */
    public function aggregatedLines(): Collection
    {
        return SalesAggregator::lines($this->saleIds());
    }

    /** Totaluri pe metoda de plata (doar vanzari finalizate): method => [amount, tokens]. */
    public function paymentTotals(): array
    {
        return SalesAggregator::payments($this->saleIds());
    }

    public function completedCount(): int
    {
        return $this->sales()->where('status', 'completed')->count();
    }

    public function cancelledCount(): int
    {
        return $this->sales()->where('status', 'cancelled')->count();
    }

    public function revenue(): float
    {
        return round((float) $this->sales()->where('status', 'completed')->sum('total'), 2);
    }

    /** Are cel putin o vanzare (chiar si anulata) - ca sa poata fi inchisa printr-o raportare. */
    public function hasAnySales(): bool
    {
        return $this->sales()->exists();
    }

    public function close(): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);
    }
}
