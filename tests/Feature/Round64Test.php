<?php

use App\Livewire\Participant\Topup;
use App\Livewire\Participant\TopupPay;
use App\Models\CreditTopup;
use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Services\CreditLedger;
use App\Services\CreditTopups;
use App\Services\ParticipantRegistry;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PlaceholderGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Payments\StripeSignature;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 64). Stripe etapa 1: încărcarea de credite cu cardul (Checkout găzduit + webhook semnat). */
function r64On(): void
{
    config([
        'services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test',
        'services.stripe.currency' => 'ron', 'app.dxa_tickets_auto_paid' => false,
    ]);
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);
    Settings::set('credit_bonus_tiers', json_encode([['min' => 100, 'percent' => 10]]));
}

function r64Tx(): int
{
    return CreditTransaction::count();
}

function r64User(): Participant
{
    $p = ParticipantRegistry::create('Ana Stripe', '0722111333');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p->fresh();
}

/** Trimite un eveniment Stripe semnat corect (sau cu antet dat). */
function r64Webhook(array $event, ?string $header = null, string $secret = 'whsec_test')
{
    $payload = json_encode($event);
    $t = time();
    $header ??= 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret);

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $header,
    ], $payload);
}

function r64Event(string $type, CreditTopup $topup, array $over = []): array
{
    return ['id' => 'evt_1', 'type' => $type, 'data' => ['object' => array_merge([
        'id' => 'cs_test_1', 'client_reference_id' => $topup->uuid, 'payment_status' => 'paid',
        'amount_total' => (int) round($topup->toPay() * 100), 'currency' => 'ron', 'payment_intent' => 'pi_123',
    ], $over)]];
}

it('gateway: Stripe doar cu STRIPE_SECRET, altfel locul rezervat', function () {
    config(['services.stripe.secret' => null]);
    expect(app(PaymentGateway::class))->toBeInstanceOf(PlaceholderGateway::class)->and(app(PaymentGateway::class)->online())->toBeFalse();

    config(['services.stripe.secret' => 'sk_test_x']);
    expect(app(PaymentGateway::class))->toBeInstanceOf(StripeGateway::class)->and(app(PaymentGateway::class)->online())->toBeTrue();
});

it('semnătura: acceptă una corectă, respinge greșită, veche sau lipsă', function () {
    $body = '{"a":1}';
    $t = 1_700_000_000;
    $sig = hash_hmac('sha256', $t.'.'.$body, 'whsec_x');

    expect(StripeSignature::verify($body, "t=$t,v1=$sig", 'whsec_x', 300, $t + 10))->toBeTrue()
        ->and(StripeSignature::verify($body, "t=$t,v1=zzz,v1=$sig", 'whsec_x', 300, $t))->toBeTrue()
        ->and(StripeSignature::verify($body.' ', "t=$t,v1=$sig", 'whsec_x', 300, $t))->toBeFalse()
        ->and(StripeSignature::verify($body, "t=$t,v1=$sig", 'altul', 300, $t))->toBeFalse()
        ->and(StripeSignature::verify($body, "t=$t,v1=$sig", 'whsec_x', 300, $t + 301))->toBeFalse()
        ->and(StripeSignature::verify($body, null, 'whsec_x'))->toBeFalse()
        ->and(StripeSignature::verify($body, "t=$t,v1=$sig", '', 300, $t))->toBeFalse();
});

it('confirm: creează sesiunea Stripe cu suma exactă (comision 0) și duce la adresa Stripe', function () {
    r64On();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
    $me = r64User();

    Livewire::actingAs($me, 'participant')->test(Topup::class)
        ->set('amount', '120')->call('confirm')
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $topup = CreditTopup::firstOrFail();
    expect($topup->status)->toBe('pending')->and((float) $topup->fee)->toBe(0.0)
        ->and($topup->provider)->toBe('stripe')->and($topup->provider_ref)->toBe('cs_test_1');

    Http::assertSent(function ($r) use ($topup) {
        $d = $r->data();

        return $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $r->hasHeader('Authorization', 'Bearer sk_test_x')
            && $d['client_reference_id'] === $topup->uuid
            && $d['line_items'][0]['price_data']['unit_amount'] === 12000
            && $d['line_items'][0]['price_data']['currency'] === 'ron'
            && str_ends_with($d['success_url'], '?plata=ok')
            && $d['mode'] === 'payment';
    });
    expect(r64Tx())->toBe(0);   // nimic creditat înainte de webhook
});

it('confirm: dacă Stripe refuză, arată eroare și nu pierde încărcarea', function () {
    r64On();
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'x']], 400)]);

    Livewire::actingAs(r64User(), 'participant')->test(Topup::class)
        ->set('amount', '50')->call('confirm')
        ->assertSet('error', 'Plata cu cardul nu poate fi pornită acum. Încearcă din nou.')
        ->assertNoRedirect();
});

it('webhook: semnătură invalidă → 400 și nimic creditat', function () {
    r64On();
    $topup = CreditTopups::start(r64User(), 120);

    r64Webhook(r64Event('checkout.session.completed', $topup), 't='.time().',v1=gresit')->assertStatus(400);
    r64Webhook(r64Event('checkout.session.completed', $topup), null, 'alt-secret')->assertStatus(400);
    expect($topup->fresh()->status)->toBe('pending')->and(r64Tx())->toBe(0);
});

it('webhook: plata reușită creditează suma + bonus, o singură dată (idempotent)', function () {
    r64On();
    $me = r64User();
    $topup = CreditTopups::start($me, 120);   // bonus 10% = 12

    r64Webhook(r64Event('checkout.session.completed', $topup))->assertOk();
    r64Webhook(r64Event('checkout.session.completed', $topup))->assertOk();
    r64Webhook(r64Event('checkout.session.async_payment_succeeded', $topup))->assertOk();

    $topup->refresh();
    expect($topup->status)->toBe('paid')->and($topup->provider)->toBe('stripe')->and($topup->provider_ref)->toBe('pi_123')
        ->and(r64Tx())->toBe(1);
    expect(round((float) CreditLedger::balance($me->fresh()), 2))->toBe(132.0);
});

it('webhook: payment_status ≠ paid nu creditează; suma sau moneda greșite nu creditează', function () {
    r64On();
    $topup = CreditTopups::start(r64User(), 120);

    r64Webhook(r64Event('checkout.session.completed', $topup, ['payment_status' => 'unpaid']))->assertOk();
    r64Webhook(r64Event('checkout.session.completed', $topup, ['amount_total' => 100]))->assertOk();
    r64Webhook(r64Event('checkout.session.completed', $topup, ['currency' => 'eur']))->assertOk();

    expect($topup->fresh()->status)->toBe('pending')->and(r64Tx())->toBe(0);
});

it('webhook: eșuat și expirat; evenimente necunoscute se confirmă fără efect', function () {
    r64On();
    $me = r64User();
    $a = CreditTopups::start($me, 120);
    $a->update(['provider' => 'stripe', 'provider_ref' => 'cs_test_1']);
    r64Webhook(r64Event('checkout.session.async_payment_failed', $a))->assertOk();
    expect($a->fresh()->status)->toBe('failed');

    $b = CreditTopups::start($me, 60);
    $b->update(['provider' => 'stripe', 'provider_ref' => 'cs_test_1']);
    r64Webhook(r64Event('checkout.session.expired', $b, ['id' => 'cs_veche']))->assertOk();   // sesiune veche: ignorată
    expect($b->fresh()->status)->toBe('pending');
    r64Webhook(r64Event('checkout.session.expired', $b))->assertOk();
    expect($b->fresh()->status)->toBe('cancelled');

    r64Webhook(['id' => 'evt_2', 'type' => 'charge.refunded', 'data' => ['object' => []]])->assertOk();
    r64Webhook(['id' => 'evt_3', 'type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => 'nu-exista']]])->assertOk();
    expect(r64Tx())->toBe(0);
});

it('webhook: plata târzie pe o încărcare anulată/expirată tot creditează (banii au fost luați)', function () {
    r64On();
    $topup = CreditTopups::start(r64User(), 120);
    CreditTopups::cancel($topup);

    r64Webhook(r64Event('checkout.session.completed', $topup))->assertOk();

    expect($topup->fresh()->status)->toBe('paid')->and(r64Tx())->toBe(1);
});

it('pagina de plată: buton „Plătește cu cardul”, apoi așteptare după întoarcere și redirect când e plătit', function () {
    r64On();
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2'])]);
    $me = r64User();
    $topup = CreditTopups::start($me, 120);

    Livewire::actingAs($me, 'participant')->test(TopupPay::class, ['topup' => $topup])
        ->assertSee('data-topup-pay', false)->assertDontSee('data-topup-soon', false)
        ->call('pay')->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_2');

    // Întoarcerea: încă neplătit → așteptare cu poll; după webhook → Portofel.
    $c = Livewire::withQueryParams(['plata' => 'ok'])->actingAs($me, 'participant')->test(TopupPay::class, ['topup' => $topup->fresh()])
        ->assertSee('data-topup-confirming', false)->assertDontSee('data-topup-pay', false);
    r64Webhook(r64Event('checkout.session.completed', $topup->fresh()))->assertOk();
    $c->call('check')->assertRedirect(route('app.wallet'));
});

it('pagina de plată fără Stripe rămâne locul rezervat', function () {
    r64On();
    config(['services.stripe.secret' => null]);
    $me = r64User();
    $topup = CreditTopups::start($me, 120);

    Livewire::actingAs($me, 'participant')->test(TopupPay::class, ['topup' => $topup])
        ->assertSee('data-topup-soon', false)->assertDontSee('data-topup-pay', false);
});

it('pagina de plată a altcuiva → 404', function () {
    r64On();
    $topup = CreditTopups::start(r64User(), 120);
    $other = ParticipantRegistry::create('Altcineva', '0722000111');
    $other->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    Livewire::actingAs($other->fresh(), 'participant')->test(TopupPay::class, ['topup' => $topup])->assertNotFound();
});
