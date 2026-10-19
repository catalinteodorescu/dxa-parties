<?php

use App\Livewire\Admin\Owner\Index as OwnerIndex;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\License;
use App\Models\Plan;
use App\Services\Installer;
use App\Services\Plan\PlanGate;
use App\Support\DashboardSections;
use App\Support\Features;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 79). Planuri Basic/Pro: catalog, licență, blocarea în panou, limite de conturi, rolul proprietar, comanda dxa:owner. */
function r79Account(string $role = 'admin', bool $active = true): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => $active, 'password' => 'secret-pass-123',
        'permissions' => ['levels' => [], 'actions' => []],
    ]);
}

function r79Plan(string $key): void
{
    License::query()->first()->update(['plan_key' => $key]);
    PlanGate::flush();
}

// ---------- catalog + date inițiale ----------

it('catalogul trimite doar la secțiuni și acțiuni care există', function () {
    foreach (Features::ALL as $key => [, , $sections, $actions]) {
        foreach ($sections as $s) {
            expect(Permissions::isSection($s))->toBeTrue("secțiunea {$s} ({$key}) nu există");
        }
        foreach ($actions as $a) {
            expect(array_key_exists($a, Permissions::ACTIONS))->toBeTrue("acțiunea {$a} ({$key}) nu există");
        }
        expect(array_key_exists($key, Features::grouped()[Features::GROUPS[Features::ALL[$key][1]]]))->toBeTrue();
    }
    expect(Features::forSection('stocks'))->toBe('stocks')->and(Features::forSection('users'))->toBeNull()
        ->and(Features::forAction('export_bar_reports'))->toBe('bar_pdf')->and(Features::forAction('cancel_sales'))->toBeNull();
});

it('migrarea creează Basic și Pro cu valorile stabilite, iar instalarea pornește pe Pro', function () {
    $basic = Plan::where('key', 'basic')->first();
    $pro = Plan::where('key', 'pro')->first();

    expect(License::first()->plan_key)->toBe('pro')
        ->and($basic->features)->toContain('loyalty', 'logs', 'push_participants', 'bar_sales')
        ->and($basic->features)->not->toContain('credits', 'stocks', 'promoters', 'bar_stats', 'dashboard_alerts')
        ->and($pro->features)->toBe(Features::keys())
        ->and($basic->limits)->toBe(['max_superadmins' => 1, 'max_admins' => 1, 'sms_included' => 100])
        ->and($pro->limits)->toBe(['max_superadmins' => null, 'max_admins' => null, 'sms_included' => 300])
        ->and((float) $basic->price_monthly)->toBe(199.0)->and((float) $basic->price_yearly_monthly)->toBe(169.0)
        ->and((float) $pro->price_monthly)->toBe(349.0)->and((float) $pro->price_yearly_monthly)->toBe(299.0);
});

it('fără licență totul rămâne permis', function () {
    License::query()->delete();
    PlanGate::flush();

    expect(PlanGate::allows('stocks'))->toBeTrue()->and(PlanGate::limit('max_admins'))->toBeNull();
});

// ---------- blocarea în panou ----------

it('pe Basic nici superadmin-ul nu mai are secțiunile din Pro, dar le are pe cele din Basic', function () {
    $super = r79Account('superadmin');
    r79Plan('basic');

    expect($super->permits('stocks'))->toBeFalse()
        ->and($super->lockedByPlan('stocks'))->toBeTrue()
        ->and($super->permits('promoters'))->toBeFalse()
        ->and($super->permits('credits'))->toBeFalse()
        ->and($super->permits('sales', 'edit'))->toBeTrue()
        ->and($super->permits('loyalty'))->toBeTrue()
        ->and($super->permits('logs'))->toBeTrue()
        ->and($super->permitsAction('export_bar_reports'))->toBeFalse()
        ->and($super->permitsAction('cancel_sales'))->toBeTrue()
        ->and($super->permitsAction('credits_adjust'))->toBeFalse()
        ->and($super->permitsAction('manage_notifications'))->toBeTrue();   // push către participanți e în Basic

    r79Plan('pro');
    expect($super->permits('stocks'))->toBeTrue()->and($super->lockedByPlan('stocks'))->toBeFalse();
});

it('bifa din plan pornește sau oprește o funcție pentru toate instalările pe acel plan', function () {
    $super = r79Account('superadmin');
    $basic = Plan::where('key', 'basic')->first();

    r79Plan('basic');
    expect($super->permits('stocks'))->toBeFalse()->and($super->permits('logs'))->toBeTrue();

    $basic->update(['features' => array_values(array_diff([...$basic->features, 'stocks'], ['logs']))]);
    PlanGate::flush();
    expect($super->permits('stocks'))->toBeTrue()->and($super->permits('logs'))->toBeFalse();
});

it('rutele și pagina „nu e inclusă în plan” (403), cu proprietarul trecând peste', function () {
    $super = r79Account('superadmin');
    $owner = r79Account('owner');
    r79Plan('basic');

    $this->actingAs($super, 'admin')->get(route('admin.stock-items.index'))->assertForbidden()->assertSee('data-plan-locked', false)->assertSee('Stocuri');
    $this->actingAs($super, 'admin')->get(route('admin.participants.stats'))->assertForbidden()->assertSee('data-plan-locked', false);
    $this->actingAs($super, 'admin')->get(route('admin.parties.overview'))->assertForbidden();
    $this->actingAs($super, 'admin')->get(route('admin.credits.topups'))->assertForbidden();
    $this->actingAs($super, 'admin')->get(route('admin.push.index'))->assertOk();   // push către participanți e în Basic

    $this->actingAs($owner, 'admin')->get(route('admin.stock-items.index'))->assertOk();

    r79Plan('pro');
    $this->actingAs($super, 'admin')->get(route('admin.stock-items.index'))->assertOk();
});

it('meniul arată lacăt pentru funcțiile blocate și pagina dedicată', function () {
    $super = r79Account('superadmin');
    r79Plan('basic');

    $this->actingAs($super, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertSee('data-plan-lock="stocks"', false)->assertSee('data-plan-lock="promoters"', false)
        ->assertSee('data-plan-lock="participants_stats"', false)->assertDontSee('data-owner-link', false);

    $this->actingAs($super, 'admin')->get(route('admin.plan-locked', 'stocks'))->assertOk()->assertSee('nu e inclusă în planul tău')->assertSee('Pro');
    $this->actingAs($super, 'admin')->get(route('admin.plan-locked', 'nu-exista'))->assertNotFound();

    r79Plan('pro');
    $this->actingAs($super, 'admin')->get(route('admin.dashboard'))->assertDontSee('data-plan-lock', false);
});

it('cardurile „Atenție” și blocul „Aplicație” din dashboard cer planul', function () {
    $super = r79Account('superadmin');
    r79Plan('basic');
    expect(DashboardSections::active($super))->not->toContain('alerts', 'app');

    r79Plan('pro');
    expect(DashboardSections::active($super))->toContain('alerts', 'app');
});

// ---------- limite de conturi ----------

it('pe Basic nu se poate adăuga al doilea admin, dar pe Pro da', function () {
    $super = r79Account('superadmin');
    r79Account('admin');
    r79Plan('basic');

    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('phone', '0712345678')->set('accessAdmin', true)->call('save')
        ->assertHasErrors('phone');
    expect(Admin::where('phone', '0712345678')->exists())->toBeFalse();

    r79Plan('pro');
    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('phone', '0712345678')->set('accessAdmin', true)->call('save')
        ->assertHasNoErrors();
    expect(Admin::where('phone', '0712345678')->exists())->toBeTrue();
});

it('reactivarea unui cont peste limită e refuzată', function () {
    $super = r79Account('superadmin');
    r79Account('admin');
    $off = r79Account('admin', active: false);
    r79Plan('basic');

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('updateStatus', $off->id, true);
    expect($off->fresh()->is_active)->toBeFalse();

    r79Plan('pro');
    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('updateStatus', $off->id, true);
    expect($off->fresh()->is_active)->toBeTrue();
});

it('al doilea superadmin e blocat pe Basic (schimbarea rolului)', function () {
    $super = r79Account('superadmin');
    $other = r79Account('admin');
    r79Plan('basic');

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('updateRole', $other->id, 'superadmin');
    expect($other->fresh()->role)->toBe('admin');
});

// ---------- proprietarul ----------

it('proprietarul e ascuns din lista clientului și nu poate fi modificat de acolo', function () {
    $super = r79Account('superadmin');
    $owner = r79Account('owner');

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->assertDontSee($owner->name);
    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->call('updateStatus', $owner->id, false)->assertNotFound();
    expect($owner->fresh()->is_active)->toBeTrue()
        ->and(PlanGate::accounts('superadmin'))->toBe(1)    // proprietarul nu se numără
        ->and(Admin::withAccess(Admin::APP_ADMIN)->pluck('id')->all())->not->toContain($owner->id);
});

it('proprietarul intră doar în panou: nu e lucrător în Recepție/Bar și nu primește notificări de personal', function () {
    $owner = r79Account('owner');

    expect($owner->canAccess(Admin::APP_ADMIN))->toBeTrue()
        ->and($owner->canAccess(Admin::APP_BAR))->toBeFalse()
        ->and($owner->canAccess(Admin::APP_RECEPTION))->toBeFalse()
        ->and($owner->isSuperAdmin())->toBeTrue();
});

it('pagina proprietarului: 404 pentru oricine altcineva, vizibilă pentru proprietar', function () {
    $super = r79Account('superadmin');
    $owner = r79Account('owner');

    $this->actingAs($super, 'admin')->get(route('admin.owner.index'))->assertNotFound();
    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()
        ->assertSee('data-owner-page', false)->assertSee('data-plan-matrix', false)->assertSee('Stocuri')->assertSee('data-owner-link', false);
    Livewire::actingAs($super, 'admin')->test(OwnerIndex::class)->assertNotFound();
});

it('proprietarul schimbă ce include un plan, iar schimbarea se aplică și se jurnalizează', function () {
    $owner = r79Account('owner');
    $super = r79Account('superadmin');

    Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)
        ->set('plans.basic.features.stocks', true)
        ->set('plans.basic.limits.sms_included', '150')
        ->set('plans.pro.limits.max_admins', '')
        ->call('savePlans')->assertHasNoErrors();

    expect(Plan::where('key', 'basic')->first()->features)->toContain('stocks')
        ->and(Plan::where('key', 'basic')->first()->limits['sms_included'])->toBe(150)
        ->and(AdminActivityLog::where('action', 'owner.plans.updated')->exists())->toBeTrue();

    r79Plan('basic');
    expect($super->permits('stocks'))->toBeTrue();
});

it('la coborârea pe Basic cu prea mulți admini apare lista de conturi, iar proprietarul alege pe care le dezactivează', function () {
    $owner = r79Account('owner');
    r79Account('superadmin');
    $a1 = r79Account('admin');
    $a2 = r79Account('admin');
    $a3 = r79Account('admin');

    $page = Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)->set('licensePlan', 'basic')->call('saveLicense');
    $page->assertSet('showDowngrade', true)->assertSee('data-downgrade-panel', false)->assertSee('data-deactivate="'.$a1->id.'"', false)
        ->assertSee('data-deactivate="'.$a3->id.'"', false);
    expect(License::first()->plan_key)->toBe('pro');        // 3 admini activi, Basic permite 1: nimic nu s-a schimbat

    // prea puține bifate: refuzat
    $page->set('deactivate.'.$a1->id, true)->call('confirmDowngrade');
    expect(License::first()->plan_key)->toBe('pro')->and($a1->fresh()->is_active)->toBeTrue();

    // suficiente bifate: dezactivează doar ce a ales și aplică planul
    $page->set('deactivate.'.$a2->id, true)->call('confirmDowngrade')->assertSet('showDowngrade', false);
    expect(License::first()->plan_key)->toBe('basic')
        ->and($a1->fresh()->is_active)->toBeFalse()->and($a2->fresh()->is_active)->toBeFalse()->and($a3->fresh()->is_active)->toBeTrue()
        ->and(AdminActivityLog::where('action', 'admin.status.updated')->count())->toBe(2);
});

it('coborârea poate fi anulată fără nicio schimbare', function () {
    $owner = r79Account('owner');
    r79Account('superadmin');
    $a1 = r79Account('admin');
    r79Account('admin');

    Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)->set('licensePlan', 'basic')->call('saveLicense')
        ->set('deactivate.'.$a1->id, true)->call('cancelDowngrade')->assertSet('showDowngrade', false);
    expect(License::first()->plan_key)->toBe('pro')->and($a1->fresh()->is_active)->toBeTrue();
});

it('trecerea pe Basic fără depășire se aplică direct, fără excepții pe funcție', function () {
    $owner = r79Account('owner');
    r79Account('superadmin');
    r79Account('admin');

    Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)
        ->set('licensePlan', 'basic')->call('saveLicense')->assertSet('showDowngrade', false);

    expect(License::first()->plan_key)->toBe('basic')->and(License::first()->overrides)->toBe([])
        ->and(AdminActivityLog::where('action', 'owner.license.updated')->exists())->toBeTrue();
    PlanGate::flush();
    expect(PlanGate::allows('credits'))->toBeFalse()->and(PlanGate::allows('stocks'))->toBeFalse()->and(PlanGate::allows('loyalty'))->toBeTrue();
});

it('pagina folosește componentele custom (select și bife) și nu mai are excepții sau „Activ acum”', function () {
    $owner = r79Account('owner');

    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()
        ->assertDontSee('Excepție pentru acest client')->assertDontSee('Activ acum')->assertDontSee('pornit oricum', false)->assertSee('data-active-plan', false)
        ->assertDontSee('<select', false)->assertSee('peer sr-only', false);
});

// ---------- contul de proprietar (creat de instalare) ----------

it('instalarea creează proprietarul „Proprietar”, o singură dată, cu parola salvată ca hash', function () {
    expect(Admin::where('role', 'owner')->count())->toBe(0);
    expect(Installer::ensureOwner('0722111222', 'parola-lunga-1'))->toBe('created');

    $owner = Admin::where('phone', '0722111222')->first();
    expect($owner->role)->toBe('owner')->and($owner->name)->toBe('Proprietar')->and($owner->is_active)->toBeTrue()
        ->and($owner->getRawOriginal('password'))->not->toBe('parola-lunga-1')
        ->and(Hash::check('parola-lunga-1', $owner->getRawOriginal('password')))->toBeTrue()
        ->and($owner->canAccess(Admin::APP_ADMIN))->toBeTrue();

    expect(Installer::ensureOwner('0722111222', 'alta-parola-2'))->toBe('exists')->and(Admin::where('role', 'owner')->count())->toBe(1);
});

it('instalarea nu atinge un cont obișnuit care are deja numărul', function () {
    $normal = Admin::create(['name' => 'Eu', 'phone' => '0722111222', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass-123']);

    expect(Installer::ensureOwner('0722111222', 'parola-lunga-1'))->toBe('exists')
        ->and($normal->fresh()->role)->toBe('superadmin')->and(Admin::where('role', 'owner')->count())->toBe(0);
});

it('textul „managerii” nu mai apare în permisiuni', function () {
    expect(Permissions::ACTIONS['manage_staff_notifications'][1])->not->toContain('manager');
});
