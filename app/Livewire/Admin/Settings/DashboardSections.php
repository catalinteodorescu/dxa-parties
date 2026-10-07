<?php

namespace App\Livewire\Admin\Settings;

use App\Services\ActivityLogger;
use App\Support\DashboardSections as Sections;
use Livewire\Component;

/**
 * DXA: adaugat (runda 45). Panou în Setări: secțiunile din dashboard, cu activ/inactiv și reordonare (drag & drop pe desktop,
 * săgeți pe mobil). Se salvează pe loc (ca panoul „Metode de plată”), independent de butonul „Salvează setările”.
 */
class DashboardSections extends Component
{
    public function toggle(string $key): void
    {
        $rows = Sections::all();
        foreach ($rows as &$r) {
            if ($r['key'] === $key) {
                $r['enabled'] = ! $r['enabled'];
                Sections::save($rows);
                ActivityLogger::log('settings.dashboard_sections_changed', ($r['enabled'] ? 'A activat' : 'A dezactivat').' secțiunea „'.$r['label'].'” din dashboard.');

                return;
            }
        }
    }

    public function moveUp(string $key): void
    {
        $this->move($key, -1);
    }

    public function moveDown(string $key): void
    {
        $this->move($key, 1);
    }

    private function move(string $key, int $delta): void
    {
        $keys = array_column(Sections::all(), 'key');
        $i = array_search($key, $keys, true);
        if ($i !== false) {
            $this->reorder($key, max(0, min($i + $delta, count($keys) - 1)));
        }
    }

    /** Drag & drop (wire:sort): cheia mutată și poziția (de la 0) pe care a ajuns. */
    public function reorder(string $key, int $position): void
    {
        $rows = Sections::all();
        $before = array_column($rows, 'key');
        if (! in_array($key, $before, true)) {
            return;
        }

        $byKey = array_column($rows, null, 'key');
        $keys = array_values(array_filter($before, fn ($k) => $k !== $key));
        array_splice($keys, max(0, min($position, count($keys))), 0, [$key]);
        if ($keys === $before) {
            return;
        }

        Sections::save(array_map(fn ($k) => $byKey[$k], $keys));
        ActivityLogger::log('settings.dashboard_sections_changed', 'A reordonat secțiunile din dashboard.');
    }

    public function render()
    {
        return view('livewire.admin.settings.dashboard-sections', ['sections' => Sections::all()]);
    }
}
