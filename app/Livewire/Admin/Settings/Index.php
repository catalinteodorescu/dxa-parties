<?php

namespace App\Livewire\Admin\Settings;

use App\Services\ActivityLogger;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Index extends Component
{
    /** @var array<string, mixed> cheie de camp => valoare curenta din formular */
    public array $values = [];

    public function mount(): void
    {
        foreach (SettingsRegistry::allFields() as $key => $field) {
            $this->values[$key] = Settings::get($key);
        }
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
            $rules['values.'.$key] = match ($field['type']) {
                'bool' => ['boolean'],
                'number' => array_values(array_filter([
                    'required', 'numeric',
                    isset($field['min']) ? 'min:'.$field['min'] : null,
                ])),
                'select' => ['required', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate();

        foreach (SettingsRegistry::allFields() as $key => $field) {
            Settings::set($key, $this->values[$key] ?? null);
        }

        ActivityLogger::log('settings.updated', 'A modificat setările aplicației.');

        session()->flash('status', 'Setările au fost salvate.');
    }

    public function render()
    {
        return view('livewire.admin.settings.index');
    }
}
