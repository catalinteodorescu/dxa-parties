<?php

namespace App\Livewire\Admin\ParticipantApp;

use App\Livewire\Admin\PwaAppSettings;
use App\Support\Branding;
use App\Support\ParticipantApp;
use App\Support\ParticipantAppSettings;
use App\Support\Settings\Settings;
use App\Support\Theme;
use Closure;
use Livewire\Attributes\Layout;

/**
 * DXA: adaugat (runda 23). Setări › Aplicație participanți: identitate (nume, logo, temă pentru iconiță, din PwaAppSettings)
 * plus textele și conținutul din Acasă, regulile conturilor, contact, linkuri legale și textele SMS. Valorile se citesc
 * prin App\Support\ParticipantAppSettings (cheile sunt în SettingsRegistry::hiddenFields).
 */
#[Layout('layouts.admin')]
class AppSettings extends PwaAppSettings
{
    /** Valorile câmpurilor suplimentare, cheie de setare => valoare (bool pentru toggle-uri, text pentru restul). */
    public array $values = [];

    protected function appClass(): string
    {
        return ParticipantApp::class;
    }

    protected function heading(): string
    {
        return 'Participanți · Aplicație';
    }

    protected function appPhrase(): string
    {
        return 'aplicației participanților';
    }

    protected function logEvent(): string
    {
        return 'settings.participant_app_updated';
    }

    /**
     * Cardurile cu câmpuri (în afară de identitate). type: text | number | bool | textarea.
     *
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public function groups(): array
    {
        return [
            [
                'title' => 'Pagina Acasă',
                'description' => 'Textele și cât conținut apare în Acasă și în lista de petreceri.',
                'fields' => [
                    ['key' => 'app_home_eyebrow', 'type' => 'text', 'label' => 'Text mic deasupra titlului', 'max' => 60],
                    ['key' => 'app_home_title', 'type' => 'text', 'label' => 'Titlul din Acasă', 'max' => 120],
                    ['key' => 'app_home_parties', 'type' => 'number', 'label' => 'Petreceri afișate în Acasă', 'help' => 'Restul se văd la „Vezi toate”.', 'min' => 1, 'max' => 20],
                    ['key' => 'app_home_announcements', 'type' => 'number', 'label' => 'Anunțuri afișate în Acasă', 'help' => '0 = secțiunea „Anunțuri” nu apare în Acasă (rămâne pagina Anunțuri).', 'min' => 0, 'max' => 20],
                    ['key' => 'app_show_past_parties', 'type' => 'bool', 'label' => 'Arată petrecerile trecute', 'help' => 'Secțiunea „Trecute” din pagina Petreceri.'],
                ],
            ],
            [
                'title' => 'Conturi',
                'description' => 'Reguli pentru crearea conturilor și codul primit prin SMS.',
                'fields' => [
                    ['key' => 'app_registration_open', 'type' => 'bool', 'label' => 'Înregistrare conturi deschisă', 'help' => 'Oprită: nimeni nu își mai poate face cont nou. Conturile existente merg ca de obicei.'],
                    ['key' => 'app_code_ttl_minutes', 'type' => 'number', 'label' => 'Valabilitatea codului SMS', 'suffix' => 'minute', 'min' => 2, 'max' => 60],
                    ['key' => 'app_code_max_attempts', 'type' => 'number', 'label' => 'Încercări greșite permise pe un cod', 'min' => 3, 'max' => 10],
                ],
            ],
            [
                'title' => 'Contact',
                'description' => 'Apare în subsolul aplicației, împreună cu adresa și linkul de hartă din Setări generale. Lasă gol ce nu vrei să afișezi.',
                'fields' => [
                    ['key' => 'app_contact_phone', 'type' => 'text', 'label' => 'Telefon', 'placeholder' => '07xx xxx xxx', 'max' => 40],
                    ['key' => 'app_contact_email', 'type' => 'text', 'label' => 'Email', 'placeholder' => 'contact@exemplu.ro', 'max' => 120],
                    ['key' => 'app_contact_instagram', 'type' => 'text', 'label' => 'Instagram (link)', 'placeholder' => 'https://instagram.com/…', 'max' => 2048],
                    ['key' => 'app_contact_facebook', 'type' => 'text', 'label' => 'Facebook (link)', 'placeholder' => 'https://facebook.com/…', 'max' => 2048],
                    ['key' => 'app_contact_website', 'type' => 'text', 'label' => 'Site (link)', 'placeholder' => 'https://…', 'max' => 2048],
                ],
            ],
            [
                'title' => 'Legal',
                'description' => 'Linkurile apar în subsolul aplicației și sub formularul de cont nou.',
                'fields' => [
                    ['key' => 'app_terms_url', 'type' => 'text', 'label' => 'Termeni și condiții (link)', 'placeholder' => 'https://…', 'max' => 2048],
                    ['key' => 'app_privacy_url', 'type' => 'text', 'label' => 'Politica de confidențialitate (link)', 'placeholder' => 'https://…', 'max' => 2048],
                ],
            ],
            [
                'title' => 'Texte SMS',
                'description' => 'Mesajele trimise participanților. Locurile din acolade se completează automat; fără ele, textul nu e valid.',
                'fields' => [
                    ['key' => 'app_sms_activation', 'type' => 'textarea', 'label' => 'SMS cu codul de activare', 'help' => 'Obligatoriu {cod}. Opțional: {minute} (valabilitatea), {aplicatie} (numele aplicației).', 'max' => 320, 'sample' => ['cod' => '123456', 'minute' => '10']],
                    ['key' => 'app_sms_reset', 'type' => 'textarea', 'label' => 'SMS de resetare a parolei', 'help' => 'Obligatoriu {link}. Opțional: {aplicatie}.', 'max' => 320, 'sample' => ['link' => 'https://exemplu.ro/r/abc123']],
                ],
            ],
        ];
    }

    /** Previzualizarea unui SMS cu valorile de acum din formular + câte caractere are și dacă are diacritice. */
    public function smsPreview(string $key, array $sample): array
    {
        $template = trim((string) ($this->values[$key] ?? ''));
        $text = ParticipantAppSettings::fill($template, $sample);

        return [
            'text' => $text,
            'length' => mb_strlen($text),
            'unicode' => preg_match('/[^\x20-\x7E\n]/u', $text) === 1,
        ];
    }

    public function mount(): void
    {
        parent::mount();

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $f) {
                $v = Settings::get($f['key']);
                $this->values[$f['key']] = $f['type'] === 'bool' ? (bool) $v : ($f['type'] === 'number' ? (string) (int) $v : (string) $v);
            }
        }
    }

    protected function rules(): array
    {
        $url = ['nullable', 'url', 'max:2048'];
        $needs = fn (string $placeholder): Closure => function ($attribute, $value, $fail) use ($placeholder) {
            if (! str_contains((string) $value, $placeholder)) {
                $fail('Textul trebuie să conțină '.$placeholder.'.');
            }
        };

        return parent::rules() + [
            'values.app_home_eyebrow' => ['required', 'string', 'max:60'],
            'values.app_home_title' => ['required', 'string', 'max:120'],
            'values.app_home_parties' => ['required', 'integer', 'min:1', 'max:20'],
            'values.app_home_announcements' => ['required', 'integer', 'min:0', 'max:20'],
            'values.app_show_past_parties' => ['boolean'],
            'values.app_registration_open' => ['boolean'],
            'values.app_code_ttl_minutes' => ['required', 'integer', 'min:2', 'max:60'],
            'values.app_code_max_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'values.app_contact_phone' => ['nullable', 'string', 'max:40'],
            'values.app_contact_email' => ['nullable', 'email', 'max:120'],
            'values.app_contact_instagram' => $url,
            'values.app_contact_facebook' => $url,
            'values.app_contact_website' => $url,
            'values.app_terms_url' => $url,
            'values.app_privacy_url' => $url,
            'values.app_sms_activation' => ['required', 'string', 'max:320', $needs('{cod}')],
            'values.app_sms_reset' => ['required', 'string', 'max:320', $needs('{link}')],
        ];
    }

    protected function validationAttributes(): array
    {
        $names = [];
        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $f) {
                $names['values.'.$f['key']] = mb_strtolower($f['label']);
            }
        }

        return $names;
    }

    public function save(): void
    {
        parent::save();   // validează tot (inclusiv câmpurile de mai jos) și salvează identitatea

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $f) {
                $v = $this->values[$f['key']] ?? '';
                Settings::set($f['key'], match ($f['type']) {
                    'bool' => (bool) $v,
                    'number' => (int) $v,
                    default => trim((string) $v),
                });
            }
        }
    }

    public function render()
    {
        return view('livewire.admin.participant-app.app-settings', [
            'pwa' => ParticipantApp::class,
            'heading' => $this->heading(),
            'appPhrase' => $this->appPhrase(),
            'presets' => Theme::presets(),
            'logo' => $this->logoPreview(),
            'preview' => Theme::preset($this->theme),
            'iconOnly' => true,
            'groups' => $this->groups(),
            'schoolAddress' => Branding::address(),
        ]);
    }
}
