<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\ParticipantApp\NotificationTypes;
use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\Sale;
use App\Services\CreditLedger;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantRegistry;
use App\Services\Push\Events;
use App\Services\Push\Notifier;
use App\Services\Push\PushGateway;
use App\Services\Push\Vapid;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 70d). Bilete luate de alții (prag), card complet, plată cu credite, bilet cumpărat / primit. */
class R70dFakeGateway implements PushGateway
{
    public int $sent = 0;

    public function send(PushSubscription $subscription, string $urgency = 'normal'): int
    {
        $this->sent++;

        return 201;
    }
}

function r70dSetup(): void
{
    try {
        $keys = Vapid::generate();
    } catch (RuntimeException) {
        $keys = ['public' => 'BP5_CEc-TRX2BdLFsiS5b-WC4UnQ0scFhux5Akxe8Zt2mHSPORwoBh9s5HCJL0Lqtrbsbmglgq92TGZosMt4NfA', 'private' => 'Y74jOdkc-Pff3aA3Q4R3YdxbVwwot5uZJpDKQTdMEFg'];
    }
    config(['services.push.public_key' => $keys['public'], 'services.push.private_key' => $keys['private'], 'services.push.subject' => 'mailto:test@exemplu.ro']);
    app()->instance(PushGateway::class, new R70dFakeGateway);
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
}

function r70dUser(string $name, string $phone, bool $subscribed = true): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    if ($subscribed) {
        Notifier::subscribe($p, 'https://push.example.com/send/'.bin2hex(random_bytes(8)));
    }

    return $p->fresh();
}

function r70dParty(array $over = []): Party
{
    return Party::create($over + [
        'name' => 'Latino Night', 'kind' => 'basic', 'start_date' => '2026-10-10', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r70dAdmin(array $levels, array $actions = []): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => $actions],
    ]);
}

function r70dGot(Participant $p, string $kind)
{
    return PushNotification::query()->where('participant_id', $p->id)->where('kind', $kind)->get();
}

// ---- Tipurile și pragul din Setări ------------------------------------------------------------------------------------

it('tipurile noi au exemplu, „bilete luate de alții” e oprit implicit și are paranteza cu orele liniștite', function () {
    foreach (['buyers', 'ticket_new', 'credit_payment', 'credit_load', 'card_ready'] as $k) {
        expect(Notifier::TYPES)->toHaveKey($k)->and(Notifier::TYPES[$k][2])->toHaveCount(2);
    }
    expect(Notifier::DEFAULT_OFF)->toBe(['buyers'])
        ->and(Notifier::typeEnabled('buyers'))->toBeFalse()
        ->and(Notifier::typeEnabled('credit_payment'))->toBeTrue()
        ->and(Notifier::TYPES['buyers'][1])->toMatch('/\(.*22:00.*09:00.*\)/u')
        ->and(Notifier::TYPES['party_new'][1])->toContain('Ai intrare gratuită');
});

it('„bilete luate de alții” nu se poate porni fără prag; cu prag se pornește; prag invalid dă eroare; pragul șters oprește tipul', function () {
    $c = Livewire::actingAs(r70dAdmin(['settings' => 2], ['manage_notifications']), 'admin')->test(NotificationTypes::class);
    $c->assertSeeHtml('data-buyers-threshold')->call('toggleType', 'buyers')->assertSeeHtml('data-buyers-error')->assertSee('Completează mai întâi pragul');
    expect(Notifier::typeEnabled('buyers'))->toBeFalse();

    $c->set('buyersThreshold', 'abc')->call('saveThreshold')->assertSeeHtml('data-buyers-error');
    expect(Notifier::buyersThreshold())->toBeNull();

    $c->set('buyersThreshold', '20')->call('saveThreshold')->assertDontSeeHtml('data-buyers-error');
    expect(Notifier::buyersThreshold())->toBe(20);
    $c->call('toggleType', 'buyers')->assertDontSeeHtml('data-buyers-error');
    expect(Notifier::typeEnabled('buyers'))->toBeTrue();

    $c->set('buyersThreshold', '')->call('saveThreshold');
    expect(Notifier::buyersThreshold())->toBeNull()->and(Notifier::typeEnabled('buyers'))->toBeFalse();
});

it('pragul și comutatorul cer bifa „Notificări”', function () {
    Livewire::actingAs(r70dAdmin(['settings' => 2]), 'admin')->test(NotificationTypes::class)->set('buyersThreshold', '5')->call('saveThreshold')->assertForbidden();
    expect(Notifier::buyersThreshold())->toBeNull();
});

// ---- Bilete luate de alții ---------------------------------------------------------------------------------------------

it('când X persoane au cumpărat, cei fără bilet (chiar fără favorite) află o singură dată; cei cu bilet nu', function () {
    r70dSetup();
    Notifier::setBuyersThreshold(2);
    Notifier::setType('buyers', true);
    $party = r70dParty();
    $ana = r70dUser('Ana', '0722710001');
    $bob = r70dUser('Bob', '0722710002');
    $cip = r70dUser('Cip', '0722710003');   // nu cumpără, nu are petrecerea la favorite
    $dan = r70dUser('Dan', '0722710004', false);   // fără abonare

    TicketOrders::place($ana, $party, 'Bilet', 1);
    expect(r70dGot($cip, 'buyers'))->toHaveCount(0);   // sub prag

    TicketOrders::place($bob, $party, 'Bilet', 1);
    $n = r70dGot($cip, 'buyers');
    expect($n)->toHaveCount(1)
        ->and($n->first()->body)->toContain('Latino Night')->toContain('2 persoane')
        ->and(r70dGot($ana, 'buyers'))->toHaveCount(0)->and(r70dGot($bob, 'buyers'))->toHaveCount(0)
        ->and(r70dGot($dan, 'buyers'))->toHaveCount(0);

    Events::runScheduled(now());
    expect(r70dGot($cip, 'buyers'))->toHaveCount(1);   // o singură dată
});

it('„bilete luate de alții”: nu pleacă în orele liniștite, cu tipul oprit sau fără prag', function () {
    r70dSetup();
    $party = r70dParty();
    $ana = r70dUser('Ana', '0722710011');
    $bob = r70dUser('Bob', '0722710012');
    $cip = r70dUser('Cip', '0722710013');
    TicketOrders::place($ana, $party, 'Bilet', 1);
    TicketOrders::place($bob, $party, 'Bilet', 1);

    expect(Events::buyers(now(), null, null, 2))->toBe(0);   // tip oprit (implicit)
    Notifier::setType('buyers', true);
    expect(Events::buyers(now()))->toBe(0);   // fără prag
    Notifier::setBuyersThreshold(2);
    expect(Events::buyers(Carbon::parse('2026-10-01 23:30:00')))->toBe(0);   // oră liniștită
    expect(Events::buyers(Carbon::parse('2026-10-01 10:00:00')))->toBe(1);   // Cip
});

// ---- Bilet cumpărat / primit ----------------------------------------------------------------------------------------------

it('cumpărătorul primește notificare instant; la rezervarea cu cardul încă neplătită, nu', function () {
    r70dSetup();
    $party = r70dParty();
    $ana = r70dUser('Ana', '0722710021');
    $bob = r70dUser('Bob', '0722710022');

    $order = TicketOrders::place($ana, $party, 'Bilet', 2);
    $n = r70dGot($ana, 'ticket_new');
    expect($n)->toHaveCount(1)->and($n->first()->title)->toBe('Bilete cumpărate')->and($n->first()->body)->toContain('Latino Night')->toContain('2 bilete')->toContain('100,00 lei');

    TicketOrders::place($bob, $party, 'Bilet', 1);
    expect(r70dGot($bob, 'ticket_new')->first()->title)->toBe('Bilet cumpărat');
    expect(Order::query()->count())->toBe(2)->and($order->fresh())->not->toBeNull();
});

it('biletul trimis către un cont cu notificări îl anunță pe destinatar', function () {
    r70dSetup();
    $party = r70dParty();
    $ana = r70dUser('Ana Sender', '0722710031');
    $bob = r70dUser('Bob', '0722710032');
    $order = TicketOrders::place($ana, $party, 'Bilet', 1);

    TicketTransfers::send($order->tickets[0]->id, $ana, $bob->phone, app(SmsSender::class));

    $n = r70dGot($bob, 'ticket_new');
    expect($n)->toHaveCount(1)->and($n->first()->title)->toBe('Ai primit un bilet')->and($n->first()->body)->toContain('Ana Sender')->toContain('Latino Night');
});

it('tipul „bilet cumpărat sau primit” oprit nu trimite nimic', function () {
    r70dSetup();
    Notifier::setType('ticket_new', false);
    $ana = r70dUser('Ana', '0722710041');
    TicketOrders::place($ana, r70dParty(), 'Bilet', 1);
    expect(r70dGot($ana, 'ticket_new'))->toHaveCount(0);
});

// ---- Plata cu credite -----------------------------------------------------------------------------------------------------

it('plata cu credite la Bar sau Recepție trimite notificare cu soldul; biletele cumpărate cu credite în aplicație nu dublează', function () {
    r70dSetup();
    $ana = r70dUser('Ana', '0722710051');
    CreditLedger::load($ana, 200, CreditTransaction::SOURCE_MANUAL, null, 'test');

    CreditLedger::pay($ana, 35, Sale::class, 1);
    CreditLedger::pay($ana, 15, PartyEntry::class, 2);
    CreditLedger::pay($ana, 10, Order::class, 3);   // bilete: fără notificare de credite

    $n = r70dGot($ana, 'credit_payment')->sortBy('id')->values();
    expect($n)->toHaveCount(2)
        ->and($n[0]->body)->toBe('Ai plătit 35,00 lei cu credite la Bar. Sold: 165,00 lei.')
        ->and($n[1]->body)->toContain('la Recepție')->toContain('Sold: 150,00 lei');
});

it('plata cu credite într-o tranzacție pleacă doar după comitere', function () {
    r70dSetup();
    $ana = r70dUser('Ana', '0722710052');
    CreditLedger::load($ana, 100, CreditTransaction::SOURCE_MANUAL, null, 'test');

    try {
        DB::transaction(function () use ($ana) {
            CreditLedger::pay($ana, 20, Sale::class, 5);
            throw new RuntimeException('anulat');
        });
    } catch (RuntimeException) {
    }

    expect(r70dGot($ana, 'credit_payment'))->toHaveCount(0);
});

// ---- Card de fidelitate ---------------------------------------------------------------------------------------------------

it('la a X-a ștampilă primești „Ai intrare gratuită”, o singură dată; la petrecerea nouă mesajul spune că ai intrare gratuită', function () {
    r70dSetup();
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 3);
    $ana = r70dUser('Ana', '0722710061');
    $bob = r70dUser('Bob', '0722710062');
    LoyaltyLedger::enroll($ana, null);
    LoyaltyLedger::enroll($bob, null);

    LoyaltyLedger::adjustStamps($ana, 2, 'test');
    expect(r70dGot($ana, 'card_ready'))->toHaveCount(0);
    LoyaltyLedger::adjustStamps($ana, 1, 'test');
    $n = r70dGot($ana, 'card_ready');
    expect($n)->toHaveCount(1)->and($n->first()->title)->toBe('Ai intrare gratuită');
    LoyaltyLedger::adjustStamps($ana, 1, 'test');   // cercul gratuit: nu se mai anunță
    expect(r70dGot($ana, 'card_ready'))->toHaveCount(1);

    LoyaltyLedger::adjustStamps($bob, 1, 'test');
    expect(r70dGot($bob, 'card_ready'))->toHaveCount(0);
});

it('petrecere nouă: „Ai intrare gratuită” doar pentru cei cu card complet și doar la petrecere cu fidelitate', function () {
    r70dSetup();
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 2);
    $ana = r70dUser('Ana', '0722710071');
    $bob = r70dUser('Bob', '0722710072');
    LoyaltyLedger::enroll($ana, null);
    LoyaltyLedger::adjustStamps($ana, 2, 'test');
    Notifier::setType('card_ready', false);   // ca să izolăm notificarea de petrecere nouă

    r70dParty(['name' => 'Cu fidelitate', 'loyalty_eligible' => true]);
    r70dParty(['name' => 'Fără fidelitate', 'loyalty_eligible' => false, 'start_date' => '2026-10-11']);
    Events::partyNew(now());

    $bodies = fn (Participant $p) => PushNotification::query()->where('participant_id', $p->id)->where('kind', 'party_new')->pluck('body', 'dedupe_key');
    $ana_ = $bodies($ana)->values()->all();
    expect(collect($ana_)->filter(fn ($b) => str_contains($b, 'Cu fidelitate'))->first())->toContain('Ai intrare gratuită');
    expect(collect($ana_)->filter(fn ($b) => str_contains($b, 'Fără fidelitate'))->first())->not->toContain('Ai intrare gratuită');
    expect($bodies($bob)->implode(' '))->not->toContain('Ai intrare gratuită');
});

it('încărcarea de credite (cu bonus, la Recepție, manual) trimite notificare cu soldul', function () {
    r70dSetup();
    $ana = r70dUser('Ana', '0722710081');

    CreditLedger::load($ana, 110, CreditTransaction::SOURCE_RECEPTION, null, null, null, null, null, 10);
    CreditLedger::load($ana, 20, CreditTransaction::SOURCE_APP);
    CreditLedger::load($ana, 5, CreditTransaction::SOURCE_MANUAL, null, 'corecție');

    $n = r70dGot($ana, 'credit_load')->sortBy('id')->values();
    expect($n)->toHaveCount(3)
        ->and($n[0]->body)->toBe('Ai încărcat 110,00 lei credite la Recepție (din care bonus 10,00 lei). Sold: 110,00 lei.')
        ->and($n[1]->body)->toBe('Ai încărcat 20,00 lei credite. Sold: 130,00 lei.')
        ->and($n[2]->body)->toContain('de către echipă')->toContain('Sold: 135,00 lei');
});
