<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Statistici DEMO (stabile per anunț) — până la tracking-ul real din PWA.
     */
    public function demoViews(): int
    {
        return ($this->id * 137 + 89) % 900 + 100;
    }

    public function demoClicks(): int
    {
        return intdiv($this->demoViews(), ($this->id % 5) + 4);
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
