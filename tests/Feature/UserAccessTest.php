<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Invite\Complete as InviteComplete;
use App\Livewire\Admin\Login;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * DXA: teste (Utilizatori - acces pe aplicație). Bifele de acces (panou / bar / recepție) de pe `admins`,
 * verificarea la login și pe fiecare cerere (middleware admin.access), gestionarea lor doar de către superadmin.
 */
function uaUser(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Utilizator '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ], $attrs));
}

it('un cont nou are implicit acces doar la panou, iar superadmin-ul are acces la toate', function () {
    $admin = uaUser();
    expect($admin->canAccess(Admin::APP_ADMIN))->toBeTrue()
        ->and($admin->canAccess(Admin::APP_BAR))->toBeFalse()
        ->and($admin->canAccess(Admin::APP_RECEPTION))->toBeFalse()
        ->and($admin->fresh()->canAccess(Admin::APP_BAR))->toBeFalse();

    $super = uaUser(['role' => 'superadmin', 'access_admin' => false]);
    expect($super->canAccess(Admin::APP_ADMIN))->toBeTrue()
        ->and($super->canAccess(Admin::APP_BAR))->toBeTrue()
        ->and($super->canAccess(Admin::APP_RECEPTION))->toBeTrue();
});

it('un barman fara acces la panou nu se poate loga in panoul web, chiar cu parola corecta', function () {
    $barman = uaUser(['phone' => '0722000111', 'access_admin' => false, 'access_bar' => true]);

    Livewire::test(Login::class)
        ->set('phone', '0722000111')->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors(['phone'])
        ->assertSee('nu are acces la această aplicație');

    expect(Auth::guard('admin')->check())->toBeFalse()
        ->and(AdminActivityLog::pluck('action')->all())->toContain('admin.login.blocked_no_access');
});

it('scoaterea accesului blocheaza imediat utilizatorul (403), fara sa-l delogheze', function () {
    $user = uaUser();
    $this->actingAs($user, 'admin');
    $this->get(route('admin.dashboard'))->assertOk();

    $user->update(['access_admin' => false]);

    $this->get(route('admin.dashboard'))->assertForbidden()->assertSee('Nu ai acces la această aplicație');
    expect(Auth::guard('admin')->check())->toBeTrue()
        ->and(AdminActivityLog::pluck('action')->all())->toContain('admin.access.denied');
});

it('middleware-ul admin.access verifica acces separat pe bar si pe receptie', function () {
    Route::middleware(['web', 'auth:admin', 'admin.access:bar'])->get('/_test/bar', fn () => 'bar-ok');
    Route::middleware(['web', 'auth:admin', 'admin.access:reception'])->get('/_test/reception', fn () => 'reception-ok');

    $barman = uaUser(['access_admin' => false, 'access_bar' => true]);
    $this->actingAs($barman, 'admin');
    $this->get('/_test/bar')->assertOk()->assertSee('bar-ok');

    // Barmanul nu are recepție: e refuzat (403), dar rămâne logat (sesiunea rămâne valabilă la bar).
    $this->get('/_test/reception')->assertForbidden();
    expect(Auth::guard('admin')->check())->toBeTrue();
    $this->get('/_test/bar')->assertOk();

    $both = uaUser(['access_admin' => false, 'access_bar' => true, 'access_reception' => true]);
    $this->actingAs($both, 'admin');
    $this->get('/_test/reception')->assertOk()->assertSee('reception-ok');

    $super = uaUser(['role' => 'superadmin']);
    $this->actingAs($super, 'admin');
    $this->get('/_test/bar')->assertOk();
});

it('doar superadmin-ul poate da sau scoate accesul, cu jurnal', function () {
    $super = uaUser(['role' => 'superadmin']);
    $target = uaUser();

    $this->actingAs($super, 'admin');
    Livewire::test(UsersIndex::class)
        ->call('updateAccess', $target->id, Admin::APP_BAR, true)
        ->call('updateAccess', $target->id, Admin::APP_ADMIN, false);

    $fresh = $target->fresh();
    expect($fresh->access_bar)->toBeTrue()->and($fresh->access_admin)->toBeFalse()
        ->and(AdminActivityLog::pluck('action')->all())->toContain('admin.access.updated');

    // Aplicație necunoscută = 422.
    expect(fn () => Livewire::test(UsersIndex::class)->call('updateAccess', $target->id, 'nimic', true))
        ->not->toThrow(Exception::class);
    expect($target->fresh()->access_reception)->toBeFalse();

    // Un admin obișnuit nu poate.
    $plain = uaUser();
    $this->actingAs($plain, 'admin');
    Livewire::test(UsersIndex::class)->call('updateAccess', $target->id, Admin::APP_RECEPTION, true)->assertForbidden();
    expect($target->fresh()->access_reception)->toBeFalse();
});

it('accesul unui superadmin nu se editeaza', function () {
    $super = uaUser(['role' => 'superadmin']);
    $other = uaUser(['role' => 'superadmin']);
    $this->actingAs($super, 'admin');

    Livewire::test(UsersIndex::class)->call('updateAccess', $other->id, Admin::APP_BAR, false);

    expect($other->fresh()->canAccess(Admin::APP_BAR))->toBeTrue();
});

it('lista de utilizatori arata coloana Acces doar superadmin-ului', function () {
    $super = uaUser(['role' => 'superadmin', 'name' => 'Sef Suprem']);
    uaUser(['name' => 'Bar Man', 'access_admin' => false, 'access_bar' => true]);

    $this->actingAs($super, 'admin');
    Livewire::test(UsersIndex::class)->assertSee('Acces')->assertSee('Recepție')->assertSee('Toate');

    $this->actingAs(uaUser(), 'admin');
    Livewire::test(UsersIndex::class)->assertDontSee('Recepție');
});

it('creeaza un utilizator cu accesul ales si cere cel putin o aplicatie', function () {
    $super = uaUser(['role' => 'superadmin']);
    $this->actingAs($super, 'admin');

    Livewire::test(UsersCreate::class)
        ->set('phone', '0733111222')->set('accessAdmin', false)->set('accessBar', true)->set('accessReception', true)
        ->call('save')->assertHasNoErrors();

    $created = Admin::where('phone', '0733111222')->sole();
    expect($created->access_admin)->toBeFalse()->and($created->access_bar)->toBeTrue()->and($created->access_reception)->toBeTrue();

    Livewire::test(UsersCreate::class)
        ->set('phone', '0733111333')->set('accessAdmin', false)
        ->call('save')->assertHasErrors(['accessAdmin']);
    expect(Admin::where('phone', '0733111333')->exists())->toBeFalse();
});

it('completarea invitatiei unui barman nu il logheaza in panoul web', function () {
    $barman = uaUser(['access_admin' => false, 'access_bar' => true, 'name' => null]);

    Livewire::test(InviteComplete::class, ['admin' => $barman])
        ->set('name', 'Bogdan Barman')->set('password', 'parola-noua')->set('password_confirmation', 'parola-noua')
        ->call('save')->assertRedirect(route('admin.login'));

    expect($barman->fresh()->activated_at)->not->toBeNull()
        ->and(Auth::guard('admin')->check())->toBeFalse();
});

it('dashboardul numara la Administratori doar conturile cu acces la panou', function () {
    $super = uaUser(['role' => 'superadmin']);
    uaUser(['access_admin' => false, 'access_bar' => true]);
    uaUser();

    expect(Admin::withAccess(Admin::APP_ADMIN)->count())->toBe(2);

    $this->actingAs($super, 'admin');
    Livewire::test(Dashboard::class)->assertSee('din 2 în total');
});
