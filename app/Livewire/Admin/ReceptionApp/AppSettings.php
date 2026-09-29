<?php

namespace App\Livewire\Admin\ReceptionApp;

use App\Services\ActivityLogger;
use App\Support\Branding;
use App\Support\ReceptionApp;
use App\Support\Settings\Settings;
use App\Support\Theme;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * DXA: adaugat (PWA Recepție - setări aplicație). Pagina din admin unde se aleg: tema de culoare, numele și logo-ul
 * aplicației de recepție. Tema colorează aplicația ȘI iconița de pe ecranul telefonului (gradient în culorile ei).
 * Valorile se citesc prin App\Support\ReceptionApp.
 */
#[Layout('layouts.admin')]
class AppSettings extends Component
{
    use WithFileUploads;

    public string $theme = '';

    public string $name = '';

    /** Logo ales acum, încă nesalvat. */
    public $logoUpload = null;

    /** A cerut revenirea la logo-ul școlii. */
    public bool $logoRemoved = false;

    public function mount(): void
    {
        $this->theme = ReceptionApp::themeKey();
        $this->name = (string) Settings::get(ReceptionApp::KEY_NAME);
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

        if (! $this->logoRemoved && ReceptionApp::uploadedLogoPath()) {
            return ['url' => ReceptionApp::logoUrl(), 'custom' => true];
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
        $this->validate();

        $current = trim((string) Settings::get(ReceptionApp::KEY_LOGO));

        if ($this->logoUpload) {
            $new = $this->logoUpload->store('branding', 'public');
            if ($current !== '') {
                Storage::disk('public')->delete($current);
            }
            Settings::set(ReceptionApp::KEY_LOGO, $new);
        } elseif ($this->logoRemoved) {
            if ($current !== '') {
                Storage::disk('public')->delete($current);
            }
            Settings::set(ReceptionApp::KEY_LOGO, '');
        }
        $this->logoUpload = null;
        $this->logoRemoved = false;

        Settings::set(ReceptionApp::KEY_THEME, $this->theme);
        Settings::set(ReceptionApp::KEY_NAME, trim($this->name));

        ActivityLogger::log('settings.reception_app_updated', 'A modificat setările aplicației de recepție (temă: '.Theme::preset($this->theme)['label'].').');

        session()->flash('status', 'Setările aplicației de recepție au fost salvate. Iconița de pe telefoane se actualizează la următoarea instalare.');
    }

    public function render()
    {
        return view('livewire.admin.reception-app.settings', [
            'presets' => Theme::presets(),
            'logo' => $this->logoPreview(),
            'preview' => Theme::preset($this->theme),
        ]);
    }
}
