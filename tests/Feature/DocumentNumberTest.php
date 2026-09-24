<?php

use App\Livewire\Admin\StockReports\Form;
use App\Livewire\Admin\StockReports\Index;
use App\Livewire\Admin\StockRequisitions\Form as RequisitionForm;
use App\Livewire\Admin\StockRequisitions\Index as RequisitionsIndex;
use App\Models\Admin;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Models\StockRequisition;
use App\Services\StockReportPdfExporter;
use App\Services\StockRequisitionPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function numberAdmin(): Admin
{
    return Admin::create([
        'name' => 'Number Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

it('numbers a report like an invoice: id / date', function () {
    $report = StockReport::create(['date' => '2026-09-23', 'status' => 'draft']);

    expect($report->number())->toBe($report->id.' / 23.09.2026')
        ->and($report->title())->toBe('Raportare nr. '.$report->id.' / 23.09.2026');
});

it('numbers a requisition like an invoice: id / creation date', function () {
    $admin = numberAdmin();
    $req = StockRequisition::create(['label' => 'Necesar sâmbătă', 'status' => 'open', 'created_by' => $admin->id]);

    expect($req->number())->toBe($req->id.' / '.now()->format('d.m.Y'))
        ->and($req->title())->toBe('Necesar nr. '.$req->number())
        ->and($req->hasNumberInLabel())->toBeFalse()
        ->and($req->numberedLabel())->toBe('nr. '.$req->id.' „Necesar sâmbătă"')
        ->and($req->displayName())->toBe('Necesar nr. '.$req->number().' — Necesar sâmbătă');

    // Denumirea implicită e chiar numărul: nu îl mai repetăm.
    $req->update(['label' => StockRequisition::defaultLabel($req->id, $req->created_at)]);
    expect($req->hasNumberInLabel())->toBeTrue()
        ->and($req->numberedLabel())->toBe('nr. '.$req->number())
        ->and($req->displayName())->toBe('Necesar nr. '.$req->number());
});

it('shows the report number in the form header once the draft exists', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');

    $c = Livewire::test(Form::class)->assertSee('Raportare nouă');

    $c->set('opening_float', '10'); // prima salvare creează draftul
    $report = StockReport::first();

    $c->assertSee('Editează raportarea nr. '.$report->number());
});

it('shows the numbers in the reports list and the requisitions list', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');

    $report = StockReport::create(['date' => '2026-09-23', 'status' => 'draft', 'created_by' => $admin->id]);
    $req = StockRequisition::create(['label' => 'Necesar test', 'status' => 'open', 'created_by' => $admin->id]);

    Livewire::test(Index::class)->assertSee('Nr. '.$report->number());
    Livewire::test(RequisitionsIndex::class)->assertSee('Nr. '.$req->number());
});

it('tells which draft already holds an open sales session instead of saying there are none', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');
    $group = SalesGroup::openFor(null, $admin->id);

    Livewire::test(Form::class)->set('sales_group_id', (string) $group->id);
    $holder = StockReport::first();

    // O raportare nouă: sesiunea e luată de draftul existent -> mesaj cu numărul lui și link către el.
    Livewire::test(Form::class)
        ->assertSee('e deja aleasă în')
        ->assertSee('raportarea nr. '.$holder->number())
        ->assertSee(route('admin.stock-reports.edit', $holder))
        ->assertDontSee('Nu există sesiuni de vânzări deschise');
});

it('explains which draft holds the session when someone tries to pick it again', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');
    $group = SalesGroup::openFor(null, $admin->id);

    Livewire::test(Form::class)->set('sales_group_id', (string) $group->id);
    $holder = StockReport::first();

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->assertSet('sales_group_id', '')
        ->assertSee('deja folosită în raportarea nr. '.$holder->number());
});

it('still says there are no open sessions when there really are none', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');

    Livewire::test(Form::class)->assertSee('Nu există sesiuni de vânzări deschise');
});

function numberRequisitionSetup(): array
{
    $admin = numberAdmin();
    test()->actingAs($admin, 'admin');

    return [$admin, StockItem::create(['name' => 'Bere', 'unit' => 'buc', 'is_active' => true, 'created_by' => $admin->id])];
}

it('prefills the name of a new requisition with its number: id / date', function () {
    numberRequisitionSetup();

    Livewire::test(RequisitionForm::class)->assertSet('label', '1 / '.now()->format('d.m.Y'));
});

it('saves an untouched default name with the real number, even if the prefilled estimate was outdated', function () {
    [$admin, $beer] = numberRequisitionSetup();

    $form = Livewire::test(RequisitionForm::class);   // estimare: 1 / data

    // Între timp se creează alt necesar (ex. din alt browser): numărul real va fi 2.
    StockRequisition::create(['label' => 'Altul', 'status' => 'open', 'created_by' => $admin->id]);

    $form->set('lines.0.stock_item_id', $beer->id)->set('lines.0.qty', '5')->call('save');

    $saved = StockRequisition::where('label', '!=', 'Altul')->first();
    expect($saved->id)->toBe(2)
        ->and($saved->label)->toBe('2 / '.now()->format('d.m.Y'))
        ->and($saved->hasNumberInLabel())->toBeTrue();
});

it('keeps a custom name exactly as typed', function () {
    [, $beer] = numberRequisitionSetup();

    Livewire::test(RequisitionForm::class)
        ->set('label', 'Bere pentru sâmbătă')
        ->set('lines.0.stock_item_id', $beer->id)->set('lines.0.qty', '5')
        ->call('save');

    expect(StockRequisition::first()->label)->toBe('Bere pentru sâmbătă');
});

it('gives a duplicated requisition its own number when the original name was its number', function () {
    [$admin] = numberRequisitionSetup();
    $numbered = StockRequisition::create(['label' => 'x', 'status' => 'open', 'created_by' => $admin->id]);
    $numbered->update(['label' => StockRequisition::defaultLabel($numbered->id, $numbered->created_at)]);
    $custom = StockRequisition::create(['label' => 'Bere sâmbătă', 'status' => 'open', 'created_by' => $admin->id]);

    Livewire::test(RequisitionsIndex::class)->call('duplicate', $numbered->id)->call('duplicate', $custom->id);

    $copies = StockRequisition::whereNotIn('id', [$numbered->id, $custom->id])->orderBy('id')->get();
    expect($copies[0]->label)->toBe($copies[0]->id.' / '.now()->format('d.m.Y'))
        ->and($copies[1]->label)->toBe('Bere sâmbătă (copie)');
});

it('shows only the name of a requisition in the report import select', function () {
    [$admin] = numberRequisitionSetup();
    $req = StockRequisition::create(['label' => 'Bere sâmbătă', 'status' => 'open', 'created_by' => $admin->id]);

    Livewire::test(Form::class)
        ->assertSee('Bere sâmbătă')
        ->assertDontSee('nr. '.$req->id.' · Bere sâmbătă');
});

it('puts the number in the PDF file names', function () {
    $admin = numberAdmin();
    $this->actingAs($admin, 'admin');

    $report = StockReport::create(['date' => '2026-09-23', 'status' => 'finalized', 'finalized_at' => now(), 'created_by' => $admin->id]);
    $req = StockRequisition::create(['label' => 'Necesar test', 'status' => 'open', 'created_by' => $admin->id]);

    expect(StockReportPdfExporter::stream($report)->headers->get('content-disposition'))
        ->toContain('raportare-'.$report->id.'-2026-09-23');

    expect(StockRequisitionPdfExporter::stream($req)->headers->get('content-disposition'))
        ->toContain('necesar-'.$req->id.'-');
});
