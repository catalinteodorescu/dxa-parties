<?php

namespace App\Support\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Punctul unic de citire/scriere a setarilor din restul aplicatiei.
 * Nu citi/scrie direct din App\Models\Setting in afara acestei clase —
 * aici se aplica tipul corect (bool/number/text) conform SettingsRegistry.
 *
 * Exemplu: Settings::get('uses_tokens'), Settings::get('token_rate').
 */
class Settings
{
    private const CACHE_KEY = 'dxa.settings.values';

    public static function get(string $key)
    {
        $field = SettingsRegistry::field($key);
        $default = $field['default'] ?? null;

        $raw = static::allRaw()[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        return match ($field['type'] ?? 'text') {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($raw) ? (float) $raw : $default,
            default => $raw,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $field = SettingsRegistry::field($key);

        $stored = match ($field['type'] ?? 'text') {
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Toate valorile brute (necastate) din DB, cheie => valoare string.
     * Cache scurt ca sa nu lovim baza de date de fiecare data cand un formular
     * (ex. produsele din bar) verifica daca se folosesc tokeni.
     */
    private static function allRaw(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });
    }
}
