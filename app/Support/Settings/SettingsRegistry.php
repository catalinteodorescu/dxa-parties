<?php

namespace App\Support\Settings;

use App\Support\Branding;
use App\Support\Theme;

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
 * 'text' (input text), 'select' (necesita 'options' => [valoare => eticheta]),
 * 'image' (upload de logo; valoarea salvata e calea pe disk-ul public; 'variant' =>
 * 'on_color'|'on_light' pentru previzualizare si logo implicit, vezi App\Support\Branding),
 * 'swatches' (alegere dintr-un set de culori prestabilite; 'options' =>
 * [cheie => ['label' => ..., 'color' => '#hex']] — folosit pentru culoarea temei).
 *
 * Optional pe orice camp: 'rules' => [...] (reguli de validare proprii, in loc de cele
 * implicite), 'wide' => true (input pe toata latimea), 'placeholder'.
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
                'key' => 'school',
                'label' => 'Școala',
                'description' => 'Numele, logo-urile și locația școlii. Numele apare în titlul paginilor, în PDF-uri și pe pagina de autentificare; adresa și linkul de hartă precompletează locația la petreceri noi.',
                'fields' => [
                    [
                        'key' => 'school_name',
                        'type' => 'text',
                        'label' => 'Numele școlii',
                        'default' => Branding::DEFAULT_NAME,
                        'wide' => true,
                        'rules' => ['required', 'string', 'max:100'],
                    ],
                    [
                        'key' => 'logo_on_color',
                        'type' => 'image',
                        'variant' => 'on_color',
                        'preview' => 'primary',
                        'label' => 'Logo pentru fundal colorat',
                        'help' => 'Varianta deschisă/albă, PNG cu fundal transparent. Apare în antetul panoului (pe culoarea temei) și pe pagina de autentificare.',
                    ],
                    [
                        'key' => 'logo_on_light',
                        'type' => 'image',
                        'variant' => 'on_light',
                        'preview' => 'white',
                        'label' => 'Logo pentru fundal deschis',
                        'help' => 'Varianta închisă/colorată, pe fundal alb sau transparent. Apare în PDF-uri și pe pagina de autentificare de pe telefon.',
                    ],
                    [
                        'key' => 'school_address',
                        'type' => 'text',
                        'label' => 'Adresă',
                        'placeholder' => 'Strada, număr, oraș',
                        'wide' => true,
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    [
                        'key' => 'school_maps_url',
                        'type' => 'text',
                        'label' => 'Link hartă',
                        'help' => 'Linkul de Google Maps al școlii (Distribuie → Copiază linkul).',
                        'placeholder' => 'https://maps.google.com/…',
                        'wide' => true,
                        'rules' => ['nullable', 'url', 'max:2048'],
                    ],
                ],
            ],
            [
                'key' => 'appearance',
                'label' => 'Aspect',
                'description' => 'Culoarea principală a aplicației: butoane, linkuri, accente și antetul paginii de autentificare.',
                'fields' => [
                    [
                        'key' => 'theme_color',
                        'type' => 'swatches',
                        'label' => 'Culoarea temei',
                        'help' => 'Se aplică imediat, în tot panoul de administrare. Culorile de stare (ștergere, editare, succes etc.) rămân neschimbate.',
                        'default' => Theme::DEFAULT,
                        'options' => collect(Theme::presets())
                            ->map(fn ($p) => ['label' => $p['label'], 'color' => $p['primary']])
                            ->all(),
                    ],
                ],
            ],
            [
                'key' => 'reception',
                'label' => 'Recepție',
                'description' => 'Reguli pentru înregistrarea intrărilor la recepție.',
                'fields' => [
                    [
                        'key' => 'entry_grace_minutes',
                        'type' => 'number',
                        'label' => 'Toleranță la ora reducerilor',
                        'help' => 'Un bilet cu reducere „până la 22:30” se acordă încă atâtea minute după oră (cu 10 minute, până la 22:40). Se aplică doar reducerilor cu oră; recepționerul poate oricând suprascrie prețul, cu motiv.',
                        'default' => 10,
                        'min' => 0,
                        'step' => 1,
                        'suffix' => 'minute',
                        'rules' => ['required', 'integer', 'min:0', 'max:60'],
                    ],
                ],
            ],
            [
                'key' => 'loyalty',
                'label' => 'Card de fidelitate',
                'description' => 'La X intrări, următoarea e gratis. Nu orice petrecere acordă ștampile — se alege pe fiecare petrecere în parte; nu orice participant are card — se înrolează din fișa lui.',
                'fields' => [
                    [
                        'key' => 'loyalty_enabled',
                        'type' => 'bool',
                        'label' => 'Folosim carduri de fidelitate',
                        'default' => false,
                    ],
                    [
                        'key' => 'loyalty_stamps_required',
                        'type' => 'number',
                        'label' => 'Ștampile până la intrarea gratis',
                        'help' => 'Un card nou pornește cu acest număr de cercuri + unul de „intrare gratis". Cardurile deja create nu se schimbă dacă modifici valoarea aici.',
                        'default' => 10,
                        'min' => 2,
                        'max' => 50,
                        'step' => 1,
                        'depends_on' => 'loyalty_enabled',
                        'rules' => ['required', 'integer', 'min:2', 'max:50'],
                    ],
                ],
            ],
            // Sectiuni viitoare (nu inca implementate — doar exemplu de forma):
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
        return static::allFields()[$key] ?? static::hiddenFields()[$key] ?? null;
    }

    /**
     * Setari care NU apar in formularul generic din Setari (nu sunt in sections()/allFields(), deci nici
     * nu se salveaza odata cu el), dar se citesc/scriu prin Settings::get()/set(). Sunt gestionate de
     * panoul „Metode de plata" (Livewire\Admin\Settings\PaymentMethods) prin App\Support\PaymentMethods.
     *
     *  - token_mode: 'active' | 'collect_only' | 'off' (vezi PaymentMethods::TOKEN_MODES);
     *  - uses_tokens: DERIVAT din token_mode (true cat timp tokenii nu sunt 'off'); il citeste restul aplicatiei;
     *  - token_rate: cati lei valoreaza 1 token;
     *  - credits_purchasable: participantii pot cumpara credite (doar cat metoda credit e activa).
     */
    public static function hiddenFields(): array
    {
        return [
            'token_mode' => ['key' => 'token_mode', 'type' => 'text', 'default' => 'active'],
            'uses_tokens' => ['key' => 'uses_tokens', 'type' => 'bool', 'default' => true],
            'token_rate' => ['key' => 'token_rate', 'type' => 'number', 'default' => 5],
            'credits_purchasable' => ['key' => 'credits_purchasable', 'type' => 'bool', 'default' => false],
            // DXA: adaugat (runda 22/23). Aplicația participanților — gestionate de pagina Setări › Aplicație participanți
            // (Livewire\Admin\ParticipantApp\AppSettings), citite prin App\Support\ParticipantAppSettings.
            // DXA: adaugat (runda 25). Marcaj intern (ultimul id de bilet la prima folosire): vezi TicketOrders::tierSoldCount().
            'tickets_tier_marker' => ['key' => 'tickets_tier_marker', 'type' => 'number', 'default' => null],
            'app_home_eyebrow' => ['key' => 'app_home_eyebrow', 'type' => 'text', 'default' => 'Hai în comunitate'],
            'app_home_title' => ['key' => 'app_home_title', 'type' => 'text', 'default' => 'Dansează și distrează-te alături de noi'],
            'app_home_parties' => ['key' => 'app_home_parties', 'type' => 'number', 'default' => 6],
            'app_home_announcements' => ['key' => 'app_home_announcements', 'type' => 'number', 'default' => 5],
            'app_show_past_parties' => ['key' => 'app_show_past_parties', 'type' => 'bool', 'default' => true],
            'app_registration_open' => ['key' => 'app_registration_open', 'type' => 'bool', 'default' => true],
            'app_code_ttl_minutes' => ['key' => 'app_code_ttl_minutes', 'type' => 'number', 'default' => 10],
            'app_code_max_attempts' => ['key' => 'app_code_max_attempts', 'type' => 'number', 'default' => 5],
            'app_contact_phone' => ['key' => 'app_contact_phone', 'type' => 'text', 'default' => ''],
            'app_contact_email' => ['key' => 'app_contact_email', 'type' => 'text', 'default' => ''],
            'app_contact_instagram' => ['key' => 'app_contact_instagram', 'type' => 'text', 'default' => ''],
            'app_contact_facebook' => ['key' => 'app_contact_facebook', 'type' => 'text', 'default' => ''],
            'app_contact_website' => ['key' => 'app_contact_website', 'type' => 'text', 'default' => ''],
            'app_terms_url' => ['key' => 'app_terms_url', 'type' => 'text', 'default' => ''],
            'app_privacy_url' => ['key' => 'app_privacy_url', 'type' => 'text', 'default' => ''],
            'app_sms_activation' => ['key' => 'app_sms_activation', 'type' => 'text', 'default' => 'DXA: codul tău de activare este {cod}. Expiră în {minute} minute.'],
            'app_sms_reset' => ['key' => 'app_sms_reset', 'type' => 'text', 'default' => 'DXA: resetează-ți parola aici: {link}'],
        ];
    }
}
