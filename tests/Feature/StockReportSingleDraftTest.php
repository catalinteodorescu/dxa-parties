<?php

use App\Livewire\Admin\StockReports\Form;
use App\Livewire\Admin\StockReports\Index;
use App\Models\Admin;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Models\StockRequisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function draftAdmin(): Admin
{
    return Admin::create([
        'name' => 'Draft Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function draftParty(array $overrides = []): Party
{
    return Party::create(array_merge([
        'name' => 'Petrecere draft',
        'kind' => 'basic',
        'start_date' => '2026-09-25',
        'start_time' => '20:00',
        'end_time' => '23:59',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 25]],
    ], $overrides));
}

it('regula globala: nu se creeaza un al doilea draft cat timp unul e neterminat, indiferent de petrecere', function () {
    $admin = draftAdmin();
    $this->actingAs($admin, 'admin');

    Livewire::test(Form::class)->set('opening_float', '10');
    expect(StockReport::count())->toBe(1);
    $first = StockReport::first();

    Livewire::test(Form::class)
        ->assertSee('Ai deja un draft deschis')
        ->assertSee('raportarea nr. '.$first->number())
        ->assertSee(route('admin.stock-reports.edit', $first));

    expect(StockReport::count())->toBe(1);
});

it('regula globala previne dublarea intrarii dintr-un necesar prin 2 drafturi paralele (fostul bug)', function () {
    $admin = draftAdmin();
    $this->actingAs($admin, 'admin');
    $beer = StockItem::create(['name' => 'Bere', 'unit' => 'buc', 'is_active' => true, 'created_by' => $admin->id]);

    $req = StockRequisition::create(['label' => 'Necesar test', 'status' => 'open', 'created_by' => $admin->id]);
    $item = $req->items()->create(['stock_item_id' => $beer->id, 'qty_requested' => 10, 'qty_received' => 0]);

    // Primul "Raporteaza din acest necesar" creeaza draftul si aduce tot restul (10).
    Livewire::withQueryParams(['requisition' => $req->id])->test(Form::class);
    expect(StockReport::count())->toBe(1);

    // Al doilea click (alta fila, acelasi necesar) NU mai creeaza un al doilea draft.
    $second = Livewire::withQueryParams(['requisition' => $req->id])->test(Form::class);
    expect(StockReport::count())->toBe(1);
    $second->assertSee('Ai deja un draft deschis');

    // Deci o singura linie de intrare din necesar, nu doua — nicio dublare posibila la finalizare.
    $report = StockReport::first();
    expect($report->lines()->where('kind', 'entry')->count())->toBe(1)
        ->and((float) $item->fresh()->qty_received)->toBe(0.0); // inca nefinalizat
});

it('cardul "Sesiuni deschise": Adu in raportare preselecteaza petrecerea si sesiunea cand nu exista alt draft', function () {
    $admin = draftAdmin();
    $this->actingAs($admin, 'admin');
    $party = draftParty();
    $group = SalesGroup::openFor($party->id, $admin->id);

    Livewire::withQueryParams(['party' => $party->id, 'sales_group' => $group->id])->test(Form::class);

    $report = StockReport::first();
    expect($report)->not->toBeNull()
        ->and($report->party_id)->toBe($party->id)
        ->and($report->sales_group_id)->toBe($group->id);
});

it('lista de raportari arata sesiunile deschise, cu Continua raportarea pentru cea prinsa de draftul curent', function () {
    $admin = draftAdmin();
    $this->actingAs($admin, 'admin');
    $party = draftParty();
    $group = SalesGroup::openFor($party->id, $admin->id);
    SalesGroup::openFor(null, $admin->id); // "fara petrecere", inca neprinsa de niciun draft

    Livewire::withQueryParams(['party' => $party->id, 'sales_group' => $group->id])->test(Form::class);
    $report = StockReport::first();

    Livewire::test(Index::class)
        ->assertSee('Sesiuni deschise')
        ->assertSee($party->name)
        ->assertSee('Continuă raportarea')
        ->assertSee(route('admin.stock-reports.edit', $report))
        ->assertSee('Fără petrecere')
        ->assertSee('Adu în raportare');
});
