<?php

use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Reception\SwitchWorker;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 71). Zoom blocat, Wake Lock, plasa loaderului și schimbarea lucrătorului cu PIN. */
function r71User(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Lucrător '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(),
        'is_active' => true, 'access_admin' => false, 'access_reception' => true,
        'password' => 'secret-pass',
    ], $attrs));
}

beforeEach(fn () => RateLimiter::clear('x'));

it('pinul se păstrează hash-uit și nu apare în serializare', function () {
    $a = r71User(['pin' => '1234']);

    expect($a->getRawOriginal('pin'))->not->toBe('1234')
        ->and(Hash::check('1234', $a->fresh()->pin))->toBeTrue()
        ->and($a->toArray())->not->toHaveKey('pin');
});

it('adminul cu drept de modificare setează și șterge PIN-ul, doar cu 4 cifre', function () {
    $super = r71User(['role' => 'superadmin']);
    $worker = r71User();

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)
        ->call('setPin', $worker->id, '12a4')
        ->call('setPin', $worker->id, '123');
    expect($worker->fresh()->pin)->toBeNull();

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('setPin', $worker->id, '4321');
    expect(Hash::check('4321', $worker->fresh()->pin))->toBeTrue();
    expect(AdminActivityLog::where('action', 'admin.pin.set')->count())->toBe(1);

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('clearPin', $worker->id);
    expect($worker->fresh()->pin)->toBeNull();
});

it('un non-superadmin nu poate seta PIN-ul unui superadmin și fără drept de modificare primește 403', function () {
    $super = r71User(['role' => 'superadmin']);
    $levels = Permissions::none();
    $editor = r71User(['permissions' => Permissions::legacyAdmin()]);

    Livewire::actingAs($editor, 'admin')->test(UsersIndex::class)->call('setPin', $super->id, '1111')->assertForbidden();
    expect($super->fresh()->pin)->toBeNull();

    $none = r71User(['permissions' => $levels]);
    Livewire::actingAs($none, 'admin')->test(UsersIndex::class)->call('setPin', $editor->id, '1111')->assertForbidden();
});

it('lista de schimbare conține doar conturi active, cu acces la aplicație și cu PIN, fără contul curent', function () {
    $me = r71User(['pin' => '1111', 'name' => 'Eu']);
    r71User(['pin' => '2222', 'name' => 'Ana']);
    r71User(['name' => 'FaraPin']);
    r71User(['pin' => '3333', 'name' => 'Inactiv', 'is_active' => false]);
    r71User(['pin' => '4444', 'name' => 'DoarBar', 'access_reception' => false, 'access_bar' => true]);

    Livewire::actingAs($me, 'admin')->test(SwitchWorker::class, ['prefix' => 'receptie'])
        ->assertSee('Ana')->assertDontSee('FaraPin')->assertDontSee('Inactiv')->assertDontSee('DoarBar');

    Livewire::actingAs($me, 'admin')->test(SwitchWorker::class, ['prefix' => 'bar'])
        ->assertSee('DoarBar')->assertDontSee('Ana');
});

it('PIN-ul corect face din noul cont lucrătorul activ al aplicației, fără a schimba login-ul, și se înregistrează în jurnal', function () {
    $me = r71User(['pin' => '1111']);
    $other = r71User(['pin' => '2222', 'name' => 'Ana']);

    Livewire::actingAs($me, 'admin')->test(SwitchWorker::class, ['prefix' => 'receptie'])
        ->call('choose', $other->id)->set('pin', '2222')->call('switch')->assertSet('error', null);

    expect(session('app_worker.reception'))->toBe($other->id)
        ->and(AdminActivityLog::where('action', 'admin.switch')->where('actor_id', $other->id)->count())->toBe(1);
});

it('PIN-ul greșit nu schimbă contul, e jurnalizat, iar după 5 greșeli se blochează', function () {
    $me = r71User(['pin' => '1111']);
    $other = r71User(['pin' => '2222']);
    RateLimiter::clear('worker-switch:target:'.$other->id);

    $c = Livewire::actingAs($me, 'admin')->test(SwitchWorker::class, ['prefix' => 'receptie'])->call('choose', $other->id);
    for ($i = 0; $i < 5; $i++) {
        $c->set('pin', '0000')->call('switch')->assertSet('error', 'PIN incorect.');
    }
    $c->set('pin', '2222')->call('switch');

    expect($c->get('error'))->toContain('Prea multe încercări')
        ->and(Auth::guard('admin')->id())->toBe($me->id)
        ->and(AdminActivityLog::where('action', 'admin.switch.failed')->count())->toBe(5);

    RateLimiter::clear('worker-switch:target:'.$other->id);
    RateLimiter::clear('worker-switch:ip:127.0.0.1');
});

it('nu se poate alege un cont care nu e în listă (fără PIN sau fără acces)', function () {
    $me = r71User(['pin' => '1111']);
    $noPin = r71User();

    Livewire::actingAs($me, 'admin')->test(SwitchWorker::class, ['prefix' => 'receptie'])
        ->call('choose', $noPin->id)->assertSet('workerId', null);
});

it('layout-urile Recepție și Bar blochează zoom-ul, cer Wake Lock și au plasă pentru loader', function () {
    $me = r71User(['pin' => '1111', 'access_bar' => true]);
    r71User(['pin' => '2222', 'access_bar' => true, 'name' => 'Ana']);

    foreach (['/receptie/petrecere', '/bar/petrecere'] as $url) {
        $html = $this->actingAs($me, 'admin')->get($url)->assertOk()->getContent();
        expect($html)->toContain('user-scalable=no')
            ->and($html)->toContain('touch-action: manipulation')
            ->and($html)->toContain('wakeLock')
            ->and($html)->toContain('gesturestart')
            ->and($html)->toContain('safety = setTimeout(() => show = false, 6000)')
            ->and($html)->toContain('data-switch-worker');
    }

    auth('admin')->logout();
    $this->get('/receptie/login')->assertOk()->assertSee('user-scalable=no', false)->assertSee('wakeLock', false);
    $this->get('/bar/login')->assertOk()->assertSee('user-scalable=no', false);
});

it('butonul de schimbare nu apare când nu există alt cont cu PIN', function () {
    $me = r71User();

    $this->actingAs($me, 'admin')->get('/receptie/petrecere')->assertOk()->assertDontSee('data-switch-worker', false);
});
