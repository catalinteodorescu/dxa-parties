<?php

use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Admin;
use App\Support\Branding;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function brandingAdmin(): Admin
{
    return Admin::create([
        'name' => 'Branding Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

it('foloseste numele si logo-urile implicite cand nu e setat nimic', function () {
    expect(Branding::name())->toBe('Dance Xplosion Academy')
        ->and(Branding::address())->toBeNull()
        ->and(Branding::mapsUrl())->toBeNull()
        ->and(Branding::logoUrl('on_color'))->toEndWith('images/logo-xplosion-white.png')
        ->and(Branding::logoUrl('on_light'))->toEndWith('images/logo-xplosion.png');
});

it('salveaza numele, adresa si linkul de harta si numele apare in titlul paginilor', function () {
    $this->actingAs(brandingAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('values.school_name', 'Studio Ritm')
        ->set('values.school_address', 'Str. Dansului 5, Sibiu')
        ->set('values.school_maps_url', 'https://maps.app.goo.gl/abc123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings.index'));

    expect(Branding::name())->toBe('Studio Ritm')
        ->and(Branding::address())->toBe('Str. Dansului 5, Sibiu')
        ->and(Branding::mapsUrl())->toBe('https://maps.app.goo.gl/abc123');

    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('<title>Studio Ritm — Panou admin</title>', false);
});

it('respinge un nume gol si un link de harta invalid', function () {
    $this->actingAs(brandingAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('values.school_name', '')
        ->set('values.school_maps_url', 'nu-e-link')
        ->call('save')
        ->assertHasErrors(['values.school_name', 'values.school_maps_url']);

    expect(Branding::name())->toBe('Dance Xplosion Academy');
});

it('incarca un logo, il inlocuieste stergand fisierul vechi si revine la implicit', function () {
    Storage::fake('public');
    $this->actingAs(brandingAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('uploads.logo_on_light', UploadedFile::fake()->image('logo.png', 400, 120))
        ->call('save')
        ->assertHasNoErrors();

    $first = Settings::get('logo_on_light');
    expect($first)->toStartWith('branding/');
    Storage::disk('public')->assertExists($first);
    expect(Branding::logoUrl('on_light'))->toEndWith($first)
        ->and(Branding::logoDataUri('on_light'))->toStartWith('data:image/png;base64,');

    Livewire::test(SettingsIndex::class)
        ->set('uploads.logo_on_light', UploadedFile::fake()->image('nou.png', 400, 120))
        ->call('save')
        ->assertHasNoErrors();

    $second = Settings::get('logo_on_light');
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    Livewire::test(SettingsIndex::class)
        ->call('removeUpload', 'logo_on_light')
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('public')->assertMissing($second);
    expect(Branding::uploadedPath('on_light'))->toBeNull()
        ->and(Branding::logoUrl('on_light'))->toEndWith('images/logo-xplosion.png');
});

it('respinge un logo care nu e imagine PNG/JPG', function () {
    Storage::fake('public');
    $this->actingAs(brandingAdmin(), 'admin');

    Livewire::test(SettingsIndex::class)
        ->set('uploads.logo_on_color', UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('uploads.logo_on_color');

    expect(Branding::uploadedPath('on_color'))->toBeNull();
});

it('precompleteaza locatia la o petrecere noua cu datele scolii', function () {
    $this->actingAs(brandingAdmin(), 'admin');

    Settings::set('school_name', 'Studio Ritm');
    Settings::set('school_address', 'Str. Dansului 5, Sibiu');
    Settings::set('school_maps_url', 'https://maps.app.goo.gl/abc123');

    Livewire::test(PartyForm::class)
        ->assertSet('location_name', 'Studio Ritm')
        ->assertSet('location_address', 'Str. Dansului 5, Sibiu')
        ->assertSet('location_url', 'https://maps.app.goo.gl/abc123')
        ->set('location_name', 'Altă sală')
        ->call('fillSchoolVenue')
        ->assertSet('location_name', 'Studio Ritm');
});

it('afiseaza logo-ul in antetul PDF-urilor', function () {
    $html = view('pdf._brand')->render();

    expect($html)->toContain('data:image/png;base64,')->toContain('alt="Dance Xplosion Academy"');
});
