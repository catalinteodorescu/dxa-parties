<?php

use App\Livewire\Admin\Parties\Form;
use App\Models\Admin;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 57). Petrecere gratuită debifează vânzarea online și ștampilele; ora de intrare nu poate fi înaintea celei de cumpărare; mesajul flash se aduce în vizor. */
function r57Admin(): Admin
{
    return Admin::create(['name' => 'Admin', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => true, 'access_reception' => false, 'password' => 'secret-pass']);
}

it('petrecere gratuită: vânzarea online și ștampilele se debifează, dar se pot bifa la loc', function () {
    $this->actingAs(r57Admin(), 'admin');

    Livewire::test(Form::class)
        ->set('online_sales', true)->set('loyalty_eligible', true)
        ->set('is_free', true)->assertSet('online_sales', false)->assertSet('loyalty_eligible', false)
        ->set('online_sales', true)->assertSet('online_sales', true);
});

it('ora până la care se poate intra nu poate fi înaintea celei până la care se poate cumpăra', function () {
    $this->actingAs(r57Admin(), 'admin');

    Livewire::test(Form::class)
        ->set('name', 'Seară test')->set('start_date', '2026-12-01')->set('start_time', '21:00')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '50', 'discounts' => [['label' => 'Early', 'price' => '30', 'until' => '2026-12-01T22:00', 'enter_until' => '2026-12-01T21:00']], 'qty_tiers' => [], 'combos' => []]])
        ->call('save')
        ->assertHasErrors(['ticket_types.0.discounts.0.enter_until']);
});

it('mesajul flash de succes se aduce în vizor (scrollIntoView)', function () {
    session()->flash('status', 'Salvat.');
    $html = Blade::render('<x-flash />');
    expect($html)->toContain('scrollIntoView')->toContain('Salvat.');
});
