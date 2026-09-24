<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\StockReports\Form;
use App\Livewire\Admin\StockReports\Index;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockReportCount;
use App\Services\SaleRecorder;
use App\Services\StockReportPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function closingAdmin(): Admin
{
    return Admin::create([
        'name' => 'Closing Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/**
 * Cocktail de 30 lei = 50 ml vodcă (0.10/ml) + 100 ml suc (0.02/ml); stoc: 1000 ml vodcă, 2000 ml suc,
 * 500 ml rom (0.20/ml, nefolosit în rețetă). Sesiune cu 3 cocktailuri vândute:
 * 2 plătite cash (60 lei) + 1 plătit cu 6 tokeni (30 lei) => stoc teoretic după raportare: vodcă 850, suc 1700, rom 500.
 *
 * @return array{0: MenuItem, 1: StockItem, 2: StockItem, 3: StockItem, 4: SalesGroup}
 */
function closingSetup(Admin $admin): array
{
    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $vodka->recordInitialStock(1000, 0.10, $admin->id);
    $juice = StockItem::create(['name' => 'Suc', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $juice->recordInitialStock(2000, 0.02, $admin->id);
    $rum = StockItem::create(['name' => 'Rom', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $rum->recordInitialStock(500, 0.20, $admin->id);

    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);
    $cocktail->recipeLines()->create(['stock_item_id' => $juice->id, 'qty' => 100]);

    $group = SalesGroup::openFor(null, $admin->id);
    SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 60]], adminId: $admin->id);
    SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'token', 'tokens' => 6]], adminId: $admin->id);

    return [$cocktail, $vodka, $juice, $rum, $group];
}

it('autosaves the closing count (float, cash, tokens, inventory sheet) into the draft without touching stock', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('opening_float', '50')
        ->set('counted_cash', '108.5')
        ->set('counted_tokens', '5')
        ->set('counts.'.$vodka->id, '800');

    $report = StockReport::first();
    expect($report)->not->toBeNull()
        ->and($report->status)->toBe('draft')
        ->and((float) $report->opening_float)->toBe(50.0)
        ->and((float) $report->counted_cash)->toBe(108.5)
        ->and($report->counted_tokens)->toBe(5)
        ->and($report->counts()->count())->toBe(1)
        ->and((float) $report->counts()->first()->counted_qty)->toBe(800.0);

    // Numărătoarea e doar salvată: stocul real rămâne neatins cât timp e draft.
    expect((float) $vodka->fresh()->stock_qty)->toBe(1000.0);
});

it('removes a counted product when its field is cleared, and rejects invalid values without saving them', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka] = closingSetup($admin);

    $c = Livewire::test(Form::class)
        ->set('counted_cash', '100')
        ->set('counts.'.$vodka->id, '800');

    expect(StockReportCount::count())->toBe(1);

    $c->set('counts.'.$vodka->id, '');
    expect(StockReportCount::count())->toBe(0);

    $c->set('counts.'.$vodka->id, '-3')->assertHasErrors('counts.'.$vodka->id);
    expect(StockReportCount::count())->toBe(0);

    $c->set('counted_cash', '-5')->assertHasErrors('counted_cash');
    expect((float) StockReport::first()->counted_cash)->toBe(100.0); // valoarea bună rămâne
});

it('shows the expected vs counted comparison live while the report is a draft', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka, , , $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('opening_float', '50')
        ->set('counted_cash', '108')
        ->set('counted_tokens', '5')
        ->set('counts.'.$vodka->id, '800')
        ->assertSee('Numărătoare de final de seară')
        ->assertSee('lipsă 2,00 lei')      // cash: 50 fond + 60 încasat = 110 așteptat, 108 numărat
        ->assertSee('lipsă 1 tk')          // tokeni: 6 așteptați, 5 numărați
        ->assertSee('lipsă 50 ml');        // vodcă: 850 așteptat după raportare, 800 numărat
});

it('finalizing snapshots expected values and aligns stock: shortage becomes a loss, surplus an adjustment', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka, $juice, $rum, $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('opening_float', '50')
        ->set('counted_cash', '108')
        ->set('counted_tokens', '5')
        ->set('counts.'.$vodka->id, '800')   // așteptat 850 => -50
        ->set('counts.'.$juice->id, '1700')  // așteptat 1700 => corect
        ->set('counts.'.$rum->id, '520')     // așteptat 500 => +20
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first()->fresh();
    expect($report->status)->toBe('finalized')
        ->and((float) $report->expected_cash)->toBe(110.0)
        ->and($report->expected_tokens)->toBe(6)
        ->and($report->stock_aligned)->toBeTrue();

    $summary = $report->closingSummary();
    expect($summary->cash_diff)->toBe(-2.0)
        ->and($summary->tokens_diff)->toBe(-1)
        ->and($summary->counted_items)->toBe(3)
        ->and($summary->diff_items)->toBe(2)
        ->and($summary->inventory_value)->toBe(-1.0)  // -50 x 0.10 + 20 x 0.20
        ->and($summary->clean)->toBeFalse();

    // Snapshot per produs.
    $vodkaCount = $report->counts()->where('stock_item_id', $vodka->id)->first();
    expect((float) $vodkaCount->expected_qty)->toBe(850.0)
        ->and((float) $vodkaCount->diff_qty)->toBe(-50.0)
        ->and((float) $vodkaCount->unit_cost)->toBe(0.1);

    // Stocul e aliniat la numărat.
    expect((float) $vodka->fresh()->stock_qty)->toBe(800.0)
        ->and((float) $juice->fresh()->stock_qty)->toBe(1700.0)
        ->and((float) $rum->fresh()->stock_qty)->toBe(520.0);

    // Lipsa e o pierdere cu motiv, deci intră în cost și în profit: 21 (vânzări) + 5 (50 ml x 0.10).
    $loss = $report->losses()->where('stock_item_id', $vodka->id)->first();
    expect($loss)->not->toBeNull()
        ->and($loss->note)->toBe('Diferență la numărătoare')
        ->and($report->totalCost())->toBe(26.0)
        ->and($report->totalProfit())->toBe(64.0); // 90 venit - 26 cost

    // Surplusul e o ajustare legată de raportare, nu o pierdere.
    $adjustment = StockMovement::where('stock_item_id', $rum->id)->where('type', 'adjustment')->where('report_id', $report->id)->first();
    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->qty)->toBe(20.0)
        ->and($report->losses()->where('stock_item_id', $rum->id)->exists())->toBeFalse();
});

it('keeps the theoretical stock when the alignment box is unticked, but still saves the differences', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka, , , $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('counted_cash', '60')
        ->set('counts.'.$vodka->id, '800')
        ->set('alignStock', false)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first()->fresh();
    expect($report->status)->toBe('finalized')
        ->and($report->stock_aligned)->toBeFalse()
        ->and((float) $vodka->fresh()->stock_qty)->toBe(850.0)   // rămâne cel teoretic
        ->and((float) $report->counts()->first()->diff_qty)->toBe(-50.0)
        ->and($report->totalCost())->toBe(21.0)                  // fără pierdere adăugată
        ->and($report->closingSummary()->cash_diff)->toBe(0.0)   // fără fond: 60 = 60
        ->and($report->closingSummary()->tokens_diff)->toBeNull(); // tokeni nenumărați => fără diferență
});

it('lets a report be finalized with only a count, and reports a clean closing when everything matches', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, , $juice] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('counts.'.$juice->id, '2000')  // nicio vânzare postată => așteptat = stocul curent
        ->set('finalizeConfirm', true)
        ->call('finalize')
        ->assertHasNoErrors();

    $report = StockReport::first()->fresh();
    expect($report->status)->toBe('finalized')
        ->and($report->closingSummary()->clean)->toBeTrue()
        ->and($report->stock_aligned)->toBeTrue()
        ->and(StockMovement::where('report_id', $report->id)->count())->toBe(0); // nicio mișcare de aliniere
});

it('still refuses to finalize a completely empty report', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');

    Livewire::test(Form::class)
        ->set('finalizeConfirm', true)
        ->call('finalize')
        ->assertHasErrors('finalize');

    expect(StockReport::where('status', 'finalized')->count())->toBe(0);
});

it('leaves a report without any count untouched: no summary and no expected snapshot', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, , , , $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first()->fresh();
    expect($report->hasClosingCount())->toBeFalse()
        ->and($report->closingSummary())->toBeNull()
        ->and($report->expected_cash)->toBeNull()
        ->and($report->stock_aligned)->toBeFalse();
});

it('renders the read-only closing summary of a finalized report', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka, , , $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('opening_float', '50')
        ->set('counted_cash', '108')
        ->set('counts.'.$vodka->id, '800')
        ->set('finalizeConfirm', true)
        ->call('finalize');

    Livewire::test(Form::class, ['report' => StockReport::first()])
        ->assertOk()
        ->assertSee('Numărătoare de final de seară')
        ->assertSee('cu diferențe')
        ->assertSee('stoc aliniat la numărat')
        ->assertSee('lipsă 2,00 lei');
});

it('deletes the count together with a draft report', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    [, $vodka] = closingSetup($admin);

    Livewire::test(Form::class)->set('counts.'.$vodka->id, '800');
    $report = StockReport::first();
    expect(StockReportCount::count())->toBe(1);

    Livewire::test(Index::class)->call('delete', $report->id);

    expect(StockReport::count())->toBe(0)
        ->and(StockReportCount::count())->toBe(0);
});

/** Finalizează o raportare cu diferențe (cash -2 lei, vodcă -50 ml) și o întoarce. */
function closingFinalizedWithDifferences(Admin $admin, $test): StockReport
{
    [, $vodka, , , $group] = closingSetup($admin);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('opening_float', '50')
        ->set('counted_cash', '108')
        ->set('counts.'.$vodka->id, '800')
        ->set('finalizeConfirm', true)
        ->call('finalize');

    return StockReport::first();
}

it('flags a report with count differences in the reports list', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    closingFinalizedWithDifferences($admin, $this);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('diferențe la numărătoare');
});

it('shows the last count on the dashboard', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');

    Livewire::test(Dashboard::class)->assertSee('Nicio numărătoare încă');

    closingFinalizedWithDifferences($admin, $this);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Ultima numărătoare')
        ->assertDontSee('Nicio numărătoare încă')
        ->assertSee('−2,00 lei');
});

it('exports the closing count into the report PDF', function () {
    $admin = closingAdmin();
    $this->actingAs($admin, 'admin');
    $report = closingFinalizedWithDifferences($admin, $this);

    $response = StockReportPdfExporter::stream($report);

    ob_start();
    $response->sendContent();
    $pdf = ob_get_clean();

    expect(substr($pdf, 0, 4))->toBe('%PDF');
});
