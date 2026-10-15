<?php

use App\Http\Middleware\ApplyAppWorker;
use App\Livewire\Reception\SwitchWorker;
use App\Models\Admin;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

uses(RefreshDatabase::class);

/** DXA: teste (runda 73). Lucrătorul activ se ține pe aplicație: schimbarea din Recepție nu atinge Bar, panoul admin sau login-ul. */
function r73User(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Cont '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(),
        'is_active' => true, 'access_admin' => true, 'access_bar' => true, 'access_reception' => true,
        'password' => 'secret-pass', 'pin' => '1111',
    ], $attrs));
}

/** Numele din antetul aplicației (lucrătorul activ). */
function r73Active(string $html): ?string
{
    return preg_match('/data-active-worker[^>]*>\s*([^<]+?)\s*</u', $html, $m) ? $m[1] : null;
}

it('lucrătorul ales în Recepție se vede doar în Recepție, nu în Bar sau în panou', function () {
    $login = r73User(['name' => 'Contul Login']);
    $worker = r73User(['name' => 'Lucrătorul Nou', 'pin' => '2222']);

    $this->actingAs($login, 'admin')->withSession(['app_worker.reception' => $worker->id]);
    expect(r73Active($this->get('/receptie/petrecere')->assertOk()->getContent()))->toBe('Lucrătorul Nou');

    // fiecare cerere reală pornește cu garda curată; în test o resetăm între cereri
    Auth::forgetGuards();
    expect(r73Active($this->actingAs($login, 'admin')->get('/bar/petrecere')->assertOk()->getContent()))->toBe('Contul Login');

    Auth::forgetGuards();
    $this->actingAs($login, 'admin')->get('/admin')->assertOk()->assertSee('Contul Login')->assertDontSee('Lucrătorul Nou');
});

it('lucrătorul se schimbă prin PIN doar pe aplicația curentă', function () {
    $login = r73User(['name' => 'Contul Login']);
    $worker = r73User(['name' => 'Lucrătorul Nou', 'pin' => '2222']);

    Livewire::actingAs($login, 'admin')->test(SwitchWorker::class, ['prefix' => 'bar'])
        ->call('choose', $worker->id)->set('pin', '2222')->call('switch');

    expect(session('app_worker.bar'))->toBe($worker->id)->and(session('app_worker.reception'))->toBeNull();
});

it('un lucrător fără PIN, dezactivat sau fără acces nu mai e aplicat și marcajul se șterge', function () {
    $login = r73User();

    foreach ([['pin' => null], ['is_active' => false], ['access_reception' => false]] as $change) {
        $w = r73User();
        $this->actingAs($login, 'admin')->withSession(['app_worker.reception' => $w->id]);
        $w->forceFill($change)->save();

        expect(r73Active($this->get('/receptie/petrecere')->assertOk()->getContent()))->toBe($login->name)
            ->and(session('app_worker.reception'))->toBeNull();
        Auth::forgetGuards();
    }
});

it('middleware-ul e persistent în Livewire, ca acțiunile să ajungă la lucrătorul activ', function () {
    $prop = new ReflectionProperty(PersistentMiddleware::class, 'persistentMiddleware');
    $prop->setAccessible(true);

    expect($prop->getValue())->toContain(ApplyAppWorker::class);
});

it('aplicațiile Recepție și Bar au middleware-ul, iar panoul admin nu', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes());
    $has = fn (string $name) => collect($routes->first(fn ($r) => $r->getName() === $name)->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'admin.worker'));

    expect($has('receptie.home'))->toBeTrue()->and($has('bar.home'))->toBeTrue()->and($has('admin.dashboard'))->toBeFalse();
});
