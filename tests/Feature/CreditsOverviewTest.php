<?php

use App\Livewire\Admin\Credits\Index as CreditsIndex;
use App\Livewire\Admin\Dashboard;
use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Participant;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Services\CreditLedger;
use App\Services\CreditsOverview;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\SaleRecorder;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (Credite - pagina „Credite" + returnarea creditelor la anulare). Serviciul de citire
 * App\Services\CreditsOverview, pagina Livewire, meniul/dashboardul și CreditLedger::reversePayments().
 */
function covAdmin(): Admin
{
    return Admin::create([
        'name' => 'Casier Credite',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(),
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function covParty(string $name = 'Petrecere Credite'): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);

    return Party::create([
        'name' => $name,
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
        'payment_methods' => ['cash', 'card', 'credit'],
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
    ]);
}

function covCocktail(Admin $admin): MenuItem
{
    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $vodka->recordInitialStock(1000, 0.10, $admin->id);
    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);

    return $cocktail;
}

function covBuyer(Admin $admin, float $load = 100): Participant
{
    $p = ParticipantRegistry::create('Client '.random_int(1, 999999), '0722'.random_int(100000, 999999));
    CreditLedger::load($p, $load, CreditTransaction::SOURCE_MANUAL, $admin->id, 'stoc initial test');

    return $p;
}

// ---- Returnarea creditelor la anulare ------------------------------------------

it('returneaza creditele in portofel cand o vanzare de bar platita cu credite se anuleaza', function () {
    $admin = covAdmin();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $buyer = covBuyer($admin, 50);
    $group = SalesGroup::openFor(null, $admin->id);

    $sale = SaleRecorder::record($group, [['menu_item_id' => covCocktail($admin)->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participantId: $buyer->id);
    expect((float) $buyer->fresh()->credit_balance)->toBe(30.0);

    $sale->cancel($admin->id, 'Greșeală de casă');

    expect((float) $buyer->fresh()->credit_balance)->toBe(50.0);
    $tx = CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->sole();
    expect($tx->isCancelled())->toBeTrue()
        ->and($tx->cancel_reason)->toContain('Greșeală de casă')
        ->and($tx->cancelled_by)->toBe($admin->id);
    // Rândul rămâne în ledger (imuabil), doar marcat anulat.
    expect(CreditTransaction::query()->count())->toBe(2);
});

it('returneaza creditele cand o intrare platita cu credite se anuleaza', function () {
    $admin = covAdmin();
    $party = covParty();
    $buyer = covBuyer($admin, 50);

    $entries = EntryRecorder::record($party, 'Bilet', 1, [
        ['method' => 'cash', 'amount' => 10],
        ['method' => 'credit', 'amount' => 20],
    ], adminId: $admin->id, participants: [$buyer->id]);
    expect((float) $buyer->fresh()->credit_balance)->toBe(30.0);

    EntryRecorder::cancelBatch($entries->first()->batch, 'Au plecat', $admin->id);

    expect((float) $buyer->fresh()->credit_balance)->toBe(50.0)
        ->and(CreditTransaction::query()->active()->where('type', CreditTransaction::PAYMENT)->count())->toBe(0);
});

it('nu atinge portofelul cand se anuleaza o vanzare platita fara credite', function () {
    $admin = covAdmin();
    $buyer = covBuyer($admin, 50);
    $group = SalesGroup::openFor(null, $admin->id);

    $sale = SaleRecorder::record($group, [['menu_item_id' => covCocktail($admin)->id, 'qty' => 1]], [
        ['method' => 'cash', 'amount' => 30],
    ], adminId: $admin->id, participantId: $buyer->id);

    $sale->cancel($admin->id, 'Test');

    expect((float) $buyer->fresh()->credit_balance)->toBe(50.0);
});

// ---- Serviciul de citire ----------------------------------------------------------

it('calculeaza sumarul all-time si respecta identitatea incarcat - cheltuit - rambursat + ajustat = active', function () {
    $admin = covAdmin();
    $party = covParty();
    $a = covBuyer($admin, 100);   // manual +100
    $b = covBuyer($admin, 40);    // manual +40
    CreditLedger::load($b, 60, CreditTransaction::SOURCE_APP, null);                       // aplicatie +60
    CreditLedger::sell($party, $b, 25, [['method' => 'cash', 'amount' => 25]], $admin->id); // recepție +25

    $group = SalesGroup::openFor($party->id, $admin->id);
    SaleRecorder::record($group, [['menu_item_id' => covCocktail($admin)->id, 'qty' => 1]], [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, participantId: $a->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, participants: [$b->id]);

    CreditLedger::refund($a, 10, 'la cerere', $admin->id);
    CreditLedger::adjust($b, -5, 'corecție', $admin->id);

    $s = CreditsOverview::summary();

    expect($s->loaded)->toBe(225.0)
        ->and($s->spent)->toBe(60.0)
        ->and($s->spent_bar)->toBe(30.0)
        ->and($s->spent_entry)->toBe(30.0)
        ->and($s->refunded)->toBe(10.0)
        ->and($s->adjusted)->toBe(-5.0)
        ->and($s->active)->toBe(150.0)
        ->and(round($s->loaded - $s->spent - $s->refunded + $s->adjusted, 2))->toBe($s->active);

    expect(CreditsOverview::loadedBySource())->toBe([
        CreditTransaction::SOURCE_APP => 60.0,
        CreditTransaction::SOURCE_RECEPTION => 25.0,
        CreditTransaction::SOURCE_MANUAL => 140.0,
    ]);
});

it('exclude din sumar miscarile anulate', function () {
    $admin = covAdmin();
    $party = covParty();
    $p = covBuyer($admin, 10);
    $tx = CreditLedger::sell($party, $p, 40, [['method' => 'cash', 'amount' => 40]], $admin->id);

    CreditLedger::cancel($tx->id, 'Greșeală', $admin->id);

    $s = CreditsOverview::summary();
    expect($s->loaded)->toBe(10.0)
        ->and($s->active)->toBe(10.0);
});

it('ordoneaza topul soldurilor, ignora soldurile zero si calculeaza ponderea', function () {
    $admin = covAdmin();
    $small = covBuyer($admin, 20);
    $big = covBuyer($admin, 80);
    $zero = covBuyer($admin, 10);
    CreditLedger::refund($zero, 10, 'golit', $admin->id);

    $top = CreditsOverview::topBalances();

    expect($top['rows']->pluck('id')->all())->toBe([$big->id, $small->id])
        ->and($top['sum'])->toBe(100.0)
        ->and($top['share'])->toBe(100.0);
});

it('filtreaza istoricul dupa tip, sursa si petrecere (recepție, bar si intrare)', function () {
    $admin = covAdmin();
    $partyA = covParty('Petrecere A');
    $partyB = Party::create([
        'name' => 'Petrecere B', 'kind' => 'basic', 'start_date' => '2026-10-04', 'start_time' => '21:00', 'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]], 'payment_methods' => ['cash', 'credit'], 'is_free' => false,
        'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
    ]);
    $buyer = covBuyer($admin, 200);

    $sellA = CreditLedger::sell($partyA, $buyer, 20, [['method' => 'cash', 'amount' => 20]], $admin->id);
    EntryRecorder::record($partyA, 'Bilet', 1, [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, participants: [$buyer->id]);
    $groupA = SalesGroup::openFor($partyA->id, $admin->id);
    SaleRecorder::record($groupA, [['menu_item_id' => covCocktail($admin)->id, 'qty' => 1]], [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, participantId: $buyer->id);
    EntryRecorder::record($partyB, 'Bilet', 1, [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, participants: [$buyer->id]);

    expect(CreditsOverview::history('', '', (string) $partyA->id)->count())->toBe(3)   // vânzare recepție + intrare + bar
        ->and(CreditsOverview::history('', '', (string) $partyB->id)->count())->toBe(1)
        ->and(CreditsOverview::history(CreditTransaction::PAYMENT)->count())->toBe(3)
        ->and(CreditsOverview::history('', CreditTransaction::SOURCE_RECEPTION)->pluck('id')->all())->toBe([$sellA->id]);
});

// ---- Pagina, meniu, dashboard ---------------------------------------------------------

it('afiseaza pagina Credite cu sumar, solduri mari si istoric (inclusiv miscari anulate)', function () {
    $admin = covAdmin();
    $party = covParty();
    $this->actingAs($admin, 'admin');
    $p = covBuyer($admin, 10);
    $p->update(['name' => 'Ioana Portofel']);
    $tx = CreditLedger::sell($party, $p, 40, [['method' => 'cash', 'amount' => 40]], $admin->id);
    CreditLedger::cancel($tx->id, 'Bani greșiți', $admin->id);
    CreditLedger::load($p, 90, CreditTransaction::SOURCE_APP, null);

    Livewire::test(CreditsIndex::class)
        ->assertSee('Credite active')
        ->assertSee('100,00 lei')
        ->assertSee('Ioana Portofel')
        ->assertSee('anulată: Bani greșiți')
        ->assertSee('Petrecere Credite')
        ->set('filterType', CreditTransaction::PAYMENT)
        ->assertSee('Nicio mișcare de credite înregistrată pentru filtrele alese');
});

it('arata linkul Credite in meniu si cardul pe dashboard doar cat creditele sunt active sau exista miscari', function () {
    $admin = covAdmin();
    $this->actingAs($admin, 'admin');

    PaymentMethods::setActive(PaymentMethods::CREDIT, false);
    expect(CreditsOverview::visible())->toBeFalse();
    Livewire::test(Dashboard::class)->assertDontSee('Credite în circulație');

    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $p = covBuyer($admin, 75);
    expect(CreditsOverview::visible())->toBeTrue();
    Livewire::test(Dashboard::class)->assertSee('Credite în circulație')->assertSee('75,00 lei');

    $this->get(route('admin.credits.index'))->assertOk()->assertSee('Credite');
});
