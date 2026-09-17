<?php

namespace App\Models;

use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_category_id',
        'name',
        'quantity',
        'price',
        'tokens',
        'image_path',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tokens' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * Pretul in tokeni. Daca scoala foloseste tokeni (Settings::get('uses_tokens')),
     * coloana `tokens` e sursa de adevar. Altfel, e doar un echivalent calculat
     * din pretul in lei si curs — util daca politica se schimba mai tarziu.
     */
    public function tokenPrice(): float
    {
        if ($this->tokens !== null) {
            return (float) $this->tokens;
        }

        $rate = (float) Settings::get('token_rate');

        return $rate > 0 ? round(((float) $this->price) / $rate, 1) : 0.0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
