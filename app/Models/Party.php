<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Party extends Model
{
    /** Stiluri pentru invitati (la 'other' se completeaza `style_other`). */
    public const GUEST_STYLES = [
        'bachata' => 'Bachata',
        'salsa' => 'Salsa',
        'kizomba' => 'Kizomba',
        'dj' => 'DJ',
        'guest_dancer' => 'Guest dancer',
        'mc' => 'MC',
        'other' => 'Altul',
    ];

    /** Tipuri de item din programul unei zile. */
    public const PROGRAM_TYPES = [
        'workshop' => 'Workshop',
        'social' => 'Social',
        'show' => 'Show',
        'other' => 'Altul',
    ];

    /** Modalitati de plata predefinite (se pot adauga si custom). */
    public const PAYMENT_METHODS = [
        'cash' => 'Cash la intrare',
        'card' => 'Card',
        'transfer' => 'Transfer bancar',
        'credite' => 'Credite DXA',
        'revolut' => 'Revolut',
    ];

    protected $fillable = [
        'name',
        'image_path',
        'kind',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location_name',
        'location_address',
        'location_url',
        'dresscode',
        'is_free',
        'ticket_types',
        'contacts',
        'payment_methods',
        'audience',
        'in_carousel',
        'is_active',
        'status',
        'days',
        'guests',
        'music_styles',
        'links',
        'custom_fields',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_free' => 'boolean',
            'in_carousel' => 'boolean',
            'is_active' => 'boolean',
            'ticket_types' => 'array',
            'contacts' => 'array',
            'days' => 'array',
            'guests' => 'array',
            'music_styles' => 'array',
            'payment_methods' => 'array',
            'links' => 'array',
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Party $party) {
            [$party->starts_at, $party->ends_at] = $party->resolveInterval();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** DXA: adaugat — inversul lui StockReport::party() (belongsTo). Nu era folosita pana acum, dar ne trebuie explicit acum. */
    public function stockReports(): HasMany
    {
        return $this->hasMany(StockReport::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFestival(): bool
    {
        return $this->kind === 'festival';
    }

    public function state(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if (! $this->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return 'upcoming';
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return 'past';
        }

        return 'live';
    }

    /**
     * Pretul curent al unui singur tip de bilet: minimul dintre pretul de baza si
     * treptele early-bird inca valabile la now(). null daca tipul n-are pret.
     */
    public function currentPriceForType(array $type): ?float
    {
        $now = now();
        $candidates = [];

        if (isset($type['price']) && is_numeric($type['price'])) {
            $candidates[] = (float) $type['price'];
        }

        foreach ($type['tiers'] ?? [] as $t) {
            if (! isset($t['price']) || ! is_numeric($t['price'])) {
                continue;
            }
            if (! empty($t['until']) && Carbon::parse($t['until'])->endOfDay()->lessThan($now)) {
                continue;
            }
            $candidates[] = (float) $t['price'];
        }

        return $candidates ? min($candidates) : null;
    }

    /**
     * Cel mai mic pret curent dintre toate tipurile de bilet (pentru „de la … lei”).
     * 0 daca e gratuit, null daca nu s-a setat niciun pret.
     */
    public function currentPrice(): ?float
    {
        if ($this->is_free) {
            return 0.0;
        }

        $prices = [];
        foreach ($this->ticket_types ?? [] as $type) {
            $p = $this->currentPriceForType($type);
            if ($p !== null) {
                $prices[] = $p;
            }
        }

        return $prices ? min($prices) : null;
    }

    /** Are mai mult de un tip de bilet cu pret? (pentru afisarea „de la”). */
    public function hasMultipleTicketTypes(): bool
    {
        return collect($this->ticket_types ?? [])
            ->filter(fn ($t) => $this->currentPriceForType($t) !== null)
            ->count() > 1;
    }

    /**
     * Statistici DEMO (stabile per petrecere) — până la tracking-ul real din PWA.
     */
    public function demoViews(): int
    {
        return ($this->id * 137 + 89) % 900 + 100;
    }

    public function demoClicks(): int
    {
        return intdiv($this->demoViews(), ($this->id % 5) + 4);
    }

    public static function dayInterval(string $date, ?string $startTime, ?string $endTime): array
    {
        $start = Carbon::parse($date.' '.($startTime ?: '00:00'));

        $end = null;
        if ($endTime) {
            $end = Carbon::parse($date.' '.$endTime);
            if ($startTime && $end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
        }

        return [$start, $end];
    }

    public function resolveInterval(): array
    {
        if ($this->isFestival() && ! empty($this->days)) {
            $start = null;
            $end = null;

            foreach ($this->days as $day) {
                if (empty($day['date'])) {
                    continue;
                }

                [$s, $e] = self::dayInterval($day['date'], $day['start_time'] ?? null, $day['end_time'] ?? null);

                if (! $start || $s->lessThan($start)) {
                    $start = $s;
                }
                if ($e && (! $end || $e->greaterThan($end))) {
                    $end = $e;
                }
            }

            if ($start) {
                return [$start, $end];
            }
        }

        if (! $this->start_date) {
            return [$this->starts_at, $this->ends_at];
        }

        $date = $this->start_date instanceof Carbon
            ? $this->start_date->format('Y-m-d')
            : (string) $this->start_date;

        return self::dayInterval($date, $this->start_time, $this->end_time);
    }

    public function scopeVisible(Builder $query, string $audience = 'all'): Builder
    {
        $now = now();

        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->when($audience === 'all', fn ($q) => $q->where('audience', 'all'))
            ->orderBy('starts_at');
    }
}
