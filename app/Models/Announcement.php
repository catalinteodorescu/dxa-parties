<?php

namespace App\Models;

use App\Services\ContentStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'image_path',
        'url',
        'url_label',
        'audience',
        'in_carousel',
        'in_list',
        'is_active',
        'status',
        'starts_at',
        'ends_at',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'in_carousel' => 'boolean',
            'in_list' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * URL public catre imagine (necesita `php artisan storage:link`).
     */
    public function imageUrl(): ?string
    {
        // asset() folosește host-ul din request (merge pe localhost, .test și
        // în producție), spre deosebire de Storage::url() care depinde de APP_URL.
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    /** Data publicării arătată în aplicație: începutul programat (dacă există), altfel crearea. */
    public function publishedAt(): ?Carbon
    {
        return $this->starts_at ?? $this->created_at;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Starea de afisare, pentru pill-ul din admin:
     * 'draft' (ciornă) | 'inactive' (publicat, dar ascuns manual)
     * | 'scheduled' (programat) | 'expired' (trecut) | 'live' (activ acum).
     */
    public function state(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'live';
    }

    /** DXA: runda 40 — contoare reale (vezi App\Services\ContentStats). Afișări = card vizibil în aplicație. */
    public function statViews(): int
    {
        return ContentStats::totals('announcement', $this->id)['impression'];
    }

    /** Deschideri ale paginii. */
    public function statOpens(): int
    {
        return ContentStats::totals('announcement', $this->id)['open'];
    }

    /** Click-uri pe acțiune (butonul cu link al anunțului). */
    public function statActions(): int
    {
        return ContentStats::totals('announcement', $this->id)['action'];
    }

    /**
     * Anunturile efectiv vizibile in app acum (folosit mai tarziu de partea mobila).
     * $audience: 'all' pentru vizitatori nelogati, 'auth' pentru utilizatori logati.
     */
    public function scopeVisible(Builder $query, string $audience = 'all'): Builder
    {
        $now = now();

        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->when($audience === 'all', fn ($q) => $q->where('audience', 'all'))
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }
}
