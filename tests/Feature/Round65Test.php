<?php

use App\Livewire\Participant\PartyShow;
use App\Livewire\Participant\TicketPay;
use App\Livewire\Participant\Tickets;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Services\CreditLedger;
use App\Services\OnlineSalesReport;
use App\Services\ParticipantRegistry;
use App\Services\PartyAttendees;
use App\Services\ReceptionScan;
use App\Services\TicketOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 65). Biletele plătite cu cardul (Stripe etapa 2): rezervare `pending`, activare din webhook, plată târzie. */
function r65Stripe(): void
{
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.currency' => 'ron']);
}

function r65Party(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere card', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'limit' => 3, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function r65User(string $name = 'Ana Card', string $phone = '0722555111'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p->fresh();
}

function r65Webhook(array $event)
{
    $payload = json_encode($event);
    $t = time();

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test'),
    ], $payload);
}

function r65Event(string $type, Order $order, array $over = []): array
{
    return ['id' => 'evt_o', 'type' => $type, 'data' => ['object' => array_merge([
        'id' => 'cs_order_1', 'client_reference_id' => 'order_'.$order->uuid, 'payment_status' => 'paid',
        'amount_total' => (int) round((float) $order->total * 100), 'currency' => 'ron', 'payment_intent' => 'pi_o1',
    ], $over)]];
}

/** Comandă cu cardul, cu sesiunea Stripe „curentă” deja legată (cs_order_1). */
function r65Card(Participant $p, Party $party, int $n = 2): Order
{
    $order = TicketOrders::place($p, $party, 'Bilet', $n, null, null, null, false, true);
    $order->update(['payment_ref' => 'cs_order_1']);

    return $order->fresh();
}

it('cu cardul: biletele sunt rezervate pending, cu termen, și ocupă loc din stoc', function () {
    $party = r65Party();
    $ana = r65User();

    $order = r65Card($ana, $party, 2);

    expect($order->payment_status)->toBe('card_pending')->and($order->payment_provider)->toBe('stripe')
        ->and($order->payment_expires_at->equalTo(now()->addMinutes(35)))->toBeTrue()
        ->and($order->tickets->pluck('status')->unique()->all())->toBe(['pending'])
        ->and(TicketOrders::available($party, 'Bilet'))->toBe(1);   // limita 3, 2 rezervate
});

it('o comandă gratuită cu cardul nu trece prin Stripe (merge ca până acum)', function () {
    $party = r65Party(['ticket_types' => [['name' => 'Gratis', 'price' => 0, 'discounts' => [], 'qty_tiers' => []]]]);

    $order = TicketOrders::place(r65User(), $party, 'Gratis', 1, null, null, null, false, true);

    expect($order->payment_status)->toBe('at_entry')->and($order->tickets->first()->status)->toBe('valid');
});

it('biletele pending nu apar în Bilete, nu intră la Recepție și nu se numără ca vândute în statistici', function () {
    $party = r65Party();
    $ana = r65User();
    $order = r65Card($ana, $party, 2);
    $ticket = $order->tickets->first();

    Livewire::actingAs($ana, 'participant')->test(Tickets::class)->assertSee('Nu ai bilete valabile');

    expect(Ticket::findByQrPayload($ticket->qrPayload())->isValid())->toBeFalse();
    expect(OnlineSalesReport::forParty($party)->totals->tickets)->toBe(0)
        ->and(OnlineSalesReport::forParty($party)->totals->pending)->toBe(2)
        ->and(PartyAttendees::for($party)->rows)->toBeEmpty();
});

it('rezervarea expirată eliberează locurile și marchează comanda', function () {
    $party = r65Party();
    $order = r65Card(r65User(), $party, 3);
    expect(TicketOrders::available($party, 'Bilet'))->toBe(0);

    Carbon::setTestNow(now()->addMinutes(36));
    expect(TicketOrders::expireStalePending())->toBe(1);

    expect($order->fresh()->payment_status)->toBe('card_expired')->and($order->tickets()->where('status', 'void')->count())->toBe(3)
        ->and(TicketOrders::available($party, 'Bilet'))->toBe(3);
});

it('webhook: plata activează biletele (idempotent) și comanda devine „card”', function () {
    r65Stripe();
    $party = r65Party();
    $ana = r65User();
    $order = r65Card($ana, $party, 2);

    r65Webhook(r65Event('checkout.session.completed', $order))->assertOk();
    r65Webhook(r65Event('checkout.session.completed', $order))->assertOk();
    r65Webhook(r65Event('checkout.session.async_payment_succeeded', $order))->assertOk();

    $order->refresh();
    expect($order->payment_status)->toBe('card')->and($order->payment_ref)->toBe('pi_o1')->and($order->paid_at)->not->toBeNull()
        ->and($order->tickets->pluck('status')->unique()->all())->toBe(['valid'])
        ->and(CreditTransaction::count())->toBe(0);
    Livewire::actingAs($ana, 'participant')->test(Tickets::class)->assertSee('achitat cu cardul');
    expect(OnlineSalesReport::forParty($party)->totals->card_orders)->toBe(1);
});

it('webhook: semnătură greșită, sumă greșită sau status ≠ paid nu activează nimic', function () {
    r65Stripe();
    $order = r65Card(r65User(), r65Party(), 2);

    test()->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=gresit'], json_encode(r65Event('checkout.session.completed', $order)))->assertStatus(400);
    r65Webhook(r65Event('checkout.session.completed', $order, ['amount_total' => 100]))->assertOk();
    r65Webhook(r65Event('checkout.session.completed', $order, ['currency' => 'eur']))->assertOk();
    r65Webhook(r65Event('checkout.session.completed', $order, ['payment_status' => 'unpaid']))->assertOk();

    expect($order->fresh()->payment_status)->toBe('card_pending')->and($order->tickets()->where('status', 'pending')->count())->toBe(2);
});

it('webhook: eșuat sau expirat eliberează biletele, dar doar pentru sesiunea curentă', function () {
    r65Stripe();
    $party = r65Party();
    $order = r65Card(r65User(), $party, 2);

    r65Webhook(r65Event('checkout.session.expired', $order, ['id' => 'cs_veche']))->assertOk();   // sesiune veche: ignorată
    expect($order->fresh()->payment_status)->toBe('card_pending');

    r65Webhook(r65Event('checkout.session.expired', $order))->assertOk();
    expect($order->fresh()->payment_status)->toBe('card_expired')->and(TicketOrders::available($party, 'Bilet'))->toBe(3);
});

it('plată târzie: dacă locurile mai sunt libere, biletele se reactivează', function () {
    r65Stripe();
    $party = r65Party();
    $order = r65Card(r65User(), $party, 2);
    Carbon::setTestNow(now()->addMinutes(40));
    TicketOrders::expireStalePending();

    r65Webhook(r65Event('checkout.session.completed', $order))->assertOk();

    expect($order->fresh()->payment_status)->toBe('card')->and($order->tickets()->where('status', 'valid')->count())->toBe(2)
        ->and(CreditTransaction::count())->toBe(0);
});

it('plată târzie fără locuri libere: suma intră în portofel ca credite, o singură dată', function () {
    r65Stripe();
    $party = r65Party();
    $ana = r65User();
    $order = r65Card($ana, $party, 2);          // 100 lei, limita 3
    Carbon::setTestNow(now()->addMinutes(40));
    TicketOrders::expireStalePending();
    TicketOrders::place(r65User('Bob', '0722555222'), $party, 'Bilet', 3);   // altcineva ia toate locurile

    r65Webhook(r65Event('checkout.session.completed', $order))->assertOk();
    r65Webhook(r65Event('checkout.session.completed', $order))->assertOk();

    expect($order->fresh()->payment_status)->toBe('card_credited')->and($order->tickets()->where('status', 'void')->count())->toBe(2)
        ->and(CreditTransaction::count())->toBe(1)
        ->and(round(CreditLedger::balance($ana->fresh()), 2))->toBe(100.0);
});

it('„Confirmă” cu Stripe activ rezervă biletele și duce la Stripe; fără Stripe rămâne comandă de plătit la intrare', function () {
    r65Stripe();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_new', 'url' => 'https://checkout.stripe.com/c/pay/cs_new'])]);
    $party = r65Party();
    $ana = r65User();

    Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $party->id])
        ->call('selectTicket', 'Bilet')->set('qty', 2)->call('buy')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_new');

    $order = Order::firstOrFail();
    expect($order->payment_status)->toBe('card_pending')->and($order->payment_ref)->toBe('cs_new')->and((float) $order->total)->toBe(100.0);
    Http::assertSent(fn ($r) => $r->data()['client_reference_id'] === 'order_'.$order->uuid && $r->data()['line_items'][0]['price_data']['unit_amount'] === 10000);

    config(['services.stripe.secret' => null]);
    Order::query()->delete();
    Ticket::query()->delete();
    Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $party->id])
        ->call('selectTicket', 'Bilet')->call('buy')->assertRedirect(route('app.tickets'));
    expect(Order::firstOrFail()->payment_status)->toBe('at_entry');
});

it('dacă Stripe refuză, rezervarea se eliberează și apare eroare', function () {
    r65Stripe();
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'x']], 400)]);
    $party = r65Party();

    Livewire::actingAs(r65User(), 'participant')->test(PartyShow::class, ['party' => $party->id])
        ->call('selectTicket', 'Bilet')->call('buy')
        ->assertSet('buyError', 'Plata cu cardul nu poate fi pornită acum. Încearcă din nou.')->assertNoRedirect();

    expect(Order::firstOrFail()->payment_status)->toBe('card_failed')->and(TicketOrders::available($party, 'Bilet'))->toBe(3);
});

it('pagina de întoarcere: așteaptă webhook-ul, apoi duce la Bilete; „Renunță” eliberează locurile', function () {
    r65Stripe();
    $party = r65Party();
    $ana = r65User();
    $order = r65Card($ana, $party, 2);

    $c = Livewire::withQueryParams(['plata' => 'ok'])->actingAs($ana, 'participant')->test(TicketPay::class, ['order' => $order])
        ->assertSee('data-order-confirming', false)->assertDontSee('data-order-pay', false);
    r65Webhook(r65Event('checkout.session.completed', $order->fresh()))->assertOk();
    $c->call('check')->assertRedirect(route('app.tickets'));

    $second = r65Card($ana, $party, 1);
    Livewire::withQueryParams([])->actingAs($ana, 'participant')->test(TicketPay::class, ['order' => $second])
        ->assertSee('data-order-pay', false)->call('cancel')->assertRedirect(route('app.tickets'));
    expect($second->fresh()->payment_status)->toBe('card_failed');
});

it('pagina de plată a altcuiva → 404', function () {
    $order = r65Card(r65User(), r65Party(), 1);

    Livewire::actingAs(r65User('Altcineva', '0722555999'), 'participant')->test(TicketPay::class, ['order' => $order])->assertNotFound();
});

it('scanner: biletul neplătit e refuzat cu motiv clar', function () {
    $order = r65Card(r65User(), r65Party(), 1);
    $t = $order->tickets->first();
    $party = $t->party;

    $r = ReceptionScan::resolve($party, $t->qrPayload(), null);

    expect($r['status'])->toBe(ReceptionScan::ERROR)->and($r['message'])->toContain('nu e plătit încă');
});
