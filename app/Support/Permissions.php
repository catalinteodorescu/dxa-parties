<?php

namespace App\Support;

/**
 * DXA: adaugat (runda 46 — permisiuni utilizatori).
 *
 * Catalogul permisiunilor din panoul admin. Fiecare SECȚIUNE (o pagină sau un grup de pagini din meniu) are 4 niveluri
 * cumulative: 0 fără acces, 1 vizualizare, 2 modificare, 3 ștergere. În plus există ACȚIUNI sensibile, bifate separat
 * (ajustări, anulări, finalizări, export…): o acțiune sensibilă se poate face doar cu bifa ei, indiferent de nivel.
 * Superadmin-ul are mereu totul. Doar el setează permisiunile.
 *
 * Format stocat (coloana `admins.permissions`): {"levels": {"parties": 3, ...}, "actions": ["credits_adjust", ...]}
 */
final class Permissions
{
    public const NONE = 0;

    public const VIEW = 1;

    public const EDIT = 2;

    public const DELETE = 3;

    public const LEVEL_LABELS = [
        self::NONE => 'Fără acces',
        self::VIEW => 'Vizualizare',
        self::EDIT => 'Modificare',
        self::DELETE => 'Ștergere',
    ];

    public const LEVEL_NAMES = [
        'none' => self::NONE,
        'view' => self::VIEW,
        'edit' => self::EDIT,
        'delete' => self::DELETE,
    ];

    public const GROUPS = [
        'parties' => 'Petreceri',
        'content' => 'Conținut',
        'bar' => 'Bar',
        'reception' => 'Recepție',
        'participants' => 'Participanți',
        'admin' => 'Administrare',
    ];

    /** cheie => [label, grup, nivelul maxim care are sens]. Ordinea e cea din meniu. */
    public const SECTIONS = [
        'parties' => ['Petreceri', 'parties', self::DELETE],
        'party_stats' => ['Statistici petreceri', 'parties', self::VIEW],
        'reconciliation' => ['Bilanțul serii', 'parties', self::VIEW],
        'promoters' => ['Promotori și coduri de reducere', 'parties', self::DELETE],
        'announcements' => ['Anunțuri', 'content', self::DELETE],
        'sales' => ['Vânzări bar', 'bar', self::EDIT],
        'menu' => ['Meniu și categorii produse', 'bar', self::DELETE],
        'stocks' => ['Stocuri', 'bar', self::DELETE],
        'requisitions' => ['Necesare', 'bar', self::DELETE],
        'stock_reports' => ['Raportări stocuri', 'bar', self::DELETE],
        'bar_reports' => ['Raportări casă bar', 'bar', self::EDIT],
        'bar_stats' => ['Statistici bar', 'bar', self::VIEW],
        'reception' => ['Intrări', 'reception', self::EDIT],
        'reception_tokens' => ['Tokeni', 'reception', self::EDIT],
        'reception_reports' => ['Raportări recepție', 'reception', self::DELETE],
        'reception_stats' => ['Statistici recepție', 'reception', self::VIEW],
        'participants' => ['Participanți', 'participants', self::DELETE],
        'loyalty' => ['Carduri de fidelitate', 'participants', self::EDIT],
        'credits' => ['Credite', 'participants', self::VIEW],
        'tickets' => ['Bilete', 'participants', self::VIEW],   // runda 66
        'legal' => ['Legal', 'admin', self::EDIT],   // runda 68: Termeni și condiții + Politica de confidențialitate
        'users' => ['Utilizatori', 'admin', self::DELETE],
        'logs' => ['Jurnal activitate', 'admin', self::VIEW],
        'settings' => ['Setări', 'admin', self::DELETE],
    ];

    /**
     * Acțiunile sensibile, fiecare în secțiunea din care face parte: cheie => [etichetă, explicație, secțiune, metodele Livewire].
     * Bifa e suficientă singură (nu cere nivelul „Modifică”), dar secțiunea trebuie să fie măcar vizibilă.
     */
    public const ACTIONS = [
        'publish_parties' => ['Publicare', 'Publică petreceri.', 'parties', ['publish']],
        'publish_announcements' => ['Publicare', 'Publică anunțuri.', 'announcements', ['publish']],
        'cancel_sales' => ['Anulare vânzări', 'Anulează vânzări de bar.', 'sales', ['cancel']],
        'reopen_sales' => ['Redeschidere raportare', 'Redeschide raportarea unui grup de vânzări.', 'sales', ['askReopen', 'reopenReport']],
        'export_requisitions' => ['Export PDF', 'Descarcă necesarele în PDF.', 'requisitions', ['exportPdf']],
        'finalize_stock_reports' => ['Finalizare rapoarte', 'Închide rapoartele de stoc.', 'stock_reports', ['finalize']],
        'export_stock_reports' => ['Export PDF', 'Descarcă rapoartele de stoc în PDF.', 'stock_reports', ['exportPdf']],
        'stock_adjust' => ['Ajustare stoc și cost', 'Corectează cantitatea sau costul unui produs din stoc.', 'stocks', ['saveCostAdjustment', 'saveQtyAdjustment']],
        'reopen_bar_reports' => ['Redeschidere rapoarte', 'Redeschide rapoarte de casă finalizate.', 'bar_reports', ['askReopen', 'reopenReport']],
        'export_bar_reports' => ['Export PDF', 'Descarcă rapoartele de casă în PDF.', 'bar_reports', []],
        'cancel_entries' => ['Anulare intrări și vânzări', 'Anulează intrări și vânzări de tokeni sau de credite.', 'reception', ['cancel', 'cancelTokenSale', 'cancelCreditSale']],
        'tokens_adjust' => ['Ajustare și casare tokeni', 'Modifică sau casează tokeni.', 'reception_tokens', ['adjust', 'askWriteOff', 'writeOff']],
        'finalize_reception_reports' => ['Finalizare rapoarte', 'Închide rapoartele de recepție.', 'reception_reports', ['askFinalize', 'finalize']],
        'reopen_reception_reports' => ['Redeschidere rapoarte', 'Redeschide rapoarte de recepție finalizate.', 'reception_reports', ['askReopen', 'reopen']],
        'export_reception_reports' => ['Export PDF', 'Descarcă rapoartele de recepție în PDF.', 'reception_reports', ['exportPdf']],
        'credits_adjust' => ['Ajustare credite', 'Încărcare, ajustare și rambursare de credite.', 'participants', ['loadCredits', 'adjustCredits', 'refundCredits']],
        'cancel_tickets' => ['Anulare bilete', 'Anulează bilete și returnează banii în credite.', 'tickets', ['cancel']],   // runda 67
        'loyalty_adjust' => ['Ajustare ștampile', 'Modifică manual ștampilele cardului de fidelitate.', 'participants', ['adjustLoyaltyStamps']],
        'anonymize' => ['Anonimizare', 'Șterge datele personale ale unui participant.', 'participants', ['anonymize']],
    ];

    /** Acțiunile unei secțiuni: cheie => [etichetă, explicație]. */
    public static function actionsOf(string $section): array
    {
        $out = [];
        foreach (self::ACTIONS as $key => [$label, $hint, $sec]) {
            if ($sec === $section) {
                $out[$key] = [$label, $hint];
            }
        }

        return $out;
    }

    /** Acțiunea sensibilă cerută de o metodă într-o secțiune (null = metoda nu e sensibilă acolo). */
    public static function actionForMethod(string $section, string $method): ?string
    {
        foreach (self::ACTIONS as $key => [, , $sec, $methods]) {
            if ($sec === $section && in_array($method, $methods, true)) {
                return $key;
            }
        }

        return null;
    }

    /** Secțiunile cu nivelul lor maxim, în ordinea din meniu. */
    public static function sections(): array
    {
        $out = [];
        foreach (self::SECTIONS as $key => [$label, $group, $max]) {
            $out[$key] = ['label' => $label, 'group' => $group, 'max' => $max];
        }

        return $out;
    }

    public static function maxFor(string $section): int
    {
        return self::SECTIONS[$section][2] ?? self::NONE;
    }

    public static function isSection(string $section): bool
    {
        return isset(self::SECTIONS[$section]);
    }

    /** Textul („view”/„edit”/„delete”) sau numărul nivelului → număr. */
    public static function levelFrom(int|string $level): int
    {
        if (is_string($level) && ! is_numeric($level)) {
            return self::LEVEL_NAMES[$level] ?? throw new \InvalidArgumentException("Nivel necunoscut: {$level}");
        }

        return max(self::NONE, min(self::DELETE, (int) $level));
    }

    /** Nimic permis. */
    public static function none(): array
    {
        return ['levels' => [], 'actions' => []];
    }

    /** Totul, la nivelul maxim al fiecărei secțiuni, cu toate acțiunile sensibile. */
    public static function full(): array
    {
        return [
            'levels' => array_map(fn ($s) => $s[2], self::SECTIONS),
            'actions' => array_keys(self::ACTIONS),
        ];
    }

    /** Ce aveau adminii înainte de permisiuni: totul, în afară de Utilizatori și Jurnal (rezervate superadmin-ului). */
    public static function legacyAdmin(): array
    {
        $full = self::full();
        unset($full['levels']['users'], $full['levels']['logs']);

        return $full;
    }

    /** Doar vizualizare peste tot (fără acțiuni sensibile). */
    public static function viewOnly(): array
    {
        return [
            'levels' => array_map(fn ($s) => self::VIEW, self::SECTIONS),
            'actions' => [],
        ];
    }

    /** Curăță o intrare: secțiuni/acțiuni necunoscute dispar, nivelurile se încadrează în maximul secțiunii. */
    public static function normalize(?array $data): array
    {
        $levels = [];
        foreach ((array) ($data['levels'] ?? []) as $section => $level) {
            if (! is_string($section) || ! self::isSection($section)) {
                continue;
            }
            $level = min(self::levelFrom((int) $level), self::maxFor($section));
            if ($level > self::NONE) {
                $levels[$section] = $level;
            }
        }

        $actions = array_values(array_filter(
            array_keys(self::ACTIONS),
            fn ($a) => in_array($a, (array) ($data['actions'] ?? []), true)
        ));

        return ['levels' => $levels, 'actions' => $actions];
    }

    /** Diferența dintre două seturi, pe scurt, pentru jurnal. Gol când nu s-a schimbat nimic. */
    public static function describeChange(?array $old, ?array $new): string
    {
        $old = self::normalize($old);
        $new = self::normalize($new);
        $parts = [];

        foreach (self::SECTIONS as $key => [$label]) {
            $before = $old['levels'][$key] ?? self::NONE;
            $after = $new['levels'][$key] ?? self::NONE;
            if ($before !== $after) {
                $parts[] = $label.': '.mb_strtolower(self::LEVEL_LABELS[$after]);
            }
        }

        foreach (self::ACTIONS as $key => [$label, , $sectionKey]) {
            $had = in_array($key, $old['actions'], true);
            $has = in_array($key, $new['actions'], true);
            if ($had !== $has) {
                $parts[] = $label.' — '.mb_strtolower(self::SECTIONS[$sectionKey][0]).($has ? ' (da)' : ' (nu)');
            }
        }

        if (count($parts) > 6) {
            $rest = count($parts) - 6;
            $parts = array_slice($parts, 0, 6);
            $parts[] = "încă {$rest}";
        }

        return implode('; ', $parts);
    }
}
