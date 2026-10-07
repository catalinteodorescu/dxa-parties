<?php

use App\Livewire\Participant\PartyShow;
use App\Models\AdminActivityLog;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Ticket;
use App\Services\CreditLedger;
use App\Services\CreditsOverview;
use App\Services\EntryRecorder;
use App\Services\OnlineSalesReport;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 50). Bilete plătite cu credite: debit în aceeași tranzacție cu comanda, doar integral, cu confirmare în aplicație. */
function tcrParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);

    return Party::create(array_merge([
        'name' => 'Petrecere credite', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'credit'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [], 'combos' => [['buy' => 3, 'free' => 1]]]],
    ], $o));
}

function tcrUser(float $credits = 0.0, string $name = 'Ana Credite', string $phone = '0722555666'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    if ($credits > 0) {
        CreditLedger::load($p, $credits, CreditTransaction::SOURCE_MANUAL, null, 'test');
    }

    return $p->fresh();
}

it('plata cu credite: comanda „credits”, biletele create, debit în ledger legat de comandă, sold actualizat', function () {
    $party = tcrParty();
    $ana = tcrUser(120);

    $order = TicketOrders::place($ana, $party, 'Bilet', 2, null, null, null, true);

    expect($order->payment_status)->toBe(Order::PAY_CREDITS)->and($order->isPaidWithCredits())->toBeTrue()
        ->and((float) $order->total)->toBe(100.0)->and($order->tickets)->toHaveCount(2);

    $tx = CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->first();
    expect((float) $tx->amount)->toBe(-100.0)->and($tx->source)->toBe(CreditTransaction::SOURCE_APP)
        ->and($tx->reference_type)->toBe(Order::class)->and($tx->reference_id)->toBe($order->id)
        ->and($tx->note)->toBe('Bilete · Petrecere credite');
    expect((float) $ana->fresh()->credit_balance)->toBe(20.0);
});

it('biletul plătit cu credite nu are nimic de încasat la intrare', function () {
    $party = tcrParty();
    $ana = tcrUser(50);
    $order = TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true);

    $due = EntryRecorder::ticketDue($order->tickets->first());

    expect($due['due'])->toBe(0)->and($due['expired'])->toBeFalse();
});

it('sold insuficient: eroare, nicio comandă, niciun bilet, niciun debit', function () {
    $party = tcrParty();
    $ana = tcrUser(40);

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true))
        ->toThrow(DomainException::class, 'nu ajunge');

    expect(Order::count())->toBe(0)->and(Ticket::count())->toBe(0)
        ->and(CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->count())->toBe(0)
        ->and((float) $ana->fresh()->credit_balance)->toBe(40.0);
});

it('soldul se verifică pe rândul din baza de date, nu pe modelul vechi din memorie', function () {
    $party = tcrParty();
    $ana = tcrUser(60);
    $stale = Participant::find($ana->id);                      // model cu sold 60
    CreditLedger::adjust($ana, -30, 'cheltuit în altă parte');  // soldul real: 30

    expect(fn () => TicketOrders::place($stale, $party, 'Bilet', 1, null, null, null, true))->toThrow(DomainException::class, 'nu ajunge');
    expect(Order::count())->toBe(0)->and((float) $ana->fresh()->credit_balance)->toBe(30.0);
});

it('două comenzi pe același sold: a doua e refuzată', function () {
    $party = tcrParty();
    $ana = tcrUser(60);

    TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true);

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true))->toThrow(DomainException::class);
    expect(Order::count())->toBe(1)->and((float) $ana->fresh()->credit_balance)->toBe(10.0);
});

it('metoda Credite dezactivată sau neacceptată de petrecere: refuz, fără debit', function () {
    $ana = tcrUser(100);

    $noCredit = tcrParty(['payment_methods' => ['cash']]);
    expect(TicketOrders::creditsBlockReason($noCredit))->not->toBeNull();
    expect(fn () => TicketOrders::place($ana, $noCredit, 'Bilet', 1, null, null, null, true))->toThrow(DomainException::class, 'credite');

    $ok = tcrParty();
    CreditLedger::refund($ana, 100, 'golire test');
    PaymentMethods::setActive(PaymentMethods::CREDIT, false);
    expect(TicketOrders::creditsBlockReason($ok))->not->toBeNull();
    expect(Order::count())->toBe(0);
});

it('comandă gratuită (total 0) nu se plătește cu credite', function () {
    $party = tcrParty(['ticket_types' => [['name' => 'Gratis', 'price' => 0, 'discounts' => [], 'qty_tiers' => []]]]);
    $ana = tcrUser(100);

    expect(fn () => TicketOrders::place($ana, $party, 'Gratis', 1, null, null, null, true))->toThrow(DomainException::class, 'gratuită');
    expect(Order::count())->toBe(0)->and((float) $ana->fresh()->credit_balance)->toBe(100.0);
});

it('combo + cod de reducere: se debitează exact totalul comenzii', function () {
    $party = tcrParty();
    PartyDiscountCode::create(['party_id' => $party->id, 'code' => 'PROMO10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'max_uses_per_participant' => null]);
    $ana = tcrUser(500);

    $order = TicketOrders::place($ana, $party, 'Bilet', 4, 'PROMO10', null, '3+1', true);   // 3 plătite × 45 = 135; al 4-lea oferit

    expect((float) $order->total)->toBe(135.0)->and((float) $ana->fresh()->credit_balance)->toBe(365.0)
        ->and((float) CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->value('amount'))->toBe(-135.0);
});

it('fără plata cu credite, comanda rămâne ca înainte (fără debit)', function () {
    $party = tcrParty();
    $ana = tcrUser(100);

    $order = TicketOrders::place($ana, $party, 'Bilet', 1);

    expect($order->payment_status)->toBe(Order::PAY_AT_ENTRY)->and((float) $ana->fresh()->credit_balance)->toBe(100.0)
        ->and(CreditTransaction::query()->where('type', CreditTransaction::PAYMENT)->count())->toBe(0);
});

it('în aplicație: opțiunea „Plătesc cu credite” apare doar dacă soldul acoperă totalul; fără mesaj „mai trebuie”', function () {
    $party = tcrParty();
    $poor = tcrUser(30);
    $this->actingAs($poor, 'participant');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertDontSee('data-credit-option', false)->assertDontSee('mai trebuie')->assertDontSee('data-credit-confirm', false);

    $rich = tcrUser(80, 'Bogdan Bogat', '0733111222');
    $this->actingAs($rich, 'participant');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('data-credit-option', false)->assertSee('Plătesc cu credite')->assertDontSee('· sold', false)->assertDontSee('Plătește 50 lei')
        ->assertSee('data-credit-confirm', false)->assertSee('Se scad')->assertSee('Îți rămân')->assertSee('wire:click="buy"', false);
});

it('în aplicație: la 2 bilete (100 lei) cu sold 80 opțiunea dispare; confirmarea arată restul', function () {
    $party = tcrParty();
    $ana = tcrUser(80);
    $this->actingAs($ana, 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])
        ->assertSee('data-credit-option', false)->assertSee('30 lei')   // 80 − 50
        ->call('inc')
        ->assertDontSee('data-credit-option', false)->assertDontSee('data-credit-confirm', false);
});

it('în aplicație: buyWithCredits plătește, redirecționează la Bilete și scade soldul', function () {
    $party = tcrParty();
    $ana = tcrUser(80);
    $this->actingAs($ana, 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])->call('buyWithCredits')->assertRedirect(route('app.tickets'));

    expect(Order::first()->payment_status)->toBe(Order::PAY_CREDITS)->and((float) $ana->fresh()->credit_balance)->toBe(30.0);
    expect(session('status'))->toStartWith('Plătit cu credite.');

    $this->get('/bilete')->assertOk()->assertSee('achitat cu credite')->assertDontSee('de plătit la intrare');
});

it('în aplicație: buyWithCredits cu sold insuficient arată eroarea și nu creează nimic', function () {
    $party = tcrParty();
    $ana = tcrUser(20);
    $this->actingAs($ana, 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])->call('buyWithCredits')->assertNoRedirect()->assertSet('buyError', fn ($e) => str_contains($e, 'nu ajunge'));

    expect(Order::count())->toBe(0)->and((float) $ana->fresh()->credit_balance)->toBe(20.0);
});

it('portofelul participantului arată plata ca „Bilete · petrecere”, cu suma negativă', function () {
    $party = tcrParty();
    $ana = tcrUser(80);
    TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true);
    $this->actingAs($ana->fresh(), 'participant');

    $this->get('/portofel')->assertOk()->assertSee('Bilete · Petrecere credite')->assertSee('-50,00');
});

it('raportul de credite: „cheltuit” include biletele online, separat de bar și intrare; identitatea se păstrează', function () {
    $party = tcrParty();
    $ana = tcrUser(120);
    TicketOrders::place($ana, $party, 'Bilet', 2, null, null, null, true);

    $s = CreditsOverview::summary();

    expect($s->spent_tickets)->toBe(100.0)->and($s->spent_bar)->toBe(0.0)->and($s->spent_entry)->toBe(0.0)->and($s->spent)->toBe(100.0)
        ->and(round($s->loaded - $s->spent - $s->refunded + $s->adjusted, 2))->toBe($s->active);
});

it('Vânzări online: totalul plătit cu credite apare separat', function () {
    $party = tcrParty();
    $ana = tcrUser(100);
    $bob = tcrUser(0, 'Bob Fără Credite', '0744777888');
    TicketOrders::place($ana, $party, 'Bilet', 2, null, null, null, true);
    TicketOrders::place($bob, $party, 'Bilet', 1);

    $r = OnlineSalesReport::forParty($party);

    expect($r->totals->credit_orders)->toBe(1)->and($r->totals->credit_amount)->toBe(100.0)->and($r->totals->orders)->toBe(2);
});

it('jurnalul comenzii menționează plata cu credite', function () {
    $party = tcrParty();
    $ana = tcrUser(50);
    TicketOrders::place($ana, $party, 'Bilet', 1, null, null, null, true);

    expect(AdminActivityLog::query()->where('action', 'tickets.ordered')->latest('id')->value('description'))->toContain('plătit cu credite');
});
