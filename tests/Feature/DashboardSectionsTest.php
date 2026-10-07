<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MenuItems\Categories;
use App\Livewire\Admin\MenuItems\Index as MenuItemsIndex;
use App\Livewire\Admin\Settings\DashboardSections as SectionsPanel;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\DashboardSections;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 45). Secțiuni dashboard (Setări), ordinea Setărilor, pagina „Categorii produse”. */
function dsbAdmin(string $role = 'superadmin'): Admin
{
    return Admin::create(['name' => 'Admin '.$role, 'phone' => '+40700'.random_int(100000, 999999), 'role' => $role, 'is_active' => true, 'password' => 'secret-pass']);
}

function dsbKeys(): array
{
    return array_column(DashboardSections::all(), 'key');
}

it('fiecare secțiune din registru are fișierul ei și invers (o secțiune nouă trebuie înregistrată aici)', function () {
    $files = collect(glob(resource_path('views/livewire/admin/dashboard/_*.blade.php')))
        ->map(fn ($f) => preg_replace('/^_(.+)\.blade\.php$/', '$1', basename($f)))->sort()->values()->all();
    $keys = collect(array_keys(DashboardSections::DEFINITIONS))->sort()->values()->all();

    expect($files)->toBe($keys);
});

it('implicit: toate secțiunile, active, în ordinea din cod', function () {
    $all = DashboardSections::all();
    expect(array_column($all, 'key'))->toBe(array_keys(DashboardSections::DEFINITIONS))
        ->and(collect($all)->every(fn ($s) => $s['enabled']))->toBeTrue();
});

it('o secțiune nouă din cod apare la final, activă; una scoasă din cod dispare; ordinea salvată se păstrează', function () {
    Settings::set(DashboardSections::SETTING, json_encode([
        ['key' => 'participants', 'enabled' => false], ['key' => 'inexistenta', 'enabled' => true], ['key' => 'bar', 'enabled' => true],
        ['key' => 'bar', 'enabled' => false],   // dublură ignorată
    ]));
    $all = collect(DashboardSections::all());

    expect($all->pluck('key')->take(2)->all())->toBe(['participants', 'bar'])
        ->and($all->pluck('key')->all())->not->toContain('inexistenta')
        ->and($all->count())->toBe(count(DashboardSections::DEFINITIONS))
        ->and($all->firstWhere('key', 'participants')['enabled'])->toBeFalse()
        ->and($all->firstWhere('key', 'announcements')['enabled'])->toBeTrue()    // „nouă” față de salvare
        ->and($all->last()['key'])->not->toBe('bar');

    Settings::set(DashboardSections::SETTING, 'nu-e-json');
    expect(dsbKeys())->toBe(array_keys(DashboardSections::DEFINITIONS));
});

it('panoul din Setări: activare/dezactivare și reordonare (drag & drop + săgeți) se salvează pe loc', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    $c = Livewire::test(SectionsPanel::class);

    $c->call('toggle', 'bar');
    expect(collect(DashboardSections::all())->firstWhere('key', 'bar')['enabled'])->toBeFalse();
    $c->call('toggle', 'bar');
    expect(collect(DashboardSections::all())->firstWhere('key', 'bar')['enabled'])->toBeTrue();

    $c->call('reorder', 'participants', 0);
    expect(dsbKeys()[0])->toBe('participants');
    $c->call('moveDown', 'participants');
    expect(array_slice(dsbKeys(), 0, 2))->toBe(['announcements', 'participants']);
    $c->call('moveUp', 'participants')->call('moveUp', 'participants');   // la început nu iese din listă
    expect(dsbKeys()[0])->toBe('participants');
    $c->call('reorder', 'admins', 999);
    expect(array_slice(dsbKeys(), -1)[0])->toBe('admins');
    $c->call('reorder', 'cheie-falsa', 0)->call('toggle', 'cheie-falsa');
    expect(dsbKeys())->toHaveCount(count(DashboardSections::DEFINITIONS));
    expect(AdminActivityLog::where('action', 'settings.dashboard_sections_changed')->exists())->toBeTrue();
});

it('dashboardul respectă activarea și ordinea; „Administratori” rămâne doar pentru superadmin', function () {
    $this->actingAs(dsbAdmin(), 'admin');

    $html = Livewire::test(Dashboard::class)->html();
    foreach (['Anunțuri', 'Petreceri', 'Participanți', 'Administratori'] as $t) {
        expect($html)->toContain('>'.$t.'</h3>');
    }

    // participanții primii, anunțurile dezactivate
    Settings::set(DashboardSections::SETTING, json_encode([['key' => 'participants', 'enabled' => true], ['key' => 'announcements', 'enabled' => false]]));
    $html = Livewire::test(Dashboard::class)->html();
    expect($html)->not->toContain('>Anunțuri</h3>')
        ->and(strpos($html, '>Participanți</h3>'))->toBeLessThan(strpos($html, '>Petreceri</h3>'));

    // admin simplu: fără „Administratori”, chiar activă
    Settings::set(DashboardSections::SETTING, '');
    expect(DashboardSections::active(false))->not->toContain('admins')->and(DashboardSections::active(true))->toContain('admins');
});

it('Setări: ordinea cerută (Organizație, Aspect, Secțiuni dashboard, Metode de plată, Recepție, Card de fidelitate), fără „Școala” și fără categorii de meniu', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    $html = Livewire::test(SettingsIndex::class)->html();

    $order = ['>Organizație</h3>', '>Aspect</h3>', '>Secțiuni dashboard</h3>', '>Metode de plată</h3>', '>Recepție</h3>', '>Card de fidelitate</h3>'];
    $pos = array_map(fn ($t) => strpos($html, $t), $order);
    expect(in_array(false, $pos, true))->toBeFalse()->and($pos)->toBe(collect($pos)->sort()->values()->all());

    expect($html)->not->toContain('Școala')->not->toContain('școlii')->not->toContain('Categorii meniu bar');
    expect(collect(SettingsRegistry::sections())->pluck('label')->all())->toBe(['Organizație', 'Aspect', 'Recepție', 'Card de fidelitate']);
});

it('Setări: salvarea câmpurilor merge cu butonul unic (fără form), iar cheile vechi school_* rămân în uz', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    Livewire::test(SettingsIndex::class)->set('values.school_name', 'Studio Ritm')->call('save')->assertHasNoErrors();
    expect(Settings::get('school_name'))->toBe('Studio Ritm');
    Livewire::test(SettingsIndex::class)->set('values.school_name', '')->call('save')->assertHasErrors('values.school_name');
    expect(Livewire::test(SettingsIndex::class)->html())->toContain('wire:click="save"');
});

it('Bar › Meniu: buton „Categorii produse” lângă „Produs nou”', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    $html = Livewire::test(MenuItemsIndex::class)->html();
    expect($html)->toContain(route('admin.menu-items.categories'))->toContain('Categorii produse')->toContain('Produs nou');
    expect(strpos($html, 'Categorii produse'))->toBeLessThan(strpos($html, 'Produs nou'));
    $this->get(route('admin.menu-items.categories'))->assertOk()->assertSee('Categorii produse');
});

it('Categorii produse: adăugare, duplicat, redenumire, ascundere, ștergere (blocată cu produse) și reordonare', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    $c = Livewire::test(Categories::class);

    $c->set('newName', '  Cocktailuri ')->call('add')->assertSet('newName', '');
    $c->set('newName', 'Bere')->call('add');
    $c->set('newName', 'cocktailuri')->call('add');
    expect($c->get('error'))->toContain('Există deja');
    $c->set('newName', '   ')->call('add');
    expect($c->get('error'))->toContain('obligatoriu');
    $c->set('newName', str_repeat('x', 121))->call('add');
    expect($c->get('error'))->toContain('120');

    $ids = MenuCategory::ordered()->pluck('name', 'id')->all();
    expect(array_values($ids))->toBe(['Cocktailuri', 'Bere']);
    [$cock, $bere] = array_keys($ids);

    $c->call('reorder', $bere, 0);
    expect(MenuCategory::ordered()->pluck('name')->all())->toBe(['Bere', 'Cocktailuri']);

    $c->set("names.$cock", 'Cocktails')->call('rename', $cock);
    expect(MenuCategory::find($cock)->name)->toBe('Cocktails');
    $c->call('toggleActive', $cock);
    expect(MenuCategory::find($cock)->is_active)->toBeFalse();

    MenuItem::create(['name' => 'Bere mare', 'menu_category_id' => $bere, 'price' => 10, 'is_active' => true]);
    $c->call('delete', $bere);
    expect(MenuCategory::find($bere))->not->toBeNull()->and($c->get('error'))->toContain('produs');
    $c->call('delete', $cock);
    expect(MenuCategory::find($cock))->toBeNull();
});

it('Categorii produse: o categorie nouă se adaugă la finalul listei (după cele create din formularul de produs)', function () {
    $this->actingAs(dsbAdmin(), 'admin');
    MenuCategory::create(['name' => 'Veche', 'sort_order' => 5, 'is_active' => true]);
    Livewire::test(Categories::class)->set('newName', 'Nouă')->call('add');
    expect(MenuCategory::ordered()->pluck('name')->all())->toBe(['Veche', 'Nouă']);
});
