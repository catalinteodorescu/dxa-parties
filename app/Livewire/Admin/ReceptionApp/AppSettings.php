<?php

namespace App\Livewire\Admin\ReceptionApp;

use App\Livewire\Admin\PwaAppSettings;
use App\Support\ReceptionApp;
use Livewire\Attributes\Layout;

/**
 * DXA: adaugat (PWA Recepție - setări aplicație). Setări › Aplicație recepție: nume, logo și temă.
 * Logica e în App\Livewire\Admin\PwaAppSettings (comună cu aplicația de bar); valorile se citesc prin ReceptionApp.
 */
#[Layout('layouts.admin')]
class AppSettings extends PwaAppSettings
{
    protected function appClass(): string
    {
        return ReceptionApp::class;
    }

    protected function heading(): string
    {
        return 'Recepție · Aplicație';
    }

    protected function appPhrase(): string
    {
        return 'aplicației de recepție';
    }

    protected function logEvent(): string
    {
        return 'settings.reception_app_updated';
    }
}
