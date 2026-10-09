<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ScrollsToFirstError;
use App\Services\ActivityLogger;
use App\Support\Branding;
use App\Support\PwaApp;
use App\Support\Settings\Settings;
use App\Support\Theme;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * DXA: adaugat (PWA Recepție / Bar - setări aplicație). Baza paginii din admin unde se aleg: numele și logo-ul, apoi
 * tema de culoare a unei aplicații PWA. Tema colorează aplicația ȘI iconița de pe ecranul telefonului (gradient în
 * culorile ei). Subclasele (ReceptionApp\AppSettings, BarApp\AppSettings) dau doar clasa aplicației și titlul.
 */
abstract class PwaAppSettings extends Component
{
    use ScrollsToFirstError;
    use WithFileUploads;

    public string $theme = '';

    public string $name = '';

    /** Logo ales acum, încă nesalvat. */
    public $logoUpload = null;

    /** A cerut revenirea la logo-ul școlii. */
    public bool $logoRemoved = false;

    /** @return class-string<PwaApp> */
    abstract protected function appClass(): string;

    /** Titlul paginii, ex. „Recepție · Aplicație”. */
    abstract protected function heading(): string;

    /** Textul „aplicația de recepție” (pentru descriere și mesaje). */
    abstract protected function appPhrase(): string;

    /** Numele evenimentului din jurnalul de activitate. */
    abstract protected function logEvent(): string;

    public function mount(): void
    {
        $this->theme = $this->appClass()::themeKey();
        $this->name = (string) Settings::get($this->appClass()::KEY_NAME);
    }

    public function selectTheme(string $key): void
    {
        if (isset(Theme::presets()[$key])) {
            $this->theme = $key;
        }
    }

    public function removeLogo(): void
    {
        $this->logoUpload = null;
        $this->logoRemoved = true;
    }

    public function updatedLogoUpload(): void
    {
        $this->logoRemoved = false;
    }

    /** @return array{url: string, custom: bool} */
    public function logoPreview(): array
    {
        if ($this->logoUpload) {
            try {
                return ['url' => $this->logoUpload->temporaryUrl(), 'custom' => true];
            } catch (\Throwable) {
                // fișier non-imagine: cade pe logo-ul curent, validarea afișează eroarea
            }
        }

        if (! $this->logoRemoved && $this->appClass()::uploadedLogoPath()) {
            return ['url' => $this->appClass()::logoUrl(), 'custom' => true];
        }

        return ['url' => Branding::logoUrl('on_color'), 'custom' => false];
    }

    protected function rules(): array
    {
        return [
            'theme' => ['required', 'in:'.implode(',', array_keys(Theme::presets()))],
            'name' => ['nullable', 'string', 'max:40'],
            'logoUpload' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }

    protected function messages(): array
    {
        return [
            'logoUpload.mimes' => 'Logo-ul trebuie să fie PNG sau JPG.',
            'logoUpload.max' => 'Logo-ul nu poate depăși 2 MB.',
            'logoUpload.file' => 'Alege un fișier imagine (PNG sau JPG).',
            'name.max' => 'Numele poate avea cel mult 40 de caractere.',
        ];
    }

    public function save(): void
    {
        $this->validateOrScroll();

        $current = trim((string) Settings::get($this->appClass()::KEY_LOGO));

        if ($this->logoUpload) {
            $new = $this->logoUpload->store('branding', 'public');
            if ($current !== '') {
                Storage::disk('public')->delete($current);
            }
            Settings::set($this->appClass()::KEY_LOGO, $new);
        } elseif ($this->logoRemoved) {
            if ($current !== '') {
                Storage::disk('public')->delete($current);
            }
            Settings::set($this->appClass()::KEY_LOGO, '');
        }
        $this->logoUpload = null;
        $this->logoRemoved = false;

        Settings::set($this->appClass()::KEY_THEME, $this->theme);
        Settings::set($this->appClass()::KEY_NAME, trim($this->name));

        ActivityLogger::log($this->logEvent(), 'A modificat setările '.$this->appPhrase().' (temă: '.Theme::preset($this->theme)['label'].').');

        session()->flash('status', 'Setările '.$this->appPhrase().' au fost salvate. Iconița de pe telefoane se actualizează la următoarea instalare.');
    }

    public function render()
    {
        return view('livewire.admin.pwa-app-settings', [
            'pwa' => $this->appClass(),
            'heading' => $this->heading(),
            'appPhrase' => $this->appPhrase(),
            'presets' => Theme::presets(),
            'logo' => $this->logoPreview(),
            'preview' => Theme::preset($this->theme),
        ]);
    }
}
