<?php

use App\Livewire\Admin\Logs\Index;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 42). Filtre pentru Jurnal activitate. */
function lgEntry(string $action, string $desc, string $actor, string $at, ?string $subject = null): AdminActivityLog
{
    $l = AdminActivityLog::create(['action' => $action, 'description' => $desc, 'actor_label' => $actor, 'subject_label' => $subject]);
    $l->forceFill(['created_at' => Carbon::parse($at)])->save();

    return $l;
}

function lgAdmin(string $role = 'superadmin'): Admin
{
    return Admin::create(['name' => 'Jurnal '.$role, 'phone' => '+40700'.random_int(100000, 999999), 'role' => $role, 'is_active' => true, 'password' => 'secret-pass']);
}

function lgDescriptions($c): array
{
    return collect($c->viewData('logs')->items())->pluck('description')->all();
}

beforeEach(function () {
    lgEntry('party.created', 'A creat petrecerea Salsa Night', 'Ana', '2026-10-01 10:00');
    lgEntry('credits.loaded', 'A încărcat 50 lei', 'Bogdan', '2026-10-02 12:00', 'Maria Pop');
    lgEntry('tickets.transferred', 'Bilet trimis 100% sigur', 'Ana', '2026-10-03 09:00');
    lgEntry('party.updated', 'A modificat Salsa Night', 'Bogdan', '2026-10-04 20:00');
});

it('fără filtre arată tot, cele mai recente primele', function () {
    $c = Livewire::actingAs(lgAdmin(), 'admin')->test(Index::class);
    expect(lgDescriptions($c))->toBe(['A modificat Salsa Night', 'Bilet trimis 100% sigur', 'A încărcat 50 lei', 'A creat petrecerea Salsa Night']);
});

it('caută în descriere, persoană, subiect și cod; % și _ sunt literale', function () {
    $t = Livewire::actingAs(lgAdmin(), 'admin')->test(Index::class);
    expect(lgDescriptions($t->set('search', 'salsa')))->toHaveCount(2);
    expect(lgDescriptions($t->set('search', 'Maria')))->toBe(['A încărcat 50 lei']);
    expect(lgDescriptions($t->set('search', 'tickets.')))->toBe(['Bilet trimis 100% sigur']);
    expect(lgDescriptions($t->set('search', '100%')))->toBe(['Bilet trimis 100% sigur']);
    expect(lgDescriptions($t->set('search', '%')))->toBe(['Bilet trimis 100% sigur']);
    expect(lgDescriptions($t->set('search', '_')))->toBe([]);
});

it('filtrează după categorie, persoană și interval (zilele limită incluse)', function () {
    $t = Livewire::actingAs(lgAdmin(), 'admin')->test(Index::class);
    expect(lgDescriptions($t->set('category', 'party')))->toHaveCount(2);
    expect(lgDescriptions($t->set('category', 'tickets')))->toBe(['Bilet trimis 100% sigur']);
    $t->set('category', '');
    expect(lgDescriptions($t->set('actor', 'Ana')))->toHaveCount(2);
    $t->set('actor', '');
    expect(lgDescriptions($t->set('dateFrom', '2026-10-02')->set('dateTo', '2026-10-03')))->toBe(['Bilet trimis 100% sigur', 'A încărcat 50 lei']);
    expect(lgDescriptions($t->set('dateFrom', '2026-10-04')->set('dateTo', '')))->toBe(['A modificat Salsa Night']);
    expect(lgDescriptions($t->set('dateFrom', 'nu-e-data')->set('dateTo', '')))->toHaveCount(4);   // dată invalidă = ignorată
});

it('filtrele se combină, apar în opțiuni cu etichete și se resetează', function () {
    $t = Livewire::actingAs(lgAdmin(), 'admin')->test(Index::class);
    $t->set('category', 'party')->set('actor', 'Bogdan');
    expect(lgDescriptions($t))->toBe(['A modificat Salsa Night']);

    $opts = $t->viewData('categoryOptions');
    expect($opts['party'])->toBe('Petreceri')->and($opts['tickets'])->toBe('Bilete')->and($opts[''])->toBe('Toate categoriile');
    expect(array_keys($t->viewData('actorOptions')))->toBe(['', 'Ana', 'Bogdan']);

    $t->call('clearFilters');
    expect(lgDescriptions($t))->toHaveCount(4)->and($t->get('category'))->toBe('');
});

it('doar superadminul vede jurnalul', function () {
    Livewire::actingAs(lgAdmin('admin'), 'admin')->test(Index::class)->assertForbidden();
});

it('intervalul de date e un singur câmp (calendar propriu), legat de cele două proprietăți', function () {
    $t = Livewire::actingAs(lgAdmin(), 'admin')->test(Index::class);
    $t->assertSeeHtml('data-date-range')->assertSeeHtml('dateFrom')->assertSeeHtml('dateTo')->assertDontSeeHtml('type="date"');
});
