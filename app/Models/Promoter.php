<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Coduri de reducere - promotori). Cine aduce lume la petreceri; are coduri la una sau mai multe petreceri.
 */
class Promoter extends Model
{
    protected $fillable = ['name', 'phone', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function codes(): HasMany
    {
        return $this->hasMany(PartyDiscountCode::class);
    }

    /** Promotorul cu acest nume (fără diferență între litere mari/mici), sau null. */
    public static function findByName(string $name, ?int $exceptId = null): ?self
    {
        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->first();
    }
}
