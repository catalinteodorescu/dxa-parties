<?php

use App\Models\Admin;
use App\Services\Plan\PlanGate;
use App\Services\Plan\SmsUsage;
use App\Services\Sms\CountingSmsSender;
use App\Services\Sms\SmsDeliveryException;
use App\Services\Sms\SmsoClient;
use App\Services\Sms\SmsoSmsSender;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 82). Driverul SMSO după documentația lor: tip, antet sursă, erori, reîncercare, cost real, credit, comanda de stare. */
/** Un nou Http::fake nu înlocuiește stub-urile vechi (primul care se potrivește câștigă): pornește de la un client curat. */
function r82Fake(array $stubs): void
{
    Http::swap(new HttpFactory);
    Http::fake($stubs);
}

function r82Sender(int $retryMs = 0): SmsoSmsSender
{
    return new SmsoSmsSender('cheie', '4', retryDelayMs: $retryMs);
}

it('r82: cererea poartă tipul „transactional” și antetul sursei aplicației', function () {
    r82Fake(['app.smso.ro/*' => Http::response(['status' => 200, 'responseToken' => 'u', 'transaction_cost' => 3.5], 200)]);

    r82Sender()->send('0722123456', 'Salut');

    Http::assertSent(fn (HttpRequest $r) => $r['type'] === 'transactional' && $r->header('X-Source-App') === [SmsoSmsSender::sourceApp()] && $r['sender'] === '4');
});

it('r82: costul real din răspuns (eurocenți) se adună pe luna curentă, iar un răspuns fără cost nu strică nimic', function () {
    Carbon::setTestNow('2026-10-19 10:00:00');
    r82Fake(['app.smso.ro/*' => Http::sequence()
        ->push(['status' => 200, 'transaction_cost' => 3.5], 200)
        ->push(['status' => 200, 'transaction_cost' => 3.5], 200)
        ->push(['status' => 200], 200)]);
    $sender = new CountingSmsSender(r82Sender());

    $sender->send('0722123456', 'a');
    $sender->send('0722123457', 'b');
    $sender->send('0722123458', 'c');

    $r = SmsUsage::report();
    expect($r['sent'])->toBe(3)->and($r['cost'])->toBe(0.07);
});

it('r82: 409 (prea multe pe minut) se reîncearcă și reușește; dacă rămâne 409, eșuează după 3 încercări', function () {
    r82Fake(['app.smso.ro/*' => Http::sequence()->push([], 409)->push(['status' => 200], 200)]);
    r82Sender()->send('0722123456', 'x');
    Http::assertSentCount(2);

    r82Fake(['app.smso.ro/*' => Http::response([], 409)]);
    expect(fn () => r82Sender()->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class, SmsDeliveryException::MESSAGE);
    Http::assertSentCount(3);
});

it('r82: problemele destinatarului (422 număr străin, 405 dezabonat, 403 cuvinte interzise) dau un mesaj clar, fără alertă în log', function () {
    foreach ([422, 405, 403] as $code) {
        r82Fake(['app.smso.ro/*' => Http::response(['status' => $code], $code)]);
        expect(fn () => r82Sender()->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class, SmsoSmsSender::RECIPIENT_MESSAGE);
    }
});

it('r82: 401 (cheie greșită), 402 (credit epuizat) și 400 dau mesajul generic; un „status” din JSON diferit de 200 e tot eșec', function () {
    foreach ([401, 402, 400] as $code) {
        r82Fake(['app.smso.ro/*' => Http::response(['message' => 'x'], $code)]);
        expect(fn () => (new CountingSmsSender(r82Sender()))->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class, SmsDeliveryException::MESSAGE);
    }

    r82Fake(['app.smso.ro/*' => Http::response(['status' => 402], 200)]);
    expect(fn () => r82Sender()->send('0722123456', 'x'))->toThrow(SmsDeliveryException::class);
    expect(SmsUsage::report()['sent'])->toBe(0);
});

it('r82: clientul SMSO citește creditul și expeditorii, iar creditul se ține în cache', function () {
    r82Fake([
        'app.smso.ro/api/v1/credit-check' => Http::response(['status' => 200, 'credit_value' => 4847.72], 200),
        'app.smso.ro/api/v1/senders' => Http::response([['id' => 4, 'name' => '1847', 'pricePerMessage' => 3], ['id' => 9, 'name' => 'DXA', 'pricePerMessage' => 3.5]], 200),
    ]);
    $client = new SmsoClient('cheie');

    expect($client->credit())->toBe(4847.72)
        ->and($client->senders())->toBe([['id' => 4, 'name' => '1847', 'price' => 3], ['id' => 9, 'name' => 'DXA', 'price' => 3.5]]);

    config(['sms.driver' => 'smso', 'sms.smso.key' => 'cheie']);
    Cache::forget('sms.smso.credit');
    SmsoClient::creditCached();
    SmsoClient::creditCached();
    Http::assertSentCount(3);   // 2 din clientul direct + o singură citire cache-uită

    r82Fake(['app.smso.ro/*' => Http::response([], 500)]);
    expect((new SmsoClient('cheie'))->credit())->toBeNull()->and((new SmsoClient('cheie'))->senders())->toBe([]);
    config(['sms.driver' => 'log']);
    expect(SmsoClient::creditCached())->toBeNull();
});

it('r82: pagina Proprietar arată creditul SMSO, costul real și avertizează sub prag', function () {
    $owner = Admin::create([
        'name' => 'Proprietar', 'phone' => '0700111222', 'role' => 'owner', 'is_active' => true,
        'password' => 'secret-pass-123', 'permissions' => Permissions::legacyAdmin(),
    ]);
    config(['sms.driver' => 'smso', 'sms.smso.key' => 'cheie', 'sms.smso.low_credit' => 10]);
    PlanGate::flush();
    SmsUsage::record();
    SmsUsage::addCost(7.0);

    Cache::forget('sms.smso.credit');
    r82Fake(['app.smso.ro/*' => Http::response(['status' => 200, 'credit_value' => 120.5], 200)]);
    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()
        ->assertSee('data-sms-credit', false)->assertSee('120,50')->assertSee('data-sms-cost', false)->assertSee('0,07 €')
        ->assertDontSee('alimentează contul');

    Cache::forget('sms.smso.credit');
    r82Fake(['app.smso.ro/*' => Http::response(['status' => 200, 'credit_value' => 3.2], 200)]);
    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()->assertSee('alimentează contul');
});

it('r82: comanda app:sms-status arată driverul, consumul și, pe SMSO, creditul și expeditorii', function () {
    config(['sms.driver' => 'log']);
    Artisan::call('app:sms-status');
    expect(Artisan::output())->toContain('Driver SMS: log')->toContain('laravel.log');

    config(['sms.driver' => 'smso', 'sms.smso.key' => 'cheie', 'sms.smso.sender' => '9']);
    r82Fake([
        'app.smso.ro/api/v1/credit-check' => Http::response(['credit_value' => 50], 200),
        'app.smso.ro/api/v1/senders' => Http::response([['id' => 4, 'name' => '1847', 'pricePerMessage' => 3], ['id' => 9, 'name' => 'DXA', 'pricePerMessage' => 3.5]], 200),
    ]);
    expect(Artisan::call('app:sms-status'))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('Credit SMSO')->toContain('50,00')->toContain('1847')->toContain('← folosit');

    r82Fake(['app.smso.ro/*' => Http::response([], 401)]);
    expect(Artisan::call('app:sms-status'))->toBe(1);
});
