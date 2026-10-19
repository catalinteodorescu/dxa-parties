<?php

namespace App\Support;

use App\Models\Admin;
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
        'alerts' => ['Atenție', 'Ce cere o acțiune acum: stoc sub minim, sesiuni uitate, diferențe de casă, plăți blocate', false], // runda 75
        'announcements' => ['Anunțuri', 'Anunțuri active, ultimele publicate', false],
        'parties' => ['Petreceri', 'Următoarea petrecere, statistici, următoarele petreceri', false],
        'bar' => ['Bar', 'Produse, stocuri, necesare, raportări, numărătoare', false],
        'reception' => ['Recepție', 'Sesiuni deschise, diferențe de casă', false],
        'discount_codes' => ['Coduri de reducere', 'Reduceri, coduri, promotori', false],
        'participants' => ['Participanți', 'Participanți, tokeni, credite, carduri', false],
        'app' => ['Aplicație', 'Conturi și activitate, notificări push, vizite și instalări', false], // runda 75
        'admins' => ['Administratori', 'Conturi de admin și jurnal', true],
    ];

    /** DXA: runda 46 — secțiunea de dashboard => secțiunile de permisiuni de care depinde (o secțiune e vizibilă dacă vezi cel puțin una). */
    public const PERMISSIONS = [
        'alerts' => ['stocks', 'reception_reports', 'stock_reports', 'tickets', 'credits'],
        'announcements' => ['announcements'],
        'parties' => ['parties', 'party_stats'],
        'bar' => ['sales', 'menu', 'stocks', 'requisitions', 'stock_reports', 'bar_reports', 'bar_stats'],
        'reception' => ['reception', 'reception_tokens', 'reception_reports', 'reception_stats'],
        'discount_codes' => ['promoters'],
        'participants' => ['participants', 'loyalty', 'credits', 'tickets'],
        'app' => ['participants'],
        'admins' => ['users', 'logs'],
    ];

    /** DXA: runda 75 — secțiunile care, când apar prima dată (fără ordine salvată), se pun SUS, nu la final. */
    public const PINNED_TOP = ['alerts'];

    /** DXA: runda 79 — secțiunea de dashboard => funcționalitatea de plan de care depinde (planul fără ea nu o arată). */
    public const PLAN_FEATURES = [
        'alerts' => 'dashboard_alerts',
        'app' => 'dashboard_app',
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
                // secțiune nouă: activă implicit (cele din PINNED_TOP, sus; restul, la final)
                in_array($key, self::PINNED_TOP, true) ? array_unshift($out, self::row($key, true)) : $out[] = self::row($key, true);
            }
        }

        return $out;
    }

    /** Cheile de afișat în dashboard, în ordine: doar cele active (și permise adminului). @return array<int, string> */
    public static function active(bool|Admin $viewer): array
    {
        return collect(self::all())
            ->filter(function ($s) use ($viewer) {
                if (! $s['enabled']) {
                    return false;
                }

                // Un cont real: vezi secțiunea doar dacă ai acces la măcar o pagină din ea (runda 46).
                if ($viewer instanceof Admin) {
                    if (isset(self::PLAN_FEATURES[$s['key']]) && ! $viewer->planAllows(self::PLAN_FEATURES[$s['key']])) {
                        return false;
                    }

                    return $viewer->permitsAny(...(self::PERMISSIONS[$s['key']] ?? []));
                }

                return $viewer || ! $s['superadmin_only'];
            })
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
