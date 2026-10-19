<?php

namespace App\Services;

use App\Support\Branding;
use App\Support\ParticipantApp;
use App\Support\Settings\Settings;

/**
 * DXA: adaugat (runda 83). Modelele implicite ale Termenilor și Politicii de confidențialitate (resources/legal/*.txt), cu variabile `{{cheie}}`:
 *   - datele organizatorului (clientul, operatorul datelor): se completează o dată, la Legal › Date organizator sau cu `app:install-legal`;
 *   - datele furnizorului tehnic și ale furnizorului de SMS: din config/legal.php;
 *   - numele aplicației, domeniul și data: calculate.
 * O variabilă fără valoare rămâne în text ca `{{cheie}}`; textul cu variabile rămase NU se poate publica (vezi Terms::publish / PrivacyPolicy::publish).
 */
class LegalTemplates
{
    /** @var array<string, string> cheie => etichetă (datele organizatorului, păstrate în Setări) */
    public const ORGANIZER_FIELDS = [
        'organizer_name' => 'Denumirea organizatorului',
        'organizer_cui' => 'CUI',
        'organizer_regcom' => 'Nr. înregistrare la Registrul Comerțului',
        'organizer_address' => 'Sediul',
        'organizer_email' => 'E-mail de contact',
        'organizer_phone' => 'Telefon de contact',
    ];

    public const KINDS = [
        'terms' => 'termeni-si-conditii.txt',
        'privacy' => 'politica-de-confidentialitate.txt',
    ];

    private const SETTING_PREFIX = 'legal_';

    /** Datele organizatorului, așa cum sunt salvate (denumirea cade pe numele organizației din Setări). @return array<string, string> */
    public static function organizer(): array
    {
        $out = [];
        foreach (array_keys(self::ORGANIZER_FIELDS) as $key) {
            $out[$key] = trim((string) Settings::get(self::SETTING_PREFIX.$key));
        }
        if ($out['organizer_name'] === '') {
            $out['organizer_name'] = trim(Branding::name());
        }

        return $out;
    }

    /** @param  array<string, string>  $data  doar cheile din ORGANIZER_FIELDS; restul se ignoră */
    public static function saveOrganizer(array $data): void
    {
        foreach (array_keys(self::ORGANIZER_FIELDS) as $key) {
            if (array_key_exists($key, $data)) {
                Settings::set(self::SETTING_PREFIX.$key, trim((string) $data[$key]));
            }
        }
    }

    /** Datele organizatorului care încă lipsesc (etichete). @return list<string> */
    public static function missingOrganizerFields(): array
    {
        $have = self::organizer();

        return array_values(array_map(fn ($k) => self::ORGANIZER_FIELDS[$k], array_keys(array_filter(self::ORGANIZER_FIELDS, fn ($l, $k) => $have[$k] === '', ARRAY_FILTER_USE_BOTH))));
    }

    /** Toate variabilele cunoscute, cu valorile de acum (goale unde lipsesc). @return array<string, string> */
    public static function values(): array
    {
        $provider = (array) config('legal.provider');
        $contact = 'tel. '.($provider['phone'] ?? '');
        if (filled($provider['email'] ?? null)) {
            $contact = 'e-mail '.$provider['email'].', '.$contact;
        }

        return self::organizer() + [
            'app_name' => trim(ParticipantApp::name()),
            'app_domain' => self::domain(),
            'date' => now()->format('d.m.Y'),
            'provider_address' => (string) ($provider['address'] ?? ''),
            'provider_contact' => $contact,
            'sms_provider' => (string) config('legal.sms_provider'),
        ];
    }

    public static function domain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }

    public static function template(string $kind): string
    {
        $file = self::KINDS[$kind] ?? null;
        abort_unless($file !== null, 404);

        return str_replace("\r\n", "\n", (string) file_get_contents(resource_path('legal/'.$file)));
    }

    /** Înlocuiește variabilele care au valoare; celelalte rămân `{{cheie}}`. @param  array<string, string>|null  $values */
    public static function fill(string $text, ?array $values = null): string
    {
        $values ??= self::values();

        return (string) preg_replace_callback('/\{\{([a-z_]+)\}\}/', fn ($m) => ($values[$m[1]] ?? '') !== '' ? $values[$m[1]] : $m[0], $text);
    }

    /** Modelul unui text, completat cu datele de acum. */
    public static function rendered(string $kind): string
    {
        return self::fill(self::template($kind));
    }

    /** Variabilele rămase necompletate într-un text, ca etichete (pentru mesajul de blocare a publicării). @return list<string> */
    public static function unresolved(string $text): array
    {
        preg_match_all('/\{\{([a-z_]+)\}\}/', $text, $m);

        return array_values(array_unique(array_map(
            fn ($k) => self::ORGANIZER_FIELDS[$k] ?? $k,
            $m[1],
        )));
    }

    /** Aruncă DomainException dacă textul mai conține variabile necompletate. */
    public static function assertResolved(string $text): void
    {
        $left = self::unresolved($text);
        if ($left !== []) {
            throw new \DomainException('Textul conține câmpuri necompletate: '.implode(', ', $left).'. Completează-le la „Date organizator” (sau scrie-le direct în text) și reîncarcă modelul.');
        }
    }
}
