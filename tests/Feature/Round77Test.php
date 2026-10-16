<?php

use App\Livewire\Admin\Push\Index as PushIndex;
use App\Livewire\Admin\Push\StaffTypes;
use App\Livewire\Reception\Login as ReceptionLogin;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\ReceptionSession;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Services\Push\Notifier;
use App\Services\Push\PushGateway;
use App\Services\Push\StaffEvents;
use App\Services\Push\StaffNotifier;
use App\Services\TicketOrders;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 77). Notificări push pentru telefoanele din Recepție și Bar: destinatari, evenimente, dedupe, setări, abonări și popup-ul de la login. */
class R77FakeGateway implements PushGateway
{
    public array $sent = [];

    public function send(PushSubscription $subscription, string $urgency = 'normal'): int
    {
        $this->sent[] = [$subscription->id, $urgency];

        return 201;
    }
}

function r77Gateway(): R77FakeGateway
{
    config(['services.push.public_key' => 'BP5_CEc-TRX2BdLFsiS5b-WC4UnQ0scFhux5Akxe8Zt2mHSPORwoBh9s5HCJL0Lqtrbsbmglgq92TGZosMt4NfA', 'services.push.private_key' => 'Y74jOdkc-Pff3aA3Q4R3YdxbVwwot5uZJpDKQTdMEFg', 'services.push.subject' => 'mailto:test@exemplu.ro']);
    $fake = new R77FakeGateway;
    app()->instance(PushGateway::class, $fake);

    return $fake;
}

function r77Admin(array $access = [], ?array $permissions = null, string $role = 'admin'): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999), 'role' => $role, 'is_active' => true, 'password' => 'secret-pass',
        'access_admin' => in_array('admin', $access, true), 'access_bar' => in_array('bar', $access, true), 'access_reception' => in_array('reception', $access, true),
        'permissions' => $permissions ?? Permissions::legacyAdmin(),
    ]);
}

function r77Phone(Admin $a, string $app): PushSubscription
{
    return StaffNotifier::subscribe($a, $app, 'https://push.example.com/send/'.bin2hex(random_bytes(8)), 'p256', 'auth', 'UA');
}

function r77Party(array $over = []): Party
{
    return Party::create($over + [
        'name' => 'Latino Night', 'kind' => 'basic', 'start_date' => now()->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r77Participant(string $phone): Participant
{
    return Participant::create(['uuid' => (string) Str::uuid(), 'name' => 'Ioana '.$phone, 'phone' => $phone, 'password' => 'parola-sigura', 'phone_verified_at' => now()]);
}

function r77Kinds(?Admin $a = null): array
{
    return PushNotification::query()->when($a, fn ($q) => $q->where('admin_id', $a->id))->orderBy('id')->pluck('kind')->all();
}

// ---- Destinatari ------------------------------------------------------------------------------------------------------

it('nu trimite nimic fără telefon abonat în aplicația potrivită; aceeași cheie o singură dată', function () {
    r77Gateway();
    $w = r77Admin(['reception']);
    r77Phone($w, 'bar');   // abonat doar în Bar

    expect(StaffNotifier::notify($w, 'r_open', 'k1', 'T', 'B', '/receptie', 'reception'))->toBeNull();

    r77Phone($w, 'reception');
    expect(StaffNotifier::notify($w, 'r_open', 'k1', 'T', 'B', '/receptie', 'reception'))->not->toBeNull()
        ->and(StaffNotifier::notify($w, 'r_open', 'k1', 'T', 'B', '/receptie', 'reception'))->toBeNull();
});

it('lucrătorii: conturi active cu acces la aplicație', function () {
    $rec = r77Admin(['reception']);
    $bar = r77Admin(['bar']);
    $off = r77Admin(['reception']);
    $off->forceFill(['is_active' => false])->save();
    $onlyAdmin = r77Admin(['admin']);

    expect(StaffNotifier::workers('reception')->pluck('id')->all())->toBe([$rec->id])
        ->and(StaffNotifier::workers('bar')->pluck('id')->all())->toBe([$bar->id])
        ->and(StaffNotifier::workers('reception')->pluck('id')->all())->not->toContain($onlyAdmin->id);
});

// ---- Programate ---------------------------------------------------------------------------------------------------------

it('„Deschide casa / barul”: cu 2 ore înainte, o singură dată, doar fără sesiune deschisă', function () {
    r77Gateway();
    Carbon::setTestNow('2026-10-10 19:30:00');
    $party = r77Party(['start_date' => '2026-10-10', 'start_time' => '21:00']);
    $rec = r77Admin(['reception']);
    $bar = r77Admin(['bar']);
    r77Phone($rec, 'reception');
    r77Phone($bar, 'bar');

    StaffEvents::runScheduled();
    StaffEvents::runScheduled();
    expect(r77Kinds($rec))->toBe(['r_open'])->and(r77Kinds($bar))->toBe(['b_open']);

    // prea devreme: nimic
    PushNotification::query()->delete();
    Carbon::setTestNow('2026-10-10 17:00:00');
    StaffEvents::runScheduled();
    expect(r77Kinds())->toBe([]);

    // sesiunea de recepție există deja: nu mai e nevoie să deschidă
    PushNotification::query()->delete();
    Carbon::setTestNow('2026-10-10 19:30:00');
    ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => 'open']);
    $rec2 = r77Admin(['reception']);
    r77Phone($rec2, 'reception');
    StaffEvents::runScheduled();
    expect(r77Kinds($rec2))->toBe([]);
});

it('tipul oprit nu trimite', function () {
    r77Gateway();
    Carbon::setTestNow('2026-10-10 19:30:00');
    r77Party(['start_date' => '2026-10-10', 'start_time' => '21:00']);
    $rec = r77Admin(['reception']);
    r77Phone($rec, 'reception');
    StaffNotifier::setType('r_open', false);

    StaffEvents::runScheduled();

    expect(r77Kinds($rec))->toBe([]);
});

it('casă / bar nedeschis: cu 30 de minute înainte, dacă nu există nicio sesiune', function () {
    r77Gateway();
    Carbon::setTestNow('2026-10-10 20:45:00');
    $party = r77Party(['start_date' => '2026-10-10', 'start_time' => '21:00']);
    $rec = r77Admin(['reception']);
    $bar = r77Admin(['bar']);
    r77Phone($rec, 'reception');
    r77Phone($bar, 'bar');
    SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);   // barul e deschis

    StaffEvents::runScheduled();

    expect(r77Kinds($rec))->toBe(['r_not_open'])->and(r77Kinds($bar))->toBe([]);
});

it('final de petrecere: la 30 de minute după sfârșit, către cel care a deschis, doar dacă raportarea nu e depusă', function () {
    r77Gateway();
    $party = r77Party(['start_date' => '2026-10-10', 'start_time' => '21:00', 'end_time' => '03:00']);   // se termină 11.10 la 03:00
    $owner = r77Admin(['reception']);
    $other = r77Admin(['reception']);
    r77Phone($owner, 'reception');
    r77Phone($other, 'reception');
    ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => 'open', 'opened_by' => $owner->id]);

    Carbon::setTestNow('2026-10-11 03:10:00');   // prea devreme
    StaffEvents::runScheduled();
    expect(r77Kinds())->toBe([]);

    Carbon::setTestNow('2026-10-11 03:40:00');
    StaffEvents::runScheduled();
    StaffEvents::runScheduled();
    expect(r77Kinds($owner))->toBe(['r_party_end'])->and(r77Kinds($other))->toBe([]);

    // a doua zi nu se mai trimite
    PushNotification::query()->delete();
    Carbon::setTestNow('2026-10-11 12:00:00');
    StaffEvents::partyEnd(now());
    expect(r77Kinds())->toBe([]);
});

it('raportare nedepusă dimineața și sesiune uitată: o singură notificare pe sesiune, către cel care a deschis', function () {
    r77Gateway();
    Carbon::setTestNow('2026-10-11 10:00:00');
    $party = r77Party(['start_date' => '2026-10-10']);
    $owner = r77Admin(['reception']);
    $other = r77Admin(['reception']);
    r77Phone($owner, 'reception');
    r77Phone($other, 'reception');
    $s = ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => 'open', 'opened_by' => $owner->id]);
    DB::table('reception_sessions')->where('id', $s->id)->update(['created_at' => '2026-10-10 20:00:00']);

    StaffEvents::runScheduled();
    StaffEvents::runScheduled();
    expect(r77Kinds($owner))->toBe(['r_report_missing'])->and(r77Kinds($other))->toBe([]);

    Carbon::setTestNow('2026-10-11 15:00:00');
    StaffEvents::runScheduled();
    expect(r77Kinds($owner))->toBe(['r_report_missing']);   // cheie comună cu „sesiune uitată”
});

it('sesiune de bar uitată deschisă (peste 12 ore), după-amiaza', function () {
    r77Gateway();
    Carbon::setTestNow('2026-10-11 15:00:00');
    $party = r77Party(['start_date' => '2026-10-10']);
    $bar = r77Admin(['bar']);
    r77Phone($bar, 'bar');
    $g = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    DB::table('sales_groups')->where('id', $g->id)->update(['created_at' => '2026-10-10 20:00:00']);

    StaffEvents::runScheduled();

    expect(r77Kinds($bar))->toBe(['b_session_stale']);
});

it('stoc sub minim (Bar): o dată pe episod, din nou după reaprovizionare', function () {
    r77Gateway();
    $bar = r77Admin(['bar']);
    $rec = r77Admin(['reception']);
    r77Phone($bar, 'bar');
    r77Phone($rec, 'reception');
    $item = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'stock_qty' => 100, 'min_stock' => 500, 'is_active' => true, 'created_by' => $bar->id]);

    StaffEvents::runScheduled();
    StaffEvents::runScheduled();
    expect(r77Kinds($bar))->toBe(['b_low_stock'])->and(r77Kinds($rec))->toBe([]);

    $item->forceFill(['stock_qty' => 900])->save();
    StaffEvents::runScheduled();
    $item->forceFill(['stock_qty' => 50])->save();
    StaffEvents::runScheduled();
    expect(r77Kinds($bar))->toBe(['b_low_stock', 'b_low_stock']);
});

// ---- La fața locului -----------------------------------------------------------------------------------------------------

it('produs epuizat: colegii din Bar află, cel care l-a marcat nu', function () {
    r77Gateway();
    $actor = r77Admin(['bar']);
    $mate = r77Admin(['bar']);
    r77Phone($actor, 'bar');
    r77Phone($mate, 'bar');
    $party = r77Party();
    $cat = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true, 'created_by' => $actor->id]);
    $item = MenuItem::create(['menu_category_id' => $cat->id, 'name' => 'Mojito', 'price' => 25, 'is_active' => true, 'created_by' => $actor->id]);

    StaffEvents::soldOut($item, $party, $actor->name, $actor->id);

    expect(r77Kinds($mate))->toBe(['b_sold_out'])->and(r77Kinds($actor))->toBe([]);
});

it('petrecere epuizată: recepția află când se vinde ultimul bilet, nu înainte', function () {
    r77Gateway();
    $rec = r77Admin(['reception']);
    $bar = r77Admin(['bar']);
    r77Phone($rec, 'reception');
    r77Phone($bar, 'bar');
    $party = r77Party(['tickets_for_sale' => 2, 'max_tickets_per_order' => 5]);
    $ana = r77Participant('0722777001');

    TicketOrders::place($ana, $party, 'Bilet', 1);
    StaffEvents::partySeats($party);
    expect(r77Kinds())->toBe([]);

    TicketOrders::place($ana, $party, 'Bilet', 1);
    StaffEvents::partySeats($party);
    StaffEvents::partySeats($party);
    expect(r77Kinds($rec))->toBe(['r_sold_out'])->and(r77Kinds($bar))->toBe([]);
});

// ---- Setări și permisiune --------------------------------------------------------------------------------------------------

it('permisiunea există; fără ea comutatorul e refuzat', function () {
    expect(Permissions::ACTIONS)->toHaveKey('manage_staff_notifications');

    $with = r77Admin(['admin'], ['levels' => ['settings' => 1], 'actions' => ['manage_staff_notifications']]);
    $this->actingAs($with, 'admin');
    Livewire::test(StaffTypes::class, ['page' => 'bar'])->call('toggleStaffType', 'b_low_stock');
    expect(StaffNotifier::typeEnabled('b_low_stock'))->toBeFalse();

    $without = r77Admin(['admin'], ['levels' => ['settings' => 1], 'actions' => []]);
    $this->actingAs($without, 'admin');
    Livewire::test(StaffTypes::class, ['page' => 'bar'])->call('toggleStaffType', 'b_sold_out')->assertForbidden();
    expect(StaffNotifier::typeEnabled('b_sold_out'))->toBeTrue();
});

it('pagina arată doar tipurile aplicației; un tip din cealaltă aplicație nu se poate schimba', function () {
    $a = r77Admin(['admin']);
    $this->actingAs($a, 'admin');

    Livewire::test(StaffTypes::class, ['page' => 'reception'])
        ->assertSee('Deschide casa')->assertDontSee('Deschide barul')->assertDontSee('manager')
        ->call('toggleStaffType', 'b_open');
    expect(StaffNotifier::typeEnabled('b_open'))->toBeTrue();

    Livewire::test(StaffTypes::class, ['page' => 'bar'])->assertSee('Deschide barul')->assertSee('Stoc sub minim')->assertDontSee('Deschide casa');
});

// ---- Abonări și popup --------------------------------------------------------------------------------------------------------

it('abonarea: doar pentru conturi cu acces la aplicație, o dată per telefon; rămâne după logout', function () {
    r77Gateway();
    $w = r77Admin(['reception']);
    $this->actingAs($w, 'admin');
    $url = 'https://push.example.com/send/abc';

    $this->postJson('/receptie/notificari/abonare', ['endpoint' => $url, 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertOk();
    $this->postJson('/receptie/notificari/abonare', ['endpoint' => $url])->assertOk();
    expect(PushSubscription::query()->where('admin_id', $w->id)->count())->toBe(1);

    Auth::forgetGuards();
    $this->actingAs($w, 'admin');
    $this->postJson('/bar/notificari/abonare', ['endpoint' => 'https://push.example.com/send/xyz'])->assertForbidden();

    Auth::forgetGuards();
    $this->actingAs($w, 'admin');
    $this->postJson('/receptie/notificari/test')->assertOk();
    expect(r77Kinds($w))->toBe(['test']);

    // nu există rute de notificări în panoul admin
    expect(fn () => route('admin.push.subscribe'))->toThrow(RouteNotFoundException::class);
});

it('popup-ul de activare: pus la login, ascuns cu „Mai târziu”, revine la următorul login', function () {
    r77Gateway();
    $w = r77Admin(['reception']);
    $w->forceFill(['phone' => '0722123456', 'password' => 'parola-secreta'])->save();

    Livewire::test(ReceptionLogin::class)->set('phone', '0722123456')->set('password', 'parola-secreta')->call('login');
    expect(session('staff_push_prompt.reception'))->toBeTrue();

    Auth::forgetGuards();
    $this->actingAs($w, 'admin')->withSession(['staff_push_prompt.reception' => true]);
    $this->get('/receptie/petrecere')->assertOk()->assertSee('data-staff-push-prompt', false)->assertSee('Mai târziu');

    Auth::forgetGuards();
    $this->actingAs($w, 'admin')->withSession(['staff_push_prompt.reception' => true]);
    $this->postJson('/receptie/notificari/popup')->assertOk();
    expect(session()->has('staff_push_prompt.reception'))->toBeFalse();

    Auth::forgetGuards();
    $this->actingAs($w, 'admin');
    $this->get('/receptie/petrecere')->assertOk()->assertDontSee('data-staff-push-prompt', false);
});

it('service worker-ele din Recepție și Bar tratează notificările; panoul admin nu are service worker', function () {
    $this->get('/receptie/sw.js')->assertOk()->assertSee('notificationclick', false)->assertSee('/notificari/necitite', false);
    $this->get('/bar/sw.js')->assertOk()->assertSee('showNotification', false);
    $this->get('/admin/sw.js')->assertNotFound();
});

it('statisticile participanților nu includ notificările personalului', function () {
    r77Gateway();
    $w = r77Admin(['reception']);
    r77Phone($w, 'reception');
    StaffNotifier::notify($w, 'r_open', 'x', 'T', 'B', '/receptie', 'reception');
    $p = r77Participant('0722777002');
    Notifier::subscribe($p, 'https://push.example.com/send/p1', 'a', 'b', 'UA');

    $admin = r77Admin(['admin'], ['levels' => ['settings' => 1], 'actions' => ['manage_notifications']]);
    $this->actingAs($admin, 'admin');
    $html = Livewire::test(PushIndex::class)->html();

    expect($html)->not->toContain('Deschide casa');
    expect(Notifier::subscribedParticipants()->count())->toBe(1);
});

it('Setări aplicație arată câte telefoane sunt abonate și cine nu are niciunul', function () {
    r77Gateway();
    $a = r77Admin(['reception']);
    $b = r77Admin(['reception']);
    r77Phone($a, 'reception');
    r77Phone($a, 'reception');
    r77Phone($b, 'bar');   // alt aplicație: nu contează aici

    $sum = StaffNotifier::phonesSummary('reception');
    expect($sum['phones'])->toBe(2)->and($sum['accounts'])->toBe(1)->and($sum['without'])->toBe([$b->name]);

    $this->actingAs(r77Admin(['admin']), 'admin');
    Livewire::test(StaffTypes::class, ['page' => 'reception'])->assertSee('2 telefoane abonate')->assertSee($b->name);
});
