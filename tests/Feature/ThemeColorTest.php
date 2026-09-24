<?php

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Admin;
use App\Support\Settings\Settings;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function themeAdmin(): Admin
{
    return Admin::create([
        'name' => 'Theme Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

it('foloseste terracotta implicit si nu emite niciun stil suplimentar', function () {
    expect(Theme::key())->toBe('terracotta');

    $this->actingAs(themeAdmin(), 'admin');

    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertDontSee('id="dxa-theme"', false);
});

it('salveaza culoarea aleasa si o aplica in layout', function () {
    $this->actingAs(themeAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('values.theme_color', 'indigo')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('theme-changed');

    expect(Settings::get('theme_color'))->toBe('indigo');

    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('id="dxa-theme"', false)
        ->assertSee('--color-primary:#4F46E5', false);
});

it('respinge o culoare care nu e in lista prestabilita', function () {
    $this->actingAs(themeAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('values.theme_color', 'chartreuse')
        ->call('save')
        ->assertHasErrors('values.theme_color');

    expect(Settings::get('theme_color'))->toBe('terracotta');
});

it('revine la implicita daca in DB e o cheie necunoscuta', function () {
    Settings::set('theme_color', 'nu-exista');

    expect(Theme::key())->toBe('terracotta')
        ->and(Theme::primary())->toBe('#DD6441');
});

it('fiecare paleta are cele 5 trepte, in format hex', function () {
    foreach (Theme::presets() as $key => $preset) {
        foreach (['primary', 'hover', 'soft', 'dark', 'bright'] as $step) {
            expect($preset[$step])->toMatch('/^#[0-9A-F]{6}$/', "$key.$step");
        }
        expect(Theme::cssVars($key))->toHaveCount(5);
    }
});
