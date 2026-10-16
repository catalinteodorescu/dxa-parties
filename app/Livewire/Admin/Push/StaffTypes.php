<?php

namespace App\Livewire\Admin\Push;

use App\Services\ActivityLogger;
use App\Services\Push\StaffNotifier;
use Livewire\Component;

/**
 * DXA: adaugat (runda 77). Secțiunea „Notificări — ce se trimite” din Setări › Aplicație recepție / bar: un întrerupător pe tip de notificare,
 * cu descriere și exemplu. Ce se bifează aici primesc toate telefoanele din aplicația respectivă; cine lucrează nu alege. Cere acțiunea
 * manage_staff_notifications (metoda toggleStaffType). `page` = reception | bar.
 */
class StaffTypes extends Component
{
    public string $page = 'reception';

    public function mount(string $page = 'reception'): void
    {
        $this->page = $page === 'bar' ? 'bar' : 'reception';
    }

    public function toggleStaffType(string $kind): void
    {
        if (! isset(StaffNotifier::typesFor($this->page)[$kind])) {
            return;
        }
        $on = ! StaffNotifier::typeEnabled($kind);
        StaffNotifier::setType($kind, $on);
        ActivityLogger::log('push.type_toggled', ($on ? 'A pornit' : 'A oprit').' notificările „'.StaffNotifier::TYPES[$kind][1].'” ('.($this->page === 'bar' ? 'Aplicație bar' : 'Aplicație recepție').').');
    }

    public function render()
    {
        $types = [];
        foreach (StaffNotifier::typesFor($this->page) as $k => $t) {
            $types[$k] = ['label' => $t[1], 'hint' => $t[2], 'example' => $t[3], 'on' => StaffNotifier::typeEnabled($k)];
        }

        return view('livewire.admin.push.staff-types', ['enabled' => StaffNotifier::enabled(), 'types' => $types] + StaffNotifier::phonesSummary($this->page));
    }
}
