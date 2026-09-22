<?php

use App\Livewire\Admin\StockReports\Form;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Models\StockReportLine;
use App\Models\StockRequisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function admin(): Admin
{
    return Admin::create([
        'name' => 'Test Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function stockItem(string $name, string $unit, float $qty, ?float $cost, int $adminId): StockItem
{
    $item = StockItem::create(['name' => $name, 'unit' => $unit, 'is_active' => true, 'created_by' => $adminId]);
    if ($qty > 0 || $cost !== null) {
        $item->recordInitialStock($qty, $cost, $adminId);
    }

    return $item->fresh();
}

it('autosaves a filled entry line into staging without touching real stock', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');

    $beer = stockItem('Bere', 'buc', 0, null, $admin->id);

    Livewire::test(Form::class)
        ->set('entries.0.stock_item_id', $beer->id)
        ->set('entries.0.qty', '10')
        ->set('entries.0.unit_cost', '4.5');

    // Draftul + linia de staging exista...
    $report = StockReport::first();
    expect($report)->not->toBeNull()
        ->and($report->status)->toBe('draft')
        ->and($report->lines()->count())->toBe(1);

    $line = $report->lines()->first();
    expect($line->kind)->toBe('entry')
        ->and((float) $line->qty)->toBe(10.0)
        ->and((float) $line->unit_cost)->toBe(4.5);

    // ...dar stocul real e neatins cat timp e draft.
    expect((float) $beer->fresh()->stock_qty)->toBe(0.0);
});

it('drops a staging line when the row becomes incomplete again', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');
    $beer = stockItem('Bere', 'buc', 0, null, $admin->id);

    $c = Livewire::test(Form::class)
        ->set('entries.0.stock_item_id', $beer->id)
        ->set('entries.0.qty', '10');

    expect(StockReportLine::count())->toBe(1);

    $c->set('entries.0.qty', ''); // golim cantitatea -> linia iese din staging
    expect(StockReportLine::count())->toBe(0);
});

it('finalizes a draft: creates real movements, recalculates CMP and clears staging', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');

    $vodka = stockItem('Vodcă', 'ml', 1000, 0.10, $admin->id);   // 1000 ml @ 0.10
    $juice = stockItem('Suc', 'ml', 2000, 0.02, $admin->id);     // 2000 ml @ 0.02
    $beer = stockItem('Bere', 'buc', 20, 5.0, $admin->id);       // 20 buc @ 5

    // Cocktail = 50ml vodcă + 100ml suc, pret 30.
    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);
    $cocktail->recipeLines()->create(['stock_item_id' => $juice->id, 'qty' => 100]);

    $c = Livewire::test(Form::class)
        // Intrare: +500 ml vodca @ 0.20 (va misca CMP de la 0.10 spre 0.1333)
        ->set('entries.0.stock_item_id', $vodka->id)
        ->set('entries.0.qty', '500')
        ->set('entries.0.unit_cost', '0.20')
        // Vanzare: 2 cocktailuri -> consuma 100 ml vodca + 200 ml suc
        ->set('sales.0.menu_item_id', $cocktail->id)
        ->set('sales.0.qty', '2')
        // Pierdere: 1 bere sparta
        ->set('losses.0.stock_item_id', $beer->id)
        ->set('losses.0.qty', '1')
        ->set('losses.0.note', 'sticlă spartă');

    $report = StockReport::first();
    expect($report->lines()->count())->toBe(3);

    // Finalizare cu confirmare.
    $c->set('finalizeConfirm', true)->call('finalize');

    $report->refresh();
    expect($report->status)->toBe('finalized')
        ->and($report->finalized_by)->toBe($admin->id)
        ->and($report->lines()->count())->toBe(0); // staging golit

    // Vodca: 1000@0.10 + 500@0.20 = 200 lei / 1500 = 0.1333; apoi -100 (vanzare) => 1400 ml
    $vodka->refresh();
    expect((float) $vodka->stock_qty)->toBe(1400.0)
        ->and(round((float) $vodka->avg_cost, 4))->toBe(0.1333);

    // Suc: 2000 - 200 = 1800 ml, cost neschimbat
    expect((float) $juice->fresh()->stock_qty)->toBe(1800.0);

    // Bere: 20 - 1 = 19 buc
    expect((float) $beer->fresh()->stock_qty)->toBe(19.0);

    // Venit = 2 x 30 = 60
    expect($report->totalRevenue())->toBe(60.0);
});

it('blocks finalize when a loss has no reason', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');
    $beer = stockItem('Bere', 'buc', 20, 5.0, $admin->id);

    Livewire::test(Form::class)
        ->set('losses.0.stock_item_id', $beer->id)
        ->set('losses.0.qty', '1')
        // fara motiv
        ->set('finalizeConfirm', true)
        ->call('finalize')
        ->assertHasErrors('finalize');

    expect(StockReport::first()->status)->toBe('draft');
    expect((float) $beer->fresh()->stock_qty)->toBe(20.0);
});

it('imports open requisition items as grouped entries and updates qty_received on finalize', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');
    $beer = stockItem('Bere', 'buc', 0, null, $admin->id);

    $req = StockRequisition::create(['label' => 'Necesar test', 'status' => 'open', 'created_by' => $admin->id]);
    $item = $req->items()->create(['stock_item_id' => $beer->id, 'qty_requested' => 10, 'qty_received' => 0]);

    $c = Livewire::withQueryParams(['requisition' => $req->id])->test(Form::class);

    // Preincarcat ca linie de intrare legata de necesar, cantitate = remaining (10).
    $line = StockReportLine::where('kind', 'entry')->whereNotNull('requisition_item_id')->first();
    expect($line)->not->toBeNull()
        ->and((int) $line->requisition_item_id)->toBe($item->id)
        ->and((float) $line->qty)->toBe(10.0);

    $c->set('finalizeConfirm', true)->call('finalize');

    // qty_received actualizat + necesar rezolvat.
    expect((float) $item->fresh()->qty_received)->toBe(10.0);
    expect($req->fresh()->status)->toBe('fulfilled');
    expect((float) $beer->fresh()->stock_qty)->toBe(10.0);
});

it('renders the read-only view of a finalized report (incl. entry sourced from a requisition)', function () {
    $admin = admin();
    $this->actingAs($admin, 'admin');
    $beer = stockItem('Bere', 'buc', 0, null, $admin->id);

    $req = StockRequisition::create(['label' => 'Necesar test', 'status' => 'open', 'created_by' => $admin->id]);
    $req->items()->create(['stock_item_id' => $beer->id, 'qty_requested' => 10, 'qty_received' => 0]);

    // Import din necesar -> finalizare => o mișcare 'in' cu requisition_item_id.
    Livewire::withQueryParams(['requisition' => $req->id])->test(Form::class)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first();
    expect($report->status)->toBe('finalized');

    // Randarea read-only eager-loads requisitionItem.requisition pe mișcări —
    // dacă relația lipsește pe StockMovement, aici pică cu RelationNotFoundException.
    Livewire::test(Form::class, ['report' => $report])
        ->assertOk()
        ->assertSee('Finalizat')
        ->assertSee('din necesar');
});
