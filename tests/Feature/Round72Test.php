<?php

use App\Livewire\Bar\Sale;
use App\Livewire\Bar\Summary as BarSummary;
use App\Livewire\Reception\QuickScan;
use App\Livewire\Reception\Summary as ReceptionSummaryPage;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Participant;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\Ticket;
use App\Services\ParticipantRegistry;
use App\Services\ReceptionScan;
use App\Services\SaleRecorder;
use App\Services\TicketOrders;
use App\Support\BarApp;
use App\Support\Permissions;
use App\Support\ReceptionApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00')));
afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 72). Feedback la scanare, contor live, Rezumat Recepție / Bar, butoane rapide și produs epuizat. */
function r72Party(string $name = 'Petrecere r72'): Party
{
    return Party::create([
        'name' => $name, 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'card', 'credit'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 0, 'qty_tiers' => [], 'discounts' => []]],
    ]);
}

function r72Admin(string $app = 'reception'): Admin
{
    return Admin::create(['name' => 'Lucrător', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => false, 'access_'.$app => true, 'password' => 'secret-pass']);
}

function r72Buyer(string $name, string $phone): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function r72Ticket(Participant $buyer, Party $party): Ticket
{
    return TicketOrders::place($buyer, $party, 'Bilet', 1, null, now())->tickets->first();
}

function r72Item(string $name, float $price): MenuItem
{
    $cat = MenuCategory::firstOrCreate(['name' => 'Băuturi'], ['is_active' => true]);

    return MenuItem::create(['menu_category_id' => $cat->id, 'name' => $name, 'price' => $price, 'is_active' => true]);
}

// ---- Recepție: feedback la scanare ----------------------------------------------------------

it('biletul deja folosit spune cine a intrat și la ce oră, cu motivul „used”', function () {
    $party = r72Party();
    $admin = r72Admin();
    $ticket = r72Ticket(r72Buyer('Ana Pop', '0722000111'), $party);

    Carbon::setTestNow(Carbon::parse('2026-10-03 21:40:00'));
    expect(ReceptionScan::resolve($party, $ticket->qrPayload(), $admin->id)['status'])->toBe(ReceptionScan::ENTERED);

    Carbon::setTestNow(Carbon::parse('2026-10-03 22:05:00'));
    $again = ReceptionScan::resolve($party, $ticket->qrPayload(), $admin->id);

    expect($again['status'])->toBe(ReceptionScan::ERROR)->and($again['reason'])->toBe(ReceptionScan::REASON_USED)
        ->and($again['message'])->toBe('Biletul a fost deja folosit de Ana Pop, la 21:40.');
});

it('biletul anulat primește motivul „cancelled”, iar alte erori „other”', function () {
    $party = r72Party();
    $admin = r72Admin();
    $ticket = r72Ticket(r72Buyer('Bob', '0733111222'), $party);
    $ticket->forceFill(['status' => Ticket::VOID])->save();

    expect(ReceptionScan::resolve($party, $ticket->qrPayload(), $admin->id)['reason'])->toBe(ReceptionScan::REASON_CANCELLED)
        ->and(ReceptionScan::resolve($party, 'cod-necunoscut', $admin->id)['reason'])->toBe(ReceptionScan::REASON_OTHER);
});

it('ecranul de scanare trimite motivul și contorul live către ecran', function () {
    $party = r72Party();
    $admin = r72Admin();
    $t1 = r72Ticket(r72Buyer('Ana', '0722000111'), $party);
    r72Ticket(r72Buyer('Bob', '0733111222'), $party);
    session([ReceptionApp::PARTY_SESSION_KEY => $party->id]);

    $c = Livewire::actingAs($admin, 'admin')->test(QuickScan::class);
    expect($c->call('counter')->get('count') ?? null)->toBeNull();   // metoda nu strică starea componentei

    $r = $c->instance()->scan($t1->qrPayload());
    expect($r['status'])->toBe('entered')->and($r['reason'])->toBeNull()->and($r['counter'])->toBe(['in' => 1, 'free' => 1]);

    $r2 = $c->instance()->scan($t1->qrPayload());
    expect($r2['reason'])->toBe('used')->and($r2['counter']['in'])->toBe(1);
});

it('ecranul scannerului are comutator de sunet, contor și funcția de feedback', function () {
    $party = r72Party();
    $admin = r72Admin();
    session([ReceptionApp::PARTY_SESSION_KEY => $party->id]);

    $this->actingAs($admin, 'admin')->get('/receptie')->assertOk()
        ->assertSee('data-scan-sound', false)->assertSee('data-scan-counter', false)
        ->assertSee('window.appFeedback', false)->assertSee('app.sound', false);
});

// ---- Recepție: Rezumat --------------------------------------------------------------------------

it('Rezumatul din Recepție arată intrările, gratuitele și încasările, doar de citit', function () {
    $party = r72Party();
    $admin = r72Admin();
    session([ReceptionApp::PARTY_SESSION_KEY => $party->id]);

    Livewire::actingAs($admin, 'admin')->test(ReceptionSummaryPage::class)->assertSee('Nicio operațiune');

    $t = r72Ticket(r72Buyer('Ana', '0722000111'), $party);
    ReceptionScan::resolve($party, $t->qrPayload(), $admin->id);

    Livewire::actingAs($admin, 'admin')->test(ReceptionSummaryPage::class)
        ->assertSee('Intrări pe tip de bilet')->assertSee('Bilet · 1')->assertSee('din care 1 gratuite')->assertSee('Încasări pe metodă');

    $this->actingAs($admin, 'admin')->get('/receptie')->assertOk()->assertSee(route('receptie.summary'), false);
    $this->actingAs($admin, 'admin')->get('/receptie/rezumat')->assertOk();
});

// ---- Bar: Rezumat -------------------------------------------------------------------------------

it('Rezumatul din Bar arată vânzările, plata cu credite și stocul consumat estimat din rețete', function () {
    $party = r72Party();
    $barman = r72Admin('bar');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);

    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $barman->id]);
    $cocktail = r72Item('Cocktail', 25);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);
    SaleRecorder::record(SalesGroup::openFor($party->id, $barman->id), [['menu_item_id' => $cocktail->id, 'qty' => 3]], [['method' => 'cash', 'amount' => 75]], source: 'app', adminId: $barman->id);

    Livewire::actingAs($barman, 'admin')->test(BarSummary::class)
        ->assertSee('Cocktail × 3')->assertSee('Vodcă')->assertSee('150 ml')->assertSee('Stoc consumat (estimat)')->assertSee('75,00 lei');
});

it('Rezumatul din Bar fără vânzări spune că nu e nimic înregistrat', function () {
    $party = r72Party();
    $barman = r72Admin('bar');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);

    Livewire::actingAs($barman, 'admin')->test(BarSummary::class)->assertSee('Nicio vânzare');
    $this->actingAs($barman, 'admin')->get('/bar')->assertOk()->assertSee(route('bar.summary'), false);
});

// ---- Bar: butoane rapide și produs epuizat ----------------------------------------------

it('butoanele rapide arată numărul din bon, iar produsul epuizat nu se poate adăuga', function () {
    $party = r72Party();
    $barman = r72Admin('bar');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);
    $cola = r72Item('Cola', 10);
    $bere = r72Item('Bere', 12);
    foreach ([$cola, $bere] as $i) {
        SaleRecorder::record(SalesGroup::openFor($party->id, $barman->id), [['menu_item_id' => $i->id, 'qty' => 2]], [['method' => 'cash', 'amount' => (float) $i->price * 2]], source: 'app', adminId: $barman->id);
    }

    $c = Livewire::actingAs($barman, 'admin')->test(Sale::class)->call('addItem', $cola->id)->call('addItem', $cola->id);
    expect($c->html())->toContain('data-quick-qty')->and($c->html())->toContain('data-quick-item="'.$cola->id.'"');

    $c->call('toggleSoldOut', $bere->id);
    expect($bere->fresh()->isSoldOutFor($party->id))->toBeTrue()
        ->and(AdminActivityLog::where('action', 'menu.sold_out')->count())->toBe(1);

    $c->call('addItem', $bere->id)->assertSet('error', 'Bere e epuizat.')->assertSet('cart', [$cola->id => 2]);
    expect($c->html())->toContain('epuizat');
});

it('produsul epuizat iese din bon, se poate demarca, iar vânzarea îl refuză pe server', function () {
    $party = r72Party();
    $barman = r72Admin('bar');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);
    $cola = r72Item('Cola', 10);

    $c = Livewire::actingAs($barman, 'admin')->test(Sale::class)->call('addItem', $cola->id);
    // marcat epuizat de pe alt telefon, între timp
    $cola->forceFill(['sold_out_party_id' => $party->id])->save();
    $c->call('payAll', 'cash')->call('sell');
    expect($c->get('error'))->toContain('Cola e epuizat')->and(App\Models\Sale::count())->toBe(0);

    $c->call('toggleSoldOut', $cola->id);
    expect($cola->fresh()->sold_out_party_id)->toBeNull();
    $c->call('addItem', $cola->id)->assertSet('error', null);
});

it('epuizat e valabil doar la petrecerea pentru care a fost marcat', function () {
    $a = r72Party('A');
    $b = r72Party('B');
    $cola = r72Item('Cola', 10);
    $cola->forceFill(['sold_out_party_id' => $a->id])->save();

    expect($cola->fresh()->isSoldOutFor($a->id))->toBeTrue()
        ->and($cola->fresh()->isSoldOutFor($b->id))->toBeFalse()
        ->and(MenuItem::soldOutIds($b->id))->toBe([])
        ->and(MenuItem::soldOutIds($a->id))->toBe([$cola->id]);
});
