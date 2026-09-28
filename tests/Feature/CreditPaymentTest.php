<?php

use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\SaleRecorder;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (Portofelul de credite - Etapa 3). Debitarea automată a creditelor la plată (bar + intrare),
 * prin App\Services\CreditLedger::pay(), apelată din SaleRecorder (bar) și EntryRecorder (intrare).
 */
function cpAdmin(): Admin
{
    return Admin::create([
        'name' => 'Casier Etapa3',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function cpParty(array $methods = ['cash', 'card', 'credit']): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);

    return Party::create([
        'name' => 'Petrecere Etapa 3',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
        'payment_methods' => $methods,
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
    ]);
}

function cpCocktail(Admin $admin): MenuItem
{
    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $vodka->recordInitialStock(1000, 0.10, $admin->id);
    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);

    return $cocktail;
}

function cpBuyer(Admin $admin, float $load = 50): \App\Models\Participant
{
    $p = ParticipantRegistry::create('Cumpărător '.random_int(1, 999999), '0722'.random_int(100000, 999999));
    CreditLedger::load($p, $load, CreditTransaction::SOURCE_MANUAL, $admin->id, 'stoc initial test');

    return $p;
}

// ---- Bar (SaleRecorder) ---------------------------------------------------

it('debiteaza automat portofelul la o vanzare de bar platita (partial) cu credite', function () {
    $admin = cpAdmin();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $cocktail = cpCocktail($admin);
    $buyer = cpBuyer($admin, 50);
    $group = SalesGroup::openFor(null, $admin->id);

    $sale = SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participantId: $buyer->id);

    expect((float) $sale->total)->toBe(30.0)
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(30.0);

    $tx = CreditTransaction::query()->where('participant_id', $buyer->id)->where('type', CreditTransaction::PAYMENT)->sole();
    expect((float) $tx->amount)->toBe(-20.0)
        ->and($tx->source)->toBe(CreditTransaction::SOURCE_BAR)
        ->and($tx->reference_type)->toBe(Sale::class)
        ->and($tx->reference_id)->toBe($sale->id);
});

it('respinge plata cu credite la bar fara participant identificat', function () {
    $admin = cpAdmin();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $cocktail = cpCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);

    expect(fn () => SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'credit', 'amount' => 30],
    ], adminId: $admin->id))->toThrow(DomainException::class, 'necesită un participant identificat');

    expect(Sale::query()->count())->toBe(0);
});

it('nu inregistreaza vanzarea de bar daca soldul de credite nu ajunge (rollback complet)', function () {
    $admin = cpAdmin();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $cocktail = cpCocktail($admin);
    $buyer = cpBuyer($admin, 5); // doar 5 lei credite
    $group = SalesGroup::openFor(null, $admin->id);

    expect(fn () => SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participantId: $buyer->id))->toThrow(DomainException::class, 'nu ajunge');

    expect(Sale::query()->count())->toBe(0)
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(5.0);
});

it('vanzarea de bar bypass (enforceMethods=false) nu impune participant pentru credite', function () {
    $admin = cpAdmin();
    $cocktail = cpCocktail($admin);
    $group = SalesGroup::openFor(null, $admin->id);

    $sale = SaleRecorder::record($group, [['menu_item_id' => $cocktail->id, 'qty' => 1]], [
        ['method' => 'credit', 'amount' => 30],
    ], adminId: $admin->id, enforceMethods: false);

    expect((float) $sale->total)->toBe(30.0)
        ->and(CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->count())->toBe(0);
});

// ---- Intrare (EntryRecorder) ----------------------------------------------

it('debiteaza automat portofelul la o intrare platita (partial) cu credite, cu un singur participant', function () {
    $admin = cpAdmin();
    $party = cpParty();
    $buyer = cpBuyer($admin, 50);

    $entries = EntryRecorder::record($party, 'Bilet', 1, [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participants: [$buyer->id]);

    expect($entries->count())->toBe(1)
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(30.0);

    $tx = CreditTransaction::query()->where('participant_id', $buyer->id)->where('type', CreditTransaction::PAYMENT)->sole();
    expect((float) $tx->amount)->toBe(-20.0)
        ->and($tx->reference_type)->toBe(PartyEntry::class)
        ->and($tx->reference_id)->toBe($entries->first()->id);
});

it('respinge plata cu credite la intrare fara niciun participant identificat', function () {
    $admin = cpAdmin();
    $party = cpParty();

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [
        ['method' => 'credit', 'amount' => 30],
    ], adminId: $admin->id))->toThrow(DomainException::class, 'necesită exact un participant identificat');

    expect(PartyEntry::query()->count())->toBe(0);
});

it('respinge plata cu credite la intrare de grup cu mai multi participanti identificati (ambiguu al cui portofel)', function () {
    $admin = cpAdmin();
    $party = cpParty();
    $a = cpBuyer($admin, 50);
    $b = cpBuyer($admin, 50);

    expect(fn () => EntryRecorder::record($party, 'Bilet', 2, [
        ['method' => 'credit', 'amount' => 60],
    ], adminId: $admin->id, participants: [$a->id, $b->id]))->toThrow(DomainException::class, 'necesită exact un participant identificat');

    expect(PartyEntry::query()->count())->toBe(0);
});

it('nu inregistreaza intrarea daca soldul de credite nu ajunge (rollback complet pe tot grupul)', function () {
    $admin = cpAdmin();
    $party = cpParty();
    $buyer = cpBuyer($admin, 5);

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participants: [$buyer->id]))->toThrow(DomainException::class, 'nu ajunge');

    expect(PartyEntry::query()->count())->toBe(0)
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(5.0);
});

it('permite o intrare de grup cu un participant identificat cu credite si restul anonimi', function () {
    $admin = cpAdmin();
    $party = cpParty();
    $buyer = cpBuyer($admin, 50);

    $entries = EntryRecorder::record($party, 'Bilet', 3, [
        ['method' => 'cash', 'amount' => 60],
        ['method' => 'credit', 'amount' => 30],
    ], adminId: $admin->id, participants: [$buyer->id]);

    expect($entries->count())->toBe(3)
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(20.0);
});
