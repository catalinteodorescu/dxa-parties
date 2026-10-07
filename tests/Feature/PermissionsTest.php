<?php

use App\Livewire\Admin\Parties\Index as PartiesIndex;
use App\Livewire\Admin\Sales\Index as SalesIndex;
use App\Livewire\Admin\Users\Create as UsersCreate;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Permissions as UsersPermissions;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Party;
use App\Support\DashboardSections;
use App\Support\PermissionGuard;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 46). Permisiuni utilizatori: 4 niveluri pe secțiune + acțiuni sensibile, meniu, rute, acțiuni Livewire. */
function prmAdmin(array $levels = [], array $actions = [], string $role = 'admin'): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => $actions],
    ]);
}

function prmParty(string $name = 'Petrecere permisiuni'): Party
{
    return Party::create([
        'name' => $name, 'kind' => 'basic', 'start_date' => '2030-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => true, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'draft',
        'payment_methods' => ['cash'], 'online_sales' => false, 'ticket_types' => [],
    ]);
}

// ---------- model ----------

it('superadmin-ul are totul, un cont fără matrice nu are nimic', function () {
    $super = prmAdmin(role: 'superadmin');
    $none = Admin::create(['name' => 'Fără', 'phone' => '0711111111', 'role' => 'admin', 'is_active' => true, 'password' => 'x-secret-1']);

    expect($super->permits('users', 'delete'))->toBeTrue()
        ->and($super->permitsAction('export_bar_reports'))->toBeTrue()
        ->and($none->permits('parties'))->toBeFalse()
        ->and($none->permitsAction('export_bar_reports'))->toBeFalse();
});

it('nivelurile sunt cumulative și se opresc la maximul secțiunii', function () {
    $a = prmAdmin(['parties' => 2, 'bar_stats' => 3, 'logs' => 1]);

    expect($a->permits('parties', 'view'))->toBeTrue()
        ->and($a->permits('parties', 'edit'))->toBeTrue()
        ->and($a->permits('parties', 'delete'))->toBeFalse()
        ->and($a->permits('promoters'))->toBeFalse()
        ->and($a->permissionLevel('bar_stats'))->toBe(Permissions::VIEW)   // statisticile au maxim „vizualizare”
        ->and($a->permitsAny('promoters', 'logs'))->toBeTrue()
        ->and($a->permitsAny('promoters', 'sales'))->toBeFalse();
});

it('acțiunile sensibile sunt bife separate de nivel', function () {
    $a = prmAdmin(['sales' => 2], ['export_bar_reports']);

    expect($a->permitsAction('export_bar_reports'))->toBeTrue()->and($a->permitsAction('cancel_sales'))->toBeFalse();
});

it('normalize scoate ce nu există și încadrează nivelurile', function () {
    $n = Permissions::normalize(['levels' => ['parties' => 9, 'inexistent' => 2, 'logs' => 3, 'sales' => 0], 'actions' => ['export_bar_reports', 'nu-exista']]);

    expect($n['levels'])->toBe(['parties' => 3, 'logs' => 1])->and($n['actions'])->toBe(['export_bar_reports']);
});

it('adminii existenți păstrează ce aveau: totul, mai puțin Utilizatori și Jurnal', function () {
    $legacy = Permissions::legacyAdmin();

    expect($legacy['levels'])->not->toHaveKeys(['users', 'logs'])->toHaveKey('parties')
        ->and($legacy['actions'])->toBe(array_keys(Permissions::ACTIONS));
});

it('descrie schimbarea pentru jurnal', function () {
    $text = Permissions::describeChange(['levels' => ['parties' => 1]], ['levels' => ['parties' => 2, 'sales' => 1], 'actions' => ['export_bar_reports']]);

    expect($text)->toContain('Petreceri: modificare')->toContain('Vânzări bar: vizualizare')->toContain('Export PDF — raportări casă bar (da)');
    expect(Permissions::describeChange(['levels' => ['parties' => 1]], ['levels' => ['parties' => 1]]))->toBe('');
});

// ---------- rute + meniu ----------

it('o pagină cere nivelul ei: vizualizare deschide lista, nu și formularul de adăugare', function () {
    $a = prmAdmin(['parties' => 1]);

    $this->actingAs($a, 'admin')->get(route('admin.parties.index'))->assertOk();
    $this->actingAs($a, 'admin')->get(route('admin.parties.create'))->assertForbidden()->assertSee('data-no-permission', false);
    $this->actingAs($a, 'admin')->get(route('admin.promoters.index'))->assertForbidden()->assertSee('Nu ai permisiunea necesară');
});

it('cu nivelul „modificare” se deschide și formularul', function () {
    $a = prmAdmin(['parties' => 2]);

    $this->actingAs($a, 'admin')->get(route('admin.parties.create'))->assertOk();
});

it('refuzul la o pagină se scrie o singură dată în jurnal pe sesiune', function () {
    $a = prmAdmin(['parties' => 1]);

    $this->actingAs($a, 'admin')->get(route('admin.promoters.index'))->assertForbidden();
    $this->get(route('admin.promoters.index'))->assertForbidden();

    expect(AdminActivityLog::where('action', 'admin.permission.denied')->count())->toBe(1);
});

it('meniul și dashboard-ul arată doar ce are voie utilizatorul', function () {
    $a = prmAdmin(['parties' => 1, 'announcements' => 1]);

    $html = $this->actingAs($a, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain(route('admin.parties.index'))->toContain(route('admin.announcements.index'))
        ->not->toContain(route('admin.sales.index'))
        ->not->toContain(route('admin.promoters.index'))
        ->not->toContain(route('admin.users.index'))
        ->not->toContain(route('admin.logs.index'))
        ->not->toContain(route('admin.settings.index'))
        ->toContain(route('admin.account.edit'));    // contul propriu e mereu accesibil
});

it('superadmin-ul vede tot meniul', function () {
    $html = $this->actingAs(prmAdmin(role: 'superadmin'), 'admin')->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain(route('admin.users.index'))->toContain(route('admin.logs.index'))->toContain(route('admin.settings.index'));
});

it('PDF-ul de raport cere și bifa „Export PDF”', function () {
    $without = prmAdmin(['bar_reports' => 1]);
    $with = prmAdmin(['bar_reports' => 1], ['export_bar_reports']);

    $this->actingAs($without, 'admin')->get('/admin/bar/reports/999/pdf')->assertForbidden();
    // cu bifa trece de middleware (raportul inexistent → 404, nu 403)
    $this->actingAs($with, 'admin')->get('/admin/bar/reports/999/pdf')->assertNotFound();
});

it('toate rutele panoului au o permisiune sau sunt scutite explicit', function () {
    $exempt = ['admin.login', 'admin.forgot-password', 'admin.invite.complete', 'admin.reset-password', 'admin.setup-phone',
        'admin.dashboard', 'admin.account.edit', 'admin.logout'];

    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'admin.') && ! in_array($r->getName(), $exempt, true))
        ->filter(fn ($r) => in_array('auth:admin', $r->gatherMiddleware(), true))
        ->reject(fn ($r) => collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'admin.can:')))
        ->map(fn ($r) => $r->getName())->values()->all();

    expect($unguarded)->toBe([]);
});

it('toate componentele panoului sunt păzite sau scutite explicit', function () {
    $files = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Livewire/Admin'))))
        ->filter(fn ($f) => $f->isFile() && $f->getExtension() === 'php' && ! str_contains($f->getPathname(), '/Concerns/'))
        ->map(fn ($f) => str_replace(['/', '.php'], ['\\', ''], substr($f->getPathname(), strlen(app_path('Livewire/Admin')) + 1)));

    $unknown = $files->reject(fn ($c) => isset(PermissionGuard::COMPONENTS[$c]) || in_array($c, PermissionGuard::EXEMPT, true))->values()->all();
    expect($unknown)->toBe([]);

    foreach (array_merge(array_keys(PermissionGuard::COMPONENTS), PermissionGuard::EXEMPT) as $c) {
        expect(class_exists('App\\Livewire\\Admin\\'.$c))->toBeTrue("clasa {$c} nu există");
    }
    foreach (PermissionGuard::COMPONENTS as $section) {
        expect(Permissions::isSection($section))->toBeTrue();
    }
});

// ---------- acțiuni Livewire ----------

it('cu „vizualizare” nu se poate modifica nimic, nici apelând direct metodele', function () {
    $party = prmParty();
    $viewer = prmAdmin(['parties' => 1]);

    Livewire::actingAs($viewer, 'admin')->test(PartiesIndex::class)->call('toggleActive', $party->id)->assertForbidden();
    Livewire::actingAs($viewer, 'admin')->test(PartiesIndex::class)->call('delete', $party->id)->assertForbidden();
    expect(Party::find($party->id))->not->toBeNull();
});

it('cu „modificare” se modifică, dar nu se șterge', function () {
    $party = prmParty();
    $editor = prmAdmin(['parties' => 2]);

    Livewire::actingAs($editor, 'admin')->test(PartiesIndex::class)->call('toggleActive', $party->id)->assertOk();
    Livewire::actingAs($editor, 'admin')->test(PartiesIndex::class)->call('delete', $party->id)->assertForbidden();
    expect(Party::find($party->id))->not->toBeNull();
});

it('cu „ștergere” se poate șterge', function () {
    $party = prmParty();
    $owner = prmAdmin(['parties' => 3]);

    Livewire::actingAs($owner, 'admin')->test(PartiesIndex::class)->call('delete', $party->id)->assertOk();
    expect(Party::find($party->id))->toBeNull();
});

it('acțiunile sensibile cer bifa lor, indiferent de nivel', function () {
    $noFlag = prmAdmin(['sales' => 2]);
    $flag = prmAdmin(['sales' => 1], ['cancel_sales']);

    Livewire::actingAs($noFlag, 'admin')->test(SalesIndex::class)->call('cancel', 1, 'motiv')->assertForbidden();

    expect(PermissionGuard::allows($noFlag, 'sales', 'cancel'))->toBeFalse()
        ->and(PermissionGuard::allows($flag, 'sales', 'cancel'))->toBeTrue()   // bifa e suficientă, chiar și cu „vizualizare”
        ->and(PermissionGuard::allows($flag, 'sales', 'save'))->toBeFalse()    // dar nu deschide și modificarea obișnuită
        ->and(PermissionGuard::allows($noFlag, 'sales', 'clearFilters'))->toBeTrue();
});

it('regulile pe metode: ștergere, sensibile, navigare, excepții', function () {
    expect(PermissionGuard::requirement('parties', 'delete'))->toBe(['level', Permissions::DELETE])
        ->and(PermissionGuard::requirement('parties', 'save'))->toBe(['level', Permissions::EDIT])
        ->and(PermissionGuard::requirement('parties', 'clearFilters'))->toBe(['level', Permissions::VIEW])
        ->and(PermissionGuard::requirement('participants', 'anonymize'))->toBe(['action', 'anonymize'])
        ->and(PermissionGuard::requirement('requisitions', 'reopen'))->toBe(['level', Permissions::EDIT])
        ->and(PermissionGuard::requirement('reception_reports', 'reopen'))->toBe(['action', 'reopen_reception_reports'])
        ->and(PermissionGuard::requirement('parties', '$refresh'))->toBeNull();
});

it('un cont dezactivat din secțiune pierde acțiunile imediat (se citește la fiecare cerere)', function () {
    $party = prmParty();
    $a = prmAdmin(['parties' => 3]);

    Livewire::actingAs($a, 'admin')->test(PartiesIndex::class)->call('delete', $party->id)->assertOk();

    $a->update(['permissions' => Permissions::none()]);
    $this->actingAs($a->fresh(), 'admin')->get(route('admin.parties.index'))->assertForbidden();
});

// ---------- dashboard ----------

it('secțiunile din dashboard urmează permisiunile', function () {
    $a = prmAdmin(['announcements' => 1, 'sales' => 1]);

    expect(DashboardSections::active($a))->toBe(['announcements', 'bar'])
        ->and(DashboardSections::active(prmAdmin()))->toBe([])
        ->and(DashboardSections::active(prmAdmin(role: 'superadmin')))->toContain('admins')->toContain('participants');
});

// ---------- utilizatori ----------

it('superadmin-ul creează un utilizator cu permisiunile bifate', function () {
    $super = prmAdmin(role: 'superadmin');

    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('phone', '0722000111')
        ->call('setLevel', 'parties', 2)
        ->call('setLevel', 'bar_stats', 3)          // se încadrează la maximul secțiunii
        ->set('actions.export_bar_reports', true)
        ->call('save')
        ->assertHasNoErrors();

    $new = Admin::where('phone', '0722000111')->first();
    expect($new->permissions)->toBe(['levels' => ['parties' => 2, 'bar_stats' => 1], 'actions' => ['export_bar_reports']]);
    expect(AdminActivityLog::where('action', 'admin.permissions.updated')->exists())->toBeTrue();
});

it('„Copiază permisiunile” de la alt utilizator completează matricea, apoi se poate ajusta', function () {
    $super = prmAdmin(role: 'superadmin');
    $source = prmAdmin(['sales' => 2, 'menu' => 3], ['cancel_sales']);

    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('phone', '0722000222')
        ->set('copyFrom', $source->id)
        ->call('copyPermissions')
        ->assertSet('levels.sales', 2)
        ->assertSet('levels.menu', 3)
        ->assertSet('actions.cancel_sales', true)
        ->call('setLevel', 'menu', 1)
        ->call('save');

    $new = Admin::where('phone', '0722000222')->first();
    expect($new->permissions['levels'])->toBe(['sales' => 2, 'menu' => 1])->and($new->permissions['actions'])->toBe(['cancel_sales']);
});

it('un superadmin nu apare printre sursele de copiere, iar copierea de la el e refuzată', function () {
    $super = prmAdmin(role: 'superadmin');
    $other = prmAdmin(role: 'superadmin');

    Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->set('copyFrom', $other->id)->call('copyPermissions')->assertHasErrors('copyFrom');
});

it('cine nu e superadmin și creează un utilizator nu poate da permisiuni: contul pornește fără niciuna', function () {
    $manager = prmAdmin(['users' => 2]);

    Livewire::actingAs($manager, 'admin')->test(UsersCreate::class)
        ->set('phone', '0722000333')
        ->set('levels.parties', 3)                   // chiar dacă ar trimite valori
        ->call('save')->assertHasNoErrors();

    expect(Admin::where('phone', '0722000333')->first()->permissions)->toBe(Permissions::none());
});

it('pagina de permisiuni e doar pentru superadmin și salvează cu intrare în jurnal', function () {
    $super = prmAdmin(role: 'superadmin');
    $target = prmAdmin(['parties' => 1]);
    $manager = prmAdmin(['users' => 3]);

    $this->actingAs($manager, 'admin')->get(route('admin.users.permissions', $target))->assertForbidden();
    $this->actingAs($super, 'admin')->get(route('admin.users.permissions', $super))->assertNotFound();

    Livewire::actingAs($super, 'admin')->test(UsersPermissions::class, ['admin' => $target])
        ->assertSet('levels.parties', 1)
        ->call('setLevel', 'parties', 3)
        ->call('setLevel', 'sales', 2)
        ->set('actions.export_bar_reports', true)
        ->call('save')->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->permissions)->toBe(['levels' => ['parties' => 3, 'sales' => 2], 'actions' => ['export_bar_reports']]);
    expect(AdminActivityLog::where('action', 'admin.permissions.updated')->latest('id')->first()->description)
        ->toContain('Petreceri: ștergere')->toContain('Vânzări bar: modificare');
});

it('„Acces complet / Doar vizualizare / Fără acces” completează matricea', function () {
    $super = prmAdmin(role: 'superadmin');

    $c = Livewire::actingAs($super, 'admin')->test(UsersCreate::class)
        ->call('setAll', 'full')->assertSet('levels.users', 3)->assertSet('actions.anonymize', true)
        ->call('setAll', 'view')->assertSet('levels.parties', 1)->assertSet('actions.anonymize', false)
        ->call('setAll', 'none')->assertSet('levels.parties', 0);
});

it('Utilizatorii: vizualizare = listă, modificare = status/acces, ștergere = ștergere; superadmin-ul rămâne protejat', function () {
    $super = prmAdmin(role: 'superadmin');
    $target = prmAdmin(['parties' => 1]);

    $viewer = prmAdmin(['users' => 1]);
    $editor = prmAdmin(['users' => 2]);
    $deleter = prmAdmin(['users' => 3]);

    $this->actingAs($viewer, 'admin')->get(route('admin.users.index'))->assertOk();
    $this->actingAs($viewer, 'admin')->get(route('admin.users.create'))->assertForbidden();
    Livewire::actingAs($viewer, 'admin')->test(UsersIndex::class)->call('updateStatus', $target->id, false)->assertForbidden();

    Livewire::actingAs($editor, 'admin')->test(UsersIndex::class)->call('updateStatus', $target->id, false)->assertOk();
    expect($target->fresh()->is_active)->toBeFalse();
    Livewire::actingAs($editor, 'admin')->test(UsersIndex::class)->call('updateAccess', $target->id, 'bar', true)->assertOk();
    Livewire::actingAs($editor, 'admin')->test(UsersIndex::class)->call('deleteAdmin', $target->id)->assertForbidden();
    Livewire::actingAs($editor, 'admin')->test(UsersIndex::class)->call('updateRole', $target->id, 'superadmin')->assertForbidden();

    // superadmin-ul nu poate fi atins de un non-superadmin, nici cu „ștergere”
    Livewire::actingAs($deleter, 'admin')->test(UsersIndex::class)->call('updateStatus', $super->id, false)->assertForbidden();
    Livewire::actingAs($deleter, 'admin')->test(UsersIndex::class)->call('deleteAdmin', $super->id)->assertForbidden();

    Livewire::actingAs($deleter, 'admin')->test(UsersIndex::class)->call('deleteAdmin', $target->id)->assertOk();
    expect(Admin::find($target->id))->toBeNull();
});

it('pagina Utilizatori arată butonul de permisiuni doar superadmin-ului', function () {
    $super = prmAdmin(role: 'superadmin');
    $target = prmAdmin(['parties' => 1]);
    $deleter = prmAdmin(['users' => 3]);

    Livewire::actingAs($super, 'admin')->test(UsersIndex::class)->assertSeeHtml('data-user-permissions');
    Livewire::actingAs($deleter, 'admin')->test(UsersIndex::class)->assertDontSeeHtml('data-user-permissions');
});

it('Jurnalul e o permisiune: se vede doar cu „vizualizare” la Jurnal', function () {
    $this->actingAs(prmAdmin(['parties' => 3]), 'admin')->get(route('admin.logs.index'))->assertForbidden();
    $this->actingAs(prmAdmin(['logs' => 1]), 'admin')->get(route('admin.logs.index'))->assertOk();
});

// ---------- butoane în interfață ----------

it('butoanele de modificare și ștergere apar doar cui are nivelul', function () {
    $party = prmParty('Petrecere butoane');

    $html = fn (Admin $a) => Livewire::actingAs($a, 'admin')->test(PartiesIndex::class)->html();

    $viewer = $html(prmAdmin(['parties' => 1]));
    expect($viewer)->toContain('Petrecere butoane')->not->toContain('Editează')->not->toContain('Duplică')->not->toContain('Șterge petrecerea')->not->toContain(route('admin.parties.create'));

    $editor = $html(prmAdmin(['parties' => 2]));
    expect($editor)->toContain('Editează')->toContain('Duplică')->not->toContain('Șterge petrecerea');

    $owner = $html(prmAdmin(['parties' => 3]));
    expect($owner)->toContain('Șterge petrecerea')->toContain(route('admin.parties.create'));

    // „Publică” e acțiune sensibilă: apare doar cu bifa, indiferent de nivel
    expect($html(prmAdmin(['parties' => 2])))->not->toContain('tooltip="Publică"');
    expect($html(prmAdmin(['parties' => 1], ['publish_parties'])))->toContain('Publică');
});

it('butoanele din matrice trimit cheile reale ale secțiunilor și evidențiază cumulativ nivelurile', function () {
    $super = prmAdmin(role: 'superadmin');
    $c = Livewire::actingAs($super, 'admin')->test(UsersCreate::class);

    foreach (array_keys(Permissions::SECTIONS) as $key) {
        expect($c->html())->toContain("setLevel('{$key}', 1)");
    }
    expect($c->html())->not->toContain("setLevel('0',")->not->toContain("setLevel('1',");

    $active = fn (string $html, string $section) => preg_match_all('/data-perm="'.$section.'" data-level="(\d)"\s+data-active/', $html, $m) ? array_map('intval', $m[1]) : [];

    // Modifică ⇒ sunt evidențiate și Vezi, și Modifică (nu și Fără / Șterge)
    expect($active($c->call('setLevel', 'parties', 2)->html(), 'parties'))->toBe([1, 2]);
    // Șterge ⇒ Vezi + Modifică + Șterge
    expect($active($c->call('setLevel', 'parties', 3)->html(), 'parties'))->toBe([1, 2, 3]);
    // Vezi ⇒ doar Vezi
    expect($active($c->call('setLevel', 'parties', 1)->html(), 'parties'))->toBe([1]);
    // Fără ⇒ doar Fără
    expect($active($c->call('setLevel', 'parties', 0)->html(), 'parties'))->toBe([0]);
});

it('acțiunile sensibile se pornesc cu toggle', function () {
    $c = Livewire::actingAs(prmAdmin(role: 'superadmin'), 'admin')->test(UsersCreate::class)
        ->call('toggleAction', 'publish_parties')->assertSet('actions.publish_parties', true);

    expect($c->html())->toMatch('/data-action="publish_parties"\s+data-on/');

    $c->call('toggleAction', 'publish_parties')->assertSet('actions.publish_parties', false);
    $c->call('toggleAction', 'nu-exista')->assertStatus(422);
});

it('acțiunile sensibile apar în secțiunea din care fac parte', function () {
    $html = Livewire::actingAs(prmAdmin(role: 'superadmin'), 'admin')->test(UsersCreate::class)->html();

    foreach (Permissions::ACTIONS as $key => [, , $section]) {
        expect(Permissions::isSection($section))->toBeTrue();
        expect($html)->toMatch('/data-section-actions="'.$section.'".*?data-action="'.$key.'"/s');
    }
    expect($html)->not->toContain('Acțiuni sensibile</div>');
});

it('fiecare metodă sensibilă aparține unei singure acțiuni din secțiunea ei', function () {
    $seen = [];
    foreach (Permissions::ACTIONS as $key => [, , $section, $methods]) {
        foreach ($methods as $m) {
            expect($seen)->not->toHaveKey($section.'.'.$m);
            $seen[$section.'.'.$m] = $key;
            expect(Permissions::actionForMethod($section, $m))->toBe($key);
        }
    }
});
