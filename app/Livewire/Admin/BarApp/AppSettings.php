<?php

namespace App\Livewire\Admin\BarApp;

use App\Livewire\Admin\PwaAppSettings;
use App\Support\BarApp;
use Livewire\Attributes\Layout;

/**
 * DXA: adaugat (PWA Bar - setări aplicație). Setări › Aplicație bar: nume, logo și temă, ca la aplicația de recepție.
 * Logica e în App\Livewire\Admin\PwaAppSettings; valorile se citesc prin BarApp.
 */
#[Layout('layouts.admin')]
class AppSettings extends PwaAppSettings
{
    protected function appClass(): string
    {
        return BarApp::class;
    }

    protected function heading(): string
    {
        return 'Bar · Aplicație';
    }

    protected function appPhrase(): string
    {
        return 'aplicației de bar';
    }

    protected function logEvent(): string
    {
        return 'settings.bar_app_updated';
    }
}
