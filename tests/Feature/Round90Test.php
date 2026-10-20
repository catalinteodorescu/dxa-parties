<?php

use App\Livewire\Bar\Sale;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Support\BarApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function r90View(string $path): string
{
    return file_get_contents(resource_path("views/{$path}.blade.php"));
}

it('în aplicația participanților loaderul nu mai pune un voal pe tot ecranul, iar „elasticul” iOS e oprit', function () {
    $css = r90View('layouts/partials-participant-styles');

    $loaderLine = collect(explode("\n", $css))->first(fn ($l) => str_contains($l, '.app-loader {'));

    expect($loaderLine)->not->toContain('backdrop-filter')
        ->and($css)->toContain('overscroll-behavior-y: none')
        ->toContain('.app-loader { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; background: transparent');
});

it('lista „Alege un produs” din Bar e evidentă, cu rânduri înalte, ca la participanți', function () {
    $html = Blade::render('<x-select wire:model="pick" :options="[1 => \'Cola\', 2 => \'Bere\']" :large="true" :searchable="true" />', ['pick' => '']);

    expect($html)->toContain('border-primary/40')->toContain('min-h-14')->toContain('h-12 text-base')->toContain('h-11 text-base');
    expect(r90View('livewire/bar/sale'))->toContain('placeholder="Alege un produs…" :searchable="true" :large="true"');
});

it('în Bar cantitatea pe linia de bon se schimbă local și ajunge o singură dată la server', function () {
    $v = r90View('livewire/bar/sale');

    expect($v)->toContain('$wire.setQty({{ $id }}, this.q)')->toContain('x-on:click="set(q + 1)"')->not->toContain('wire:click="stepQty');

    $party = Party::create([
        'name' => 'Seara bar', 'kind' => 'basic', 'start_date' => now()->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'is_active' => true, 'status' => 'published',
    ]);
    $cat = MenuCategory::firstOrCreate(['name' => 'Băuturi'], ['is_active' => true]);
    $item = MenuItem::create(['menu_category_id' => $cat->id, 'name' => 'Cola R90', 'price' => 12, 'is_active' => true]);
    $admin = Admin::create(['name' => 'Barman R90', 'phone' => '0722900001', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']);
    $this->actingAs($admin, 'admin');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);

    $c = Livewire::test(Sale::class)
        ->call('addItem', $item->id)
        ->call('setQty', $item->id, 5)->assertSet('cart', [$item->id => 5])
        ->call('setQty', $item->id, 0)->assertSet('cart', [$item->id => 1])
        ->call('setQty', $item->id, 5000);

    expect($c->get('cart')[$item->id])->toBe(99);
    $c->call('setQty', 999999, 3)->assertSet('cart', [$item->id => 99]);   // produs care nu e în bon: ignorat
});
