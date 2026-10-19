<?php

namespace App\Services\Plan;

use App\Models\Admin;
use App\Models\License;
use App\Models\Plan;
use App\Support\Features;
use Illuminate\Database\QueryException;

/**
 * DXA: adaugat (runda 79 — planuri Basic/Pro). Răspunde la „are instalarea asta funcția X?” și „care e limita Y?”.
 *
 * Sursa e baza locală (tabelele `plans` și `license`), citită o dată pe cerere. Fără licență (tabele lipsă sau rând gol)
 * totul e permis, ca o instalare veche să nu se blocheze. Mai târziu (varianta B) se schimbă doar `load()`: licența vine de la
 * aplicația centrală, restul codului rămâne neatins.
 * Proprietarul (rolul `owner`) trece peste plan: vezi `Admin::planAllows`.
 */
final class PlanGate
{
    /** @var array{plan: ?Plan, features: array<string,bool>, limits: array}|null */
    private static ?array $state = null;

    public static function flush(): void
    {
        self::$state = null;
    }

    public static function allows(string $feature): bool
    {
        return self::state()['features'][$feature] ?? true;
    }

    /** Limita (null = nelimitat). */
    public static function limit(string $key): ?int
    {
        $v = self::state()['limits'][$key] ?? null;

        return $v === null ? null : (int) $v;
    }

    public static function plan(): ?Plan
    {
        return self::state()['plan'];
    }

    public static function planKey(): ?string
    {
        return self::state()['plan']?->key;
    }

    public static function planName(): string
    {
        return self::state()['plan']?->name ?? 'Fără licență';
    }

    /** Planurile care includ funcția (pentru pagina „disponibil în…”). @return string[] */
    public static function plansWith(string $feature): array
    {
        try {
            return Plan::query()->orderBy('id')->get()
                ->filter(fn (Plan $p) => in_array($feature, (array) $p->features, true))
                ->pluck('name')->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * Funcțiile unui plan ca hartă cheie => pornit?. Planul e singura sursă: nu există excepții pe client.
     *
     * @param  string[]  $planFeatures
     * @return array<string, bool>
     */
    public static function resolve(array $planFeatures): array
    {
        $out = [];
        foreach (Features::keys() as $key) {
            $out[$key] = in_array($key, $planFeatures, true);
        }

        return $out;
    }

    /** Conturile active de un rol (proprietarul nu se numără). */
    public static function accounts(string $role): int
    {
        return Admin::query()->where('role', $role)->where('is_active', true)->count();
    }

    /** Se mai poate adăuga/activa un cont cu rolul dat, în limita planului? */
    public static function canAddAccount(string $role): bool
    {
        $limit = self::limit($role === 'superadmin' ? 'max_superadmins' : 'max_admins');

        return $limit === null || self::accounts($role) < $limit;
    }

    /** Mesaj pentru limita atinsă. */
    public static function limitMessage(string $role): string
    {
        $limit = self::limit($role === 'superadmin' ? 'max_superadmins' : 'max_admins');
        $what = $role === 'superadmin' ? 'superadmin' : 'admin';

        return 'Planul '.self::planName().' permite maximum '.$limit.' '.($limit === 1 ? $what : $what.'i').' activi. Dezactivează un cont sau treci pe un plan mai mare.';
    }

    private static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        try {
            $license = License::query()->first();
            $plan = $license ? Plan::query()->where('key', $license->plan_key)->first() : null;
        } catch (QueryException) {
            $license = $plan = null;
        }

        if (! $license || ! $plan) {
            return self::$state = ['plan' => null, 'features' => array_fill_keys(Features::keys(), true), 'limits' => []];
        }

        return self::$state = [
            'plan' => $plan,
            'features' => self::resolve((array) $plan->features),
            'limits' => (array) $plan->limits,
        ];
    }
}
