<?php

namespace App\Support\Settings;

/**
 * Aici se defineste TOT ce exista in pagina de Setari: sectiunile si campurile
 * lor. Pagina (Livewire\Admin\Settings\Index) doar citeste array-ul asta si
 * randeaza automat formularul — nu trebuie atinsa cand adaugi o setare noua
 * de tip simplu (bool/number/text/select).
 *
 * Ca sa adaugi o setare noua intr-o sectiune existenta: adauga un element in
 * array-ul 'fields' al sectiunii respective.
 *
 * Ca sa adaugi o sectiune noua (ex. "Aspect" cu culoare accent + logo, sau
 * "Liste" cu nr. de elemente per pagina): adauga un nou array in sections(),
 * dupa modelul celui de mai jos. Daca un camp are nevoie de UI special (ex.
 * un color-picker sau upload de logo, nu doar un input simplu), adauga-i
 * 'type' => 'custom' si trateaza-l separat in blade-ul paginii de setari
 * (vezi sectiunea "custom fields" din livewire/admin/settings/index.blade.php).
 *
 * Tipuri de camp suportate acum: 'bool' (toggle), 'number' (input numeric),
 * 'text' (input text), 'select' (necesita 'options' => [valoare => eticheta]).
 *
 * 'depends_on' (optional) = cheia altui camp bool din aceeasi sectiune; cand
 * acela e dezactivat, campul e afisat dezactivat (disabled), nu ascuns.
 */
class SettingsRegistry
{
    public static function sections(): array
    {
        return [
            [
                'key' => 'tokens',
                'label' => 'Tokeni',
                'description' => 'Sistemul de bilete (tokeni) cumpărate de la recepție, folosite ca monedă internă pentru produsele din bar, în locul plății directe în lei.',
                'fields' => [
                    [
                        'key' => 'uses_tokens',
                        'type' => 'bool',
                        'label' => 'Folosește tokeni',
                        'help' => 'Activ = produsele din bar se prețuiesc în tokeni (prețul în lei se calculează automat din curs). Dezactivat = produsele se prețuiesc direct în lei.',
                        'default' => true,
                    ],
                    [
                        'key' => 'token_rate',
                        'type' => 'number',
                        'label' => 'Curs token → lei',
                        'help' => 'Câți lei valorează 1 token.',
                        'default' => 5,
                        'step' => 0.5,
                        'min' => 0,
                        'suffix' => 'lei / token',
                        'depends_on' => 'uses_tokens',
                    ],
                ],
            ],

            // Sectiuni viitoare (nu inca implementate — doar exemplu de forma):
            // ['key' => 'appearance', 'label' => 'Aspect', 'fields' => [
            //     ['key' => 'accent_color', 'type' => 'custom', 'label' => 'Culoare accent', ...],
            //     ['key' => 'logo_path', 'type' => 'custom', 'label' => 'Logo', ...],
            // ]],
            // ['key' => 'lists', 'label' => 'Liste', 'fields' => [
            //     ['key' => 'items_per_page', 'type' => 'number', 'label' => 'Elemente per pagină', 'default' => 15],
            // ]],
        ];
    }

    /**
     * Toate campurile din toate sectiunile, intr-un singur array plat
     * (cheie => definitie), util pentru citire/validare/salvare in masa.
     */
    public static function allFields(): array
    {
        $fields = [];

        foreach (static::sections() as $section) {
            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }

        return $fields;
    }

    public static function field(string $key): ?array
    {
        return static::allFields()[$key] ?? null;
    }
}
