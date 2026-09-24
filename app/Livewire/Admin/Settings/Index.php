<?php

namespace App\Livewire\Admin\Settings;

use App\Services\ActivityLogger;
use App\Support\Branding;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;
use App\Support\Theme;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Index extends Component
{
    use WithFileUploads;

    /** @var array<string, mixed> cheie de camp => valoare curenta din formular */
    public array $values = [];

    /** @var array<string, mixed> cheie de camp 'image' => fisierul ales, inca nesalvat */
    public array $uploads = [];

    /** @var array<string, bool> cheie de camp 'image' => a cerut revenirea la logo-ul implicit */
    public array $removed = [];

    public function mount(): void
    {
        foreach (SettingsRegistry::allFields() as $key => $field) {
            $this->values[$key] = Settings::get($key);
        }
    }

    public function removeUpload(string $key): void
    {
        $field = SettingsRegistry::field($key);
        abort_unless($field && ($field['type'] ?? null) === 'image', 404);

        unset($this->uploads[$key]);
        $this->removed[$key] = true;
    }

    /**
     * Previzualizarea unui camp 'image': fisierul ales acum, altfel logo-ul salvat,
     * altfel cel implicit. `custom` = e un logo incarcat (se poate reveni la implicit).
     *
     * @return array{url: string, custom: bool}
     */
    public function imagePreview(array $field): array
    {
        $key = $field['key'];
        $variant = $field['variant'];

        if (! empty($this->uploads[$key])) {
            try {
                return ['url' => $this->uploads[$key]->temporaryUrl(), 'custom' => true];
            } catch (\Throwable) {
                // fisier non-imagine: cade pe logo-ul curent, iar validarea afiseaza eroarea
            }
        }

        if (empty($this->removed[$key])) {
            $path = trim((string) ($this->values[$key] ?? ''));
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                return ['url' => asset('storage/'.$path), 'custom' => true];
            }
        }

        return ['url' => Branding::defaultLogoUrl($variant), 'custom' => false];
    }

    public function sections(): array
    {
        return SettingsRegistry::sections();
    }

    /**
     * Un camp cu 'depends_on' e needitabil cat timp campul bool de care
     * depinde e dezactivat (nu-l ascundem, doar il dezactivam vizual).
     */
    public function fieldDisabled(array $field): bool
    {
        if (empty($field['depends_on'])) {
            return false;
        }

        return ! (bool) ($this->values[$field['depends_on']] ?? false);
    }

    protected function rules(): array
    {
        $rules = [];

        foreach (SettingsRegistry::allFields() as $key => $field) {
            if ($field['type'] === 'image') {
                $rules['uploads.'.$key] = ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'];

                continue;
            }

            if (! empty($field['rules'])) {
                $rules['values.'.$key] = $field['rules'];

                continue;
            }

            $rules['values.'.$key] = match ($field['type']) {
                'bool' => ['boolean'],
                'number' => array_values(array_filter([
                    'required', 'numeric',
                    isset($field['min']) ? 'min:'.$field['min'] : null,
                ])),
                'select', 'swatches' => ['required', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'uploads.*.mimes' => 'Logo-ul trebuie să fie PNG sau JPG.',
            'uploads.*.max' => 'Logo-ul nu poate depăși 2 MB.',
            'uploads.*.file' => 'Alege un fișier imagine (PNG sau JPG).',
            'values.school_name.required' => 'Numele școlii nu poate lipsi.',
            'values.school_maps_url.url' => 'Linkul hărții nu pare valid (începe cu https://).',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $previousTheme = Theme::key();
        $previousName = Branding::name();
        $logoChanged = false;

        // Logo-uri: fisierul nou se stocheaza pe disk-ul public (nume aleatoriu => fara probleme de cache),
        // cel vechi se sterge; "revino la implicit" goleste calea.
        foreach (SettingsRegistry::allFields() as $key => $field) {
            if ($field['type'] !== 'image') {
                continue;
            }

            $current = trim((string) ($this->values[$key] ?? ''));

            if (! empty($this->uploads[$key])) {
                $logoChanged = true;
                $new = $this->uploads[$key]->store('branding', 'public');
                if ($current !== '') {
                    Storage::disk('public')->delete($current);
                }
                $this->values[$key] = $new;
            } elseif (! empty($this->removed[$key])) {
                $logoChanged = $logoChanged || $current !== '';
                if ($current !== '') {
                    Storage::disk('public')->delete($current);
                }
                $this->values[$key] = '';
            }
        }
        $this->uploads = [];
        $this->removed = [];

        foreach (SettingsRegistry::allFields() as $key => $field) {
            Settings::set($key, $this->values[$key] ?? null);
        }

        ActivityLogger::log('settings.updated', 'A modificat setările aplicației.');

        // Culoarea temei: layout-ul e in afara componentei, deci nu se re-randeaza singur.
        // Trimitem variabilele CSS catre browser ca sa se aplice imediat, fara reincarcare.
        $theme = Theme::key();
        if ($theme !== $previousTheme) {
            ActivityLogger::log('settings.theme_changed', 'A schimbat culoarea temei: '.Theme::preset($theme)['label'].'.');
        }
        $this->dispatch('theme-changed', vars: Theme::cssVars($theme));

        // Numele si logo-urile apar in layout (in afara componentei): reincarcam pagina ca sa se vada imediat.
        if ($logoChanged || Branding::name() !== $previousName) {
            $this->redirectRoute('admin.settings.index', navigate: true);
        }

        session()->flash('status', 'Setările au fost salvate.');
    }

    public function render()
    {
        return view('livewire.admin.settings.index');
    }
}
