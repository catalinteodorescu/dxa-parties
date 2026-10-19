<?php

namespace App\Models;

use App\Services\Plan\PlanGate;
use App\Support\Features;
use App\Support\Permissions;
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
        'pin',
        'role',
        'is_active',
        'access_admin',
        'access_bar',
        'access_reception',
        'permissions',
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
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin' => 'hashed', // DXA: adaugat (runda 71)
            'is_active' => 'boolean',
            'access_admin' => 'boolean',
            'access_bar' => 'boolean',
            'access_reception' => 'boolean',
            'permissions' => 'array',
            'phone_needs_setup' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    /** Superadmin-ul și proprietarul au drepturi depline în panou (proprietarul trece și peste planul instalării). */
    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'owner'], true);
    }

    /** DXA: runda 79. Proprietarul instalării (furnizorul): ascuns din liste, nu intră în limitele de conturi, vede pagina de proprietar. */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Conturile vizibile clientului (fără proprietar). */
    public function scopeWithoutOwner(Builder $query): Builder
    {
        return $query->where('role', '!=', 'owner');
    }

    /** Planul instalării permite funcția? Proprietarul trece mereu. */
    public function planAllows(string $feature): bool
    {
        return $this->isOwner() || PlanGate::allows($feature);
    }

    /** Secțiunea e blocată doar de plan (contul ar avea altfel acces)? Pentru lacătul din meniu. */
    public function lockedByPlan(string $section, int|string $level = 'view'): bool
    {
        $feature = Features::forSection($section);

        return $feature !== null && ! $this->planAllows($feature) && $this->permitsIgnoringPlan($section, $level);
    }

    /** Are contul voie în aplicația dată? Superadmin-ul are acces la toate; ceilalți după bifele lor. */
    public function canAccess(string $app): bool
    {
        // proprietarul intră doar în panou (nu apare printre lucrătorii din Recepție/Bar și nu primește notificări de personal)
        if ($this->isOwner()) {
            return $app === self::APP_ADMIN;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $column = self::ACCESS_COLUMNS[$app] ?? null;

        return $column !== null && (bool) $this->{$column};
    }

    /**
     * DXA: adaugat (runda 46 — permisiuni). Nivelul contului într-o secțiune (0 fără acces … 3 ștergere).
     * Superadmin-ul are mereu nivelul maxim; ceilalți după matricea lor (fără matrice = fără acces).
     */
    public function permissionLevel(string $section): int
    {
        $max = Permissions::maxFor($section);

        if ($this->isSuperAdmin()) {
            return $max;
        }

        return min((int) ($this->permissions['levels'][$section] ?? Permissions::NONE), $max);
    }

    /** Are cel puțin nivelul cerut („view”, „edit”, „delete” sau numărul) în secțiune? */
    public function permits(string $section, int|string $level = 'view'): bool
    {
        $feature = Features::forSection($section);
        if ($feature !== null && ! $this->planAllows($feature)) {
            return false; // blocat de planul instalării (nici superadmin-ul nu trece)
        }

        return $this->permitsIgnoringPlan($section, $level);
    }

    /** Ca `permits`, dar fără planul instalării (ce ar putea face contul pe un plan care include funcția). */
    public function permitsIgnoringPlan(string $section, int|string $level = 'view'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $level = Permissions::levelFrom($level);

        return $level <= Permissions::NONE || $this->permissionLevel($section) >= $level;
    }

    /** Are bifa pentru o acțiune sensibilă (anulare, ajustare, export…)? */
    public function permitsAction(string $action): bool
    {
        $feature = Features::forAction($action);
        if ($feature !== null && ! $this->planAllows($feature)) {
            return false; // acțiunea ține de o funcție dezactivată pe plan
        }

        return $this->isSuperAdmin() || in_array($action, (array) ($this->permissions['actions'] ?? []), true);
    }

    /** Vede cel puțin una dintre secțiuni? (pentru grupurile din meniu) */
    public function permitsAny(string ...$sections): bool
    {
        foreach ($sections as $section) {
            if ($this->permits($section)) {
                return true;
            }
        }

        return false;
    }

    /** Conturile cu acces la o aplicație (superadmin-ii intră mereu). */
    public function scopeWithAccess(Builder $query, string $app): Builder
    {
        $column = self::ACCESS_COLUMNS[$app] ?? throw new \InvalidArgumentException('Aplicație necunoscută.');

        // runda 79: proprietarul nu e „cont cu acces” în nicio aplicație (nici ca lucrător, nici în numărători)
        return $query->where('role', '!=', 'owner')->where(fn ($q) => $q->where('role', 'superadmin')->orWhere($column, true));
    }
}
