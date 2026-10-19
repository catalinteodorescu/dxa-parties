<?php

namespace App\Support;

/**
 * DXA: adaugat (runda 79 — planuri Basic/Pro). Catalogul funcționalităților care se pot porni/opri pe plan.
 *
 * Fiecare funcționalitate are un comutator în pagina proprietarului. Legătura cu restul aplicației:
 *   - `sections`: secțiunile de permisiuni (Permissions::SECTIONS) pe care le blochează; fără funcționalitate pornită, nici
 *     un cont (nici superadmin-ul) nu mai vede secțiunea, iar meniul arată un lacăt;
 *   - `actions`: acțiunile sensibile (Permissions::ACTIONS) blocate odată cu ea;
 *   - funcțiile fără secțiuni/acțiuni se verifică direct (middleware `plan:cheie` sau `PlanGate::allows`).
 * Baza care ține aplicația în viață (autentificare, cont propriu, dashboard, Setări, Utilizatori) NU e aici și nu se oprește.
 *
 * `basic`: setarea inițială din planul Basic (Pro le are pe toate).
 * Ultimul câmp: funcția are și parte în aplicațiile participantului / Recepție / Bar (runda 80: rutele, meniurile și
 * serviciile centrale o respectă); fără el, comutatorul acționează doar în panoul admin.
 */
final class Features
{
    public const GROUPS = [
        'parties' => 'Petreceri',
        'content' => 'Conținut',
        'bar' => 'Bar',
        'reception' => 'Recepție',
        'participants' => 'Participanți',
        'apps' => 'Aplicații',
        'notifications' => 'Notificări',
        'dashboard' => 'Dashboard',
        'admin' => 'Administrare',
    ];

    /** cheie => [etichetă, grup, secțiuni, acțiuni, în Basic?, are parte în aplicații (runda 80)?] */
    public const ALL = [
        'parties' => ['Petreceri', 'parties', ['parties'], [], true, false],
        'party_stats' => ['Statistici petreceri', 'parties', ['party_stats'], [], false, false],
        'parties_overview' => ['Prezentare generală petreceri', 'parties', [], [], false, false],
        'reconciliation' => ['Bilanțul serii', 'parties', ['reconciliation'], [], false, false],
        'promoters' => ['Promotori și coduri de reducere', 'parties', ['promoters'], [], false, true],

        'announcements' => ['Anunțuri', 'content', ['announcements'], [], true, true],

        'bar_sales' => ['Vânzări bar', 'bar', ['sales'], [], true, true],
        'menu' => ['Meniu și categorii produse', 'bar', ['menu'], [], true, false],
        'stocks' => ['Stocuri', 'bar', ['stocks'], [], false, false],
        'requisitions' => ['Necesare', 'bar', ['requisitions'], [], false, false],
        'stock_reports' => ['Rapoarte de stoc', 'bar', ['stock_reports'], [], false, false],
        'bar_reports' => ['Rapoarte casă bar', 'bar', ['bar_reports'], [], true, true],
        'bar_pdf' => ['Export PDF rapoarte casă bar', 'bar', [], ['export_bar_reports'], false, false],
        'bar_stats' => ['Statistici bar', 'bar', ['bar_stats'], [], false, false],

        'reception_entries' => ['Intrări', 'reception', ['reception'], [], true, true],
        'reception_tokens' => ['Tokeni', 'reception', ['reception_tokens'], [], true, true],
        'reception_reports' => ['Rapoarte recepție', 'reception', ['reception_reports'], [], true, true],
        'reception_pdf' => ['Export PDF rapoarte recepție', 'reception', [], ['export_reception_reports'], false, false],
        'reception_stats' => ['Statistici recepție', 'reception', ['reception_stats'], [], false, false],

        'participants' => ['Participanți', 'participants', ['participants'], [], true, false],
        'participants_stats' => ['Statistici participanți', 'participants', [], [], false, false],
        'loyalty' => ['Carduri de fidelitate', 'participants', ['loyalty'], ['loyalty_adjust'], true, true],
        'credits' => ['Credite', 'participants', ['credits'], ['credits_adjust'], false, true],
        'credit_topups' => ['Încărcări de credite cu cardul', 'participants', [], [], false, true],
        'tickets' => ['Bilete (listă, anulare)', 'participants', ['tickets'], ['cancel_tickets'], true, false],
        'ticket_sending' => ['Trimiterea biletului altcuiva', 'participants', [], [], true, true],

        'app_reception' => ['Aplicația Recepție', 'apps', [], [], true, true],
        'app_bar' => ['Aplicația Bar', 'apps', [], [], true, true],
        'app_participant' => ['Aplicația participantului', 'apps', [], [], true, true],

        'push_participants' => ['Notificări push către participanți', 'notifications', [], ['manage_notifications'], true, true],
        'push_staff' => ['Notificări push către personal', 'notifications', [], ['manage_staff_notifications'], true, true],

        'dashboard_alerts' => ['Carduri „Atenție” (alerte)', 'dashboard', [], [], false, false],
        'dashboard_app' => ['Bloc „Aplicație”', 'dashboard', [], [], false, false],

        'legal' => ['Legal (termeni și politică)', 'admin', ['legal'], [], true, false],
        'logs' => ['Jurnal de activitate', 'admin', ['logs'], [], true, false],
    ];

    /** Limitele unui plan: cheie => [etichetă, unitate]. `null` = nelimitat. */
    public const LIMITS = [
        'max_superadmins' => ['Superadmini', 'conturi'],
        'max_admins' => ['Admini', 'conturi'],
        'sms_included' => ['SMS incluse pe lună', 'SMS'],
    ];

    public static function exists(string $feature): bool
    {
        return isset(self::ALL[$feature]);
    }

    public static function label(string $feature): string
    {
        return self::ALL[$feature][0] ?? $feature;
    }

    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** Funcțiile pornite implicit în Basic. */
    public static function basicDefaults(): array
    {
        return array_keys(array_filter(self::ALL, fn ($f) => $f[4]));
    }

    /** Funcționalitatea care blochează o secțiune de permisiuni (null = secțiunea nu depinde de plan). */
    public static function forSection(string $section): ?string
    {
        foreach (self::ALL as $key => $f) {
            if (in_array($section, $f[2], true)) {
                return $key;
            }
        }

        return null;
    }

    /** Funcționalitatea care blochează o acțiune sensibilă (null = depinde doar de secțiunea ei). */
    public static function forAction(string $action): ?string
    {
        foreach (self::ALL as $key => $f) {
            if (in_array($action, $f[3], true)) {
                return $key;
            }
        }

        return null;
    }

    /** Are funcția și parte în aplicațiile participantului / Recepție / Bar (blocată și în aplicații din runda 80)? */
    public static function hasAppPart(string $feature): bool
    {
        return (bool) (self::ALL[$feature][5] ?? false);
    }

    /** @return array<string, array<string, string>> grup => [cheie => etichetă] */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $label) {
            foreach (self::ALL as $key => $f) {
                if ($f[1] === $group) {
                    $out[$label][$key] = $f[0];
                }
            }
        }

        return $out;
    }
}
