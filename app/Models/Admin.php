<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasFactory;

    // DXA: adaugat (Utilizatori - acces pe aplicație). Aplicațiile în care se poate autentifica un utilizator.
    public const APP_ADMIN = 'admin';

    public const APP_BAR = 'bar';

    public const APP_RECEPTION = 'reception';

    public const APP_LABELS = [
        self::APP_ADMIN => 'Panou admin',
        self::APP_BAR => 'Aplicație bar',
        self::APP_RECEPTION => 'Aplicație recepție',
    ];

    /** Aplicația => coloana cu bifa de acces. */
    public const ACCESS_COLUMNS = [
        self::APP_ADMIN => 'access_admin',
        self::APP_BAR => 'access_bar',
        self::APP_RECEPTION => 'access_reception',
    ];

    protected $fillable = [
        'name',
        'phone',
        'password',
        'role',
        'is_active',
        'access_admin',
        'access_bar',
        'access_reception',
        'phone_needs_setup',
        'activated_at',
    ];

    /** Aceleași valori implicite ca în migrare, ca și un cont proaspăt creat (nerecitit din DB) să știe ce acces are. */
    protected $attributes = [
        'access_admin' => true,
        'access_bar' => false,
        'access_reception' => false,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'access_admin' => 'boolean',
            'access_bar' => 'boolean',
            'access_reception' => 'boolean',
            'phone_needs_setup' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** Are contul voie în aplicația dată? Superadmin-ul are acces la toate; ceilalți după bifele lor. */
    public function canAccess(string $app): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $column = self::ACCESS_COLUMNS[$app] ?? null;

        return $column !== null && (bool) $this->{$column};
    }

    /** Conturile cu acces la o aplicație (superadmin-ii intră mereu). */
    public function scopeWithAccess(Builder $query, string $app): Builder
    {
        $column = self::ACCESS_COLUMNS[$app] ?? throw new \InvalidArgumentException('Aplicație necunoscută.');

        return $query->where(fn ($q) => $q->where('role', 'superadmin')->orWhere($column, true));
    }
}
