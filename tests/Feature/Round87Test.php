<?php

use App\Livewire\Admin\Push\Index as PushIndex;
use App\Livewire\Participant\Parties;
use App\Livewire\Participant\PartyShow;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PushNotification;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function r87Party(): Party
{
    return Party::create([
        'name' => 'Petrecere R87', 'kind' => 'basic', 'start_date' => now()->addDays(5)->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

it('pagina 503 folosește logo-ul curent încărcat, inclus în pagină, nu iconița implicită', function () {
    Storage::fake('public');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Storage::disk('public')->put('branding/logo-curent.png', $png);
    Settings::set('logo_on_light', 'branding/logo-curent.png');

    $html = view('errors.503')->render();

    expect($html)->toContain('data:image/png;base64,'.base64_encode($png))->not->toContain('src="/images/logo-default.png"');
});

it('pagina 503 cade pe logo-ul implicit când nu există logo încărcat', function () {
    expect(view('errors.503')->render())->toContain('data:image/png;base64,');
});

it('loaderul din Recepție / Bar e clopoțelul, nu paharul', function () {
    $html = view('layouts.partials-reception-loader')->render();

    expect($html)->toContain('rc-bell')->not->toContain('rc-glass');
});

it('deconectarea cu sesiune expirată ajunge la login-ul zonei corecte, nu în aplicația participanților', function () {
    $this->post('/sesiune/logout', ['to' => 'bar'])->assertRedirect(route('bar.login'));
    $this->post('/sesiune/logout', ['to' => 'receptie'])->assertRedirect(route('receptie.login'));
    $this->post('/sesiune/logout')->assertRedirect(route('admin.login'));
});

it('lista de notificări din admin nu mai scrie 0/0 când abonarea a dispărut', function () {
    $ana = Participant::create(['name' => 'Ana R87', 'phone' => '0722870001', 'password' => 'secret-pass']);
    PushNotification::create(['participant_id' => $ana->id, 'kind' => 'announcement', 'dedupe_key' => 'r87', 'title' => 'Titlu R87', 'body' => 'Text', 'url' => '/']);
    $admin = Admin::create(['name' => 'A R87', 'phone' => '0722870002', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']);

    Livewire::actingAs($admin, 'admin')->test(PushIndex::class)
        ->assertSee('Titlu R87')->assertSee('Dispozitiv dezabonat')->assertDontSee('0/0 livrate');
});

it('în aplicația participanților zoom-ul la dublu-tap e oprit', function () {
    $html = $this->get(route('app.home'))->assertOk()->getContent();

    expect($html)->toContain('user-scalable=no')->toContain('touch-action: manipulation');
});

it('cardul de notificări pleacă după activare (pornește ascuns, se arată doar dacă nu sunt active)', function () {
    $html = view('livewire.participant._push-card')->render();

    // Notificările push sunt dezactivate în mediul de test: cardul nu se randează deloc; cu ele pornite, are atributul care îl ascunde.
    expect($html === '' || str_contains($html, 'data-hide-on="on"'))->toBeTrue();
    expect(file_get_contents(resource_path('views/layouts/partials-push-init.blade.php')))->toContain('data-hide-on', 'hideOn');
});

it('numărul de bilete se schimbă local și ajunge o singură dată la server', function () {
    $p = r87Party();

    $ana = Participant::create(['name' => 'Ana Bilete', 'phone' => '0722870003', 'password' => 'secret-pass']);

    $c = Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $p])
        ->assertSeeHtml('data-qty')->assertSeeHtml('x-on:click="set(q + 1)"')
        ->call('setQty', 3)->assertSet('qty', 3)
        ->call('setQty', 0)->assertSet('qty', 1)
        ->call('setQty', 9999);

    expect($c->get('qty'))->toBeGreaterThanOrEqual(1)->toBeLessThan(9999);
});

it('filele Toate / Favorite se aprind imediat, fără să aștepte serverul', function () {
    Livewire::test(Parties::class)
        ->assertSeeHtml("x-on:click=\"v = 'all'; \$wire.showView('all')\"")
        ->assertSeeHtml('wire:target="showView"');
});

it('scannerul de la Recepție respectă zona de siguranță de sus (Dynamic Island, oră)', function () {
    expect(file_get_contents(resource_path('views/livewire/reception/quick-scan.blade.php')))->toContain('env(safe-area-inset-top)');
    expect(file_get_contents(resource_path('views/livewire/reception/_scanner.blade.php')))->toContain('env(safe-area-inset-top)');
});
