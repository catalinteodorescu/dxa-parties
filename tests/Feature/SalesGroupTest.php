<?php

use App\Livewire\Admin\StockReports\Form;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Services\SaleRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function salesAdmin(): Admin
{
    return Admin::create([
        'name' => 'Sales Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Cocktail de 30 lei = 50 ml vodcă (0.10/ml) + 100 ml suc (0.02/ml); stoc: 1000 ml vodcă, 2000 ml suc. */
function salesCocktail(Admin $admin): array
{
    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $vodka->recordInitialStock(1000, 0.10, $admin->id);
    $juice = StockItem::create(['name' => 'Suc', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $juice->recordInitialStock(2000, 0.02, $admin->id);

    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);
    $cocktail->recipeLines()->create(['stock_item_id' => $juice->id, 'qty' => 100]);

    return [$cocktail, $vodka, $juice];
}

it('records a mixed-payment sale with price and cost snapshots, and rejects payments that do not add up', function () {
    $admin = salesAdmin();
    [$cocktail] = salesCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);
    \App\Support\PaymentMethods::setActive('credit', true); // creditele sunt oprite implicit (Setări > Metode de plată)

    // 30 lei = 10 cash + 5 credit + 3 tokeni x 5 lei
    $sale = SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 5],
        ['method' => 'token', 'tokens' => 3],
    ], adminId: $admin->id);

    expect((float) $sale->total)->toBe(30.0)
        ->and($sale->payments()->count())->toBe(3)
        ->and((float) $sale->lines()->first()->total_cost)->toBe(7.0); // 50*0.10 + 100*0.02

    expect(fn () => SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 25],
    ]))->toThrow(DomainException::class);
});

it('keeps one open group per party and none can take sales once closed', function () {
    $admin = salesAdmin();
    [$cocktail] = salesCocktail($admin);

    $a = SalesGroup::openFor(null, $admin->id);
    $b = SalesGroup::openFor(null, $admin->id);
    expect($a->id)->toBe($b->id);

    $a->close();

    expect(fn () => SaleRecorder::record($a, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]]))
        ->toThrow(DomainException::class);
});

it('finalizing a report with a group posts its sales (cancelled excluded), consumes stock via recipes and closes the group', function () {
    $admin = salesAdmin();
    $this->actingAs($admin, 'admin');
    [$cocktail, $vodka, $juice] = salesCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);

    SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 60]], adminId: $admin->id);
    SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'token', 'tokens' => 6]], adminId: $admin->id);
    $cancelled = SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    $cancelled->cancel($admin->id, 'greșeală');

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first();
    expect($report->status)->toBe('finalized')
        ->and($report->totalRevenue())->toBe(90.0)   // 3 cocktailuri, fara cel anulat
        ->and($report->totalCost())->toBe(21.0)
        ->and($report->sales()->first()->sales_group_id)->toBe($group->id);

    // 3 x (50 ml vodcă + 100 ml suc)
    expect((float) $vodka->fresh()->stock_qty)->toBe(850.0)
        ->and((float) $juice->fresh()->stock_qty)->toBe(1700.0);

    expect($group->fresh()->isOpen())->toBeFalse();

    // Dupa inchidere, o vanzare nu se mai poate anula (stocul e deja postat).
    $completed = $group->sales()->where('status', 'completed')->first();
    expect($completed->canBeCancelled())->toBeFalse();
});

it('does not let two drafts claim the same sales group', function () {
    $admin = salesAdmin();
    $this->actingAs($admin, 'admin');
    $group = SalesGroup::openFor(null, $admin->id);

    Livewire::test(Form::class)->set('sales_group_id', (string) $group->id);
    expect(StockReport::where('sales_group_id', $group->id)->count())->toBe(1);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->assertSet('sales_group_id', '');

    expect(StockReport::where('sales_group_id', $group->id)->count())->toBe(1);
});

it('records a simple sale without any session and lets it be cancelled until reported', function () {
    $admin = salesAdmin();
    [$cocktail] = salesCocktail($admin);

    $sale = SaleRecorder::record(null, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);

    expect($sale->sales_group_id)->toBeNull()
        ->and($sale->canBeCancelled())->toBeTrue();
});

it('posts unreported simple sales when the report includes them, and marks every sale as reported', function () {
    $admin = salesAdmin();
    $this->actingAs($admin, 'admin');
    [$cocktail, $vodka] = salesCocktail($admin);

    $loose = SaleRecorder::record(null, [['menu_item_id' => $cocktail->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 60]], adminId: $admin->id);
    $cancelledLoose = SaleRecorder::record(null, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    $cancelledLoose->cancel($admin->id, 'greșeală');

    Livewire::test(Form::class)
        ->set('include_loose_sales', true)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first();
    expect($report->status)->toBe('finalized')
        ->and($report->totalRevenue())->toBe(60.0)
        ->and((bool) $report->sales()->first()->is_loose)->toBeTrue()
        ->and((float) $vodka->fresh()->stock_qty)->toBe(900.0);

    // Si vanzarea finalizata, si cea anulata primesc report_id; nu se mai pot anula.
    expect($loose->fresh()->report_id)->toBe($report->id)
        ->and($cancelledLoose->fresh()->report_id)->toBe($report->id)
        ->and($loose->fresh()->canBeCancelled())->toBeFalse();
});

it('does not post simple sales unless the report asks for them', function () {
    $admin = salesAdmin();
    $this->actingAs($admin, 'admin');
    [$cocktail, $vodka] = salesCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);

    SaleRecorder::record(null, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    // Doar vanzarea din sesiune s-a postat.
    expect(StockReport::first()->totalRevenue())->toBe(30.0)
        ->and((float) $vodka->fresh()->stock_qty)->toBe(950.0);
});

it('lets a report close a session whose only sale was cancelled', function () {
    $admin = salesAdmin();
    $this->actingAs($admin, 'admin');
    [$cocktail, $vodka] = salesCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);

    $sale = SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    $sale->cancel($admin->id, 'greșeală');

    Livewire::test(Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('finalizeConfirm', true)
        ->call('finalize');

    $report = StockReport::first();
    expect($report->status)->toBe('finalized')
        ->and($report->totalRevenue())->toBe(0.0)
        ->and($group->fresh()->isOpen())->toBeFalse()
        ->and((float) $vodka->fresh()->stock_qty)->toBe(1000.0); // anularea compenseaza vanzarea: stoc neatins
});
