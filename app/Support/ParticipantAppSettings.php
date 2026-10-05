<?php

namespace App\Support;

use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;

/**
 * DXA: adaugat (runda 22). Citirea setărilor „Aplicația participanților” (secțiunea din Setări, vezi SettingsRegistry).
 * Un singur loc pentru valorile implicite și limitele; restul aplicației nu citește cheile direct.
 */
class ParticipantAppSettings
{
    public const DEFAULT_EYEBROW = 'Hai în comunitate';

    public const DEFAULT_TITLE = 'Dansează și distrează-te alături de noi';

    public static function homeEyebrow(): string
    {
        return self::text('app_home_eyebrow', self::DEFAULT_EYEBROW);
    }

    public static function homeTitle(): string
    {
        return self::text('app_home_title', self::DEFAULT_TITLE);
    }

    /** Câte petreceri următoare apar în Acasă. */
    public static function homeParties(): int
    {
        return self::int('app_home_parties', 1, 20);
    }

    /** Câte anunțuri apar în Acasă (0 = secțiunea dispare). */
    public static function homeAnnouncements(): int
    {
        return self::int('app_home_announcements', 0, 20);
    }

    public static function showPastParties(): bool
    {
        return (bool) Settings::get('app_show_past_parties');
    }

    public static function registrationOpen(): bool
    {
        return (bool) Settings::get('app_registration_open');
    }

    public static function codeTtlMinutes(): int
    {
        return self::int('app_code_ttl_minutes', 2, 60);
    }

    public static function codeMaxAttempts(): int
    {
        return self::int('app_code_max_attempts', 3, 10);
    }

    /**
     * Datele de contact completate, pentru afișare în aplicație: [['label' => ..., 'text' => ..., 'href' => ...], ...].
     * Adresa și linkul de hartă vin din Setări generale (Branding), nu se repetă aici.
     *
     * @return array<int, array{label: string, text: string, href: string}>
     */
    public static function contacts(): array
    {
        $items = [];

        if (($phone = self::raw('app_contact_phone')) !== '') {
            $items[] = ['label' => 'Telefon', 'text' => $phone, 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone)];
        }
        if (($email = self::raw('app_contact_email')) !== '') {
            $items[] = ['label' => 'Email', 'text' => $email, 'href' => 'mailto:'.$email];
        }
        foreach (['app_contact_instagram' => 'Instagram', 'app_contact_facebook' => 'Facebook', 'app_contact_website' => 'Site'] as $key => $label) {
            if (($url = self::raw($key)) !== '') {
                $items[] = ['label' => $label, 'text' => $label, 'href' => $url];
            }
        }
        if (($address = trim((string) Branding::address())) !== '') {
            $maps = trim((string) Branding::mapsUrl());
            $items[] = ['label' => 'Adresă', 'text' => $address, 'href' => $maps];
        }

        return $items;
    }

    /** @return array<int, array{label: string, href: string}> */
    public static function legalLinks(): array
    {
        $links = [];
        if (($t = self::raw('app_terms_url')) !== '') {
            $links[] = ['label' => 'Termeni și condiții', 'href' => $t];
        }
        if (($p = self::raw('app_privacy_url')) !== '') {
            $links[] = ['label' => 'Politica de confidențialitate', 'href' => $p];
        }

        return $links;
    }

    /** Textul SMS-ului cu codul de activare ({cod}, {minute}, {aplicatie}). */
    public static function smsActivation(string $code): string
    {
        return self::fill(self::template('app_sms_activation'), ['cod' => $code, 'minute' => (string) self::codeTtlMinutes()]);
    }

    /** Textul SMS-ului de resetare a parolei ({link}, {aplicatie}). */
    public static function smsReset(string $link): string
    {
        return self::fill(self::template('app_sms_reset'), ['link' => $link]);
    }

    /** Șablonul curent (sau cel implicit dacă a rămas gol / fără locul obligatoriu al codului sau al linkului). */
    public static function template(string $key): string
    {
        $default = (string) SettingsRegistry::field($key)['default'];
        $value = trim((string) Settings::get($key));
        $required = $key === 'app_sms_activation' ? '{cod}' : '{link}';

        return $value !== '' && str_contains($value, $required) ? $value : $default;
    }

    /** @param array<string, string> $vars */
    public static function fill(string $template, array $vars): string
    {
        $vars += ['aplicatie' => ParticipantApp::name()];

        return str_replace(array_map(fn ($k) => '{'.$k.'}', array_keys($vars)), array_values($vars), $template);
    }

    private static function raw(string $key): string
    {
        return trim((string) Settings::get($key));
    }

    private static function text(string $key, string $default): string
    {
        $v = trim((string) Settings::get($key));

        return $v !== '' ? $v : $default;
    }

    private static function int(string $key, int $min, int $max): int
    {
        return max($min, min($max, (int) round((float) Settings::get($key))));
    }
}
