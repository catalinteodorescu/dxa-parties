<?php

namespace App\Livewire\Admin\Users\Concerns;

use App\Models\Admin;
use App\Support\Permissions;

/**
 * DXA: adaugat (runda 46 — permisiuni). Matricea de permisiuni din formularele Utilizatori (creare + editare):
 * `$levels` (secțiune => nivel 0..3), `$actions` (acțiune => bifat) și „Copiază de la alt utilizator”.
 */
trait EditsPermissions
{
    /** @var array<string,int> */
    public array $levels = [];

    /** @var array<string,bool> */
    public array $actions = [];

    public ?int $copyFrom = null;

    protected function fillPermissions(?array $stored): void
    {
        $p = Permissions::normalize($stored);

        $this->levels = [];
        foreach (Permissions::SECTIONS as $key => $_) {
            $this->levels[$key] = $p['levels'][$key] ?? Permissions::NONE;
        }

        $this->actions = [];
        foreach (Permissions::ACTIONS as $key => $_) {
            $this->actions[$key] = in_array($key, $p['actions'], true);
        }
    }

    /** Matricea din formular, curățată, gata de salvat. */
    protected function permissionsPayload(): array
    {
        return Permissions::normalize([
            'levels' => $this->levels,
            'actions' => array_keys(array_filter($this->actions)),
        ]);
    }

    public function setLevel(string $section, int $level): void
    {
        abort_unless(Permissions::isSection($section), 422);

        $this->levels[$section] = min(max($level, Permissions::NONE), Permissions::maxFor($section));
    }

    /** Pornește/oprește o acțiune sensibilă. */
    public function toggleAction(string $action): void
    {
        abort_unless(isset(Permissions::ACTIONS[$action]), 422);

        $this->actions[$action] = ! ($this->actions[$action] ?? false);
    }

    /** Preset rapid: „full”, „view” sau „none” pentru toată matricea. */
    public function setAll(string $mode): void
    {
        $this->fillPermissions(match ($mode) {
            'full' => Permissions::full(),
            'view' => Permissions::viewOnly(),
            default => Permissions::none(),
        });
    }

    /** Copiază permisiunile altui utilizator în formular (se pot ajusta înainte de salvare). */
    public function copyPermissions(): void
    {
        if (! $this->copyFrom) {
            return;
        }

        $source = Admin::where('role', '!=', 'superadmin')->find($this->copyFrom);

        if (! $source) {
            $this->addError('copyFrom', 'Alege un utilizator din listă.');

            return;
        }

        $this->resetErrorBag('copyFrom');
        $this->fillPermissions($source->permissions);
    }

    /** Utilizatorii de la care se pot copia permisiuni (superadmin-ii au tot, nu au ce copia). */
    protected function copySources(?int $exceptId = null)
    {
        return Admin::where('role', '!=', 'superadmin')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderByRaw('name is null')->orderBy('name')->orderBy('phone')
            ->get(['id', 'name', 'phone']);
    }
}
