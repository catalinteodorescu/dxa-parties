<?php

use App\Http\Middleware\ApplyAuthZone;
use App\Livewire\Bar\Login as BarLogin;
use App\Livewire\Reception\Login as ReceptionLogin;
use App\Models\Admin;
use App\Support\Permissions;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

uses(RefreshDatabase::class);

function r74Admin(array $o = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true,
        'access_admin' => true, 'access_bar' => true, 'access_reception' => true, 'password' => 'secret123',
    ], $o));
}

function r74Keys(): array
{
    return array_keys(session()->all());
}

it('login in Recepție nu autentifică Admin și Bar', function () {
    $a = r74Admin();

    Auth::guard('admin')->setZone('reception'); // în aplicație o face middleware-ul persistent
    Livewire::test(ReceptionLogin::class)->set('phone', $a->phone)->set('password', 'secret123')->call('login');

    expect(collect(r74Keys())->filter(fn ($k) => str_starts_with($k, 'login_reception_')))->toHaveCount(1)
        ->and(collect(r74Keys())->filter(fn ($k) => str_starts_with($k, 'login_admin_') || str_starts_with($k, 'login_bar_')))->toHaveCount(0);
});

it('login în Bar nu autentifică Recepție', function () {
    $a = r74Admin();

    Auth::guard('admin')->setZone('bar');
    Livewire::test(BarLogin::class)->set('phone', $a->phone)->set('password', 'secret123')->call('login');

    expect(collect(r74Keys())->filter(fn ($k) => str_starts_with($k, 'login_bar_')))->toHaveCount(1)
        ->and(collect(r74Keys())->filter(fn ($k) => str_starts_with($k, 'login_reception_')))->toHaveCount(0);
});

it('cu sesiune doar în admin, Recepția cere login, iar panoul merge', function () {
    $a = r74Admin();
    $this->withSession([Auth::guard('admin')->getName() => $a->id]);

    $this->get('/admin')->assertOk();
    Auth::forgetGuards();
    $this->get('/receptie/petrecere')->assertRedirect(route('receptie.login'));
    Auth::forgetGuards();
    $this->get('/bar/petrecere')->assertRedirect(route('bar.login'));
});

it('deconectarea dintr-o zonă le lasă pe celelalte', function () {
    $a = r74Admin();
    $adminKey = 'login_admin_'.sha1(SessionGuard::class);
    $barKey = 'login_bar_'.sha1(SessionGuard::class);
    $recKey = 'login_reception_'.sha1(SessionGuard::class);

    $this->withSession([$adminKey => $a->id, $barKey => $a->id, $recKey => $a->id]);
    $this->post('/sesiune/logout', ['to' => 'receptie'])->assertRedirect(route('receptie.login'));

    expect(session()->has($recKey))->toBeFalse()
        ->and(session()->has($adminKey))->toBeTrue()
        ->and(session()->has($barKey))->toBeTrue();
});

it('ieșirea din panou nu închide Bar și Recepție', function () {
    $a = r74Admin();
    $sha = sha1(SessionGuard::class);
    $this->withSession(['login_admin_'.$sha => $a->id, 'login_bar_'.$sha => $a->id]);
    $this->post('/admin/logout')->assertRedirect(route('admin.login'));

    expect(session()->has('login_admin_'.$sha))->toBeFalse()->and(session()->has('login_bar_'.$sha))->toBeTrue();
});

it('Recepție/Bar țin conectarea 30 de zile, panoul păstrează durata veche', function () {
    $g = Auth::guard('admin');
    $g->setZone('bar');
    $r = new ReflectionProperty($g, 'rememberDuration');
    expect($r->getValue($g))->toBe(43200);
    $g->setZone('admin');
    expect($r->getValue($g))->toBe(576000)->and($g->getRecallerName())->toStartWith('remember_admin_');
});

it('zona e middleware persistent în Livewire și rulează înaintea autentificării', function () {
    $prop = new ReflectionProperty(PersistentMiddleware::class, 'persistentMiddleware');
    $prop->setAccessible(true);
    expect($prop->getValue())->toContain(ApplyAuthZone::class);

    $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->getName() === 'receptie.login');
    $sorted = app('router')->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
    $zone = collect($sorted)->search(fn ($m) => str_starts_with((string) $m, ApplyAuthZone::class));
    $guest = collect($sorted)->search(fn ($m) => str_contains((string) $m, 'RedirectIfAuthenticated'));

    expect($zone)->not->toBeFalse()->and($zone)->toBeLessThan($guest);
});
