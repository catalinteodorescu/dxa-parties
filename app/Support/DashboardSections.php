<?php

namespace App\Support;

use App\Support\Settings\Settings;

/**
 * DXA: adaugat (runda 45). Secțiunile din dashboard: ordinea și activarea se aleg din Setări › Secțiuni dashboard.
 *
 * Stare salvată (setarea `dashboard_sections`, JSON): lista ordonată `[{"key": "...", "enabled": true}, ...]`.
 * Se potrivește mereu cu DEFINITIONS: o secțiune nouă (adăugată în cod) apare automat la final, ACTIVĂ, iar una scoasă din cod dispare.
 *
 * Secțiune nouă în dashboard = (1) fișierul `resources/views/livewire/admin/dashboard/_<cheie>.blade.php`,
 * (2) o intrare în DEFINITIONS. Testul `DashboardSectionsTest` verifică că cele două rămân sincronizate.
 */
class DashboardSections
{
    /** cheie => [etichetă, descriere scurtă, doar superadmin?] — în ordinea implicită. */
    public const DEFINITIONS = [
        'announcements' => ['Anunțuri', 'Anunțuri active, ultimele publicate', false],
        'parties' => ['Petreceri', 'Următoarea petrecere, statistici, următoarele petreceri', false],
        'bar' => ['Bar', 'Produse, stocuri, necesare, raportări, numărătoare', false],
        'reception' => ['Recepție', 'Sesiuni deschise, diferențe de casă', false],
        'discount_codes' => ['Coduri de reducere', 'Reduceri, coduri, promotori', false],
        'participants' => ['Participanți', 'Participanți, tokeni, credite, carduri', false],
        'admins' => ['Administratori', 'Conturi de admin și jurnal', true],
    ];

    public const SETTING = 'dashboard_sections';

    /**
     * Lista completă, în ordinea salvată (completată cu secțiunile noi).
     *
     * @return array<int, array{key: string, label: string, description: string, superadmin_only: bool, enabled: bool}>
     */
    public static function all(): array
    {
        $saved = json_decode((string) Settings::get(self::SETTING), true);
        $saved = is_array($saved) ? $saved : [];

        $out = [];
        $seen = [];
        foreach ($saved as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            if (! is_string($key) || ! isset(self::DEFINITIONS[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = self::row($key, (bool) ($row['enabled'] ?? true));
        }
        foreach (array_keys(self::DEFINITIONS) as $key) {
            if (! isset($seen[$key])) {
                $out[] = self::row($key, true);   // secțiune nouă: activă implicit
            }
        }

        return $out;
    }

    /** Cheile de afișat în dashboard, în ordine: doar cele active (și permise adminului). @return array<int, string> */
    public static function active(bool $isSuperAdmin): array
    {
        return collect(self::all())
            ->filter(fn ($s) => $s['enabled'] && ($isSuperAdmin || ! $s['superadmin_only']))
            ->pluck('key')->values()->all();
    }

    /** Salvează ordinea și activarea. @param array<int, array{key: string, enabled: bool}> $rows */
    public static function save(array $rows): void
    {
        Settings::set(self::SETTING, json_encode(array_map(
            fn ($r) => ['key' => $r['key'], 'enabled' => (bool) $r['enabled']],
            array_values($rows),
        )));
    }

    private static function row(string $key, bool $enabled): array
    {
        [$label, $description, $superOnly] = self::DEFINITIONS[$key];

        return ['key' => $key, 'label' => $label, 'description' => $description, 'superadmin_only' => $superOnly, 'enabled' => $enabled];
    }
}
