<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\Owner\Index as OwnerIndex;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\License;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Services\DiscountCodes;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantRegistry;
use App\Services\Plan\PlanGate;
use App\Services\Plan\SmsUsage;
use App\Services\Push\Notifier;
use App\Services\Push\StaffNotifier;
use App\Services\Sms\CountingSmsSender;
use App\Services\TicketTransfers;
use App\Support\BarApp;
use App\Support\Features;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 80). Planurile în aplicații (participant, Recepție, Bar), contorul de SMS și pagina proprietarului fără excepții. */
function r80Account(string $role = 'admin', array $extra = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => true, 'password' => 'secret-pass-123',
        'permissions' => Permissions::legacyAdmin(),
    ], $extra));
}

/** Pune instalarea pe un plan; opțional schimbă ce include planul (adaugă / scoate funcții). */
function r80Plan(string $key, array $add = [], array $remove = []): void
{
    $plan = Plan::where('key', $key)->first();
    $features = array_values(array_diff(array_unique([...(array) $plan->features, ...$add]), $remove));
    $plan->update(['features' => $features]);
    License::query()->first()->update(['plan_key' => $key]);
    PlanGate::flush();
}

function r80PartyToday(): Party
{
    Carbon::setTestNow('2026-10-16 22:00:00');

    return Party::create([
        'name' => 'Seara Plan', 'kind' => 'basic', 'start_date' => '2026-10-16', 'start_time' => '21:00', 'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]], 'payment_methods' => ['cash', 'card'], 'is_free' => false,
        'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
    ]);
}

function r80Participant(): Participant
{
    $p = ParticipantRegistry::create('Ana Plan', '07'.random_int(10000000, 99999999));
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function r80Sms(): SmsSender
{
    return new class implements SmsSender
    {
        public bool $fail = false;

        public function send(string $phone, string $message): void
        {
            if ($this->fail) {
                throw new RuntimeException('furnizor căzut');
            }
        }
    };
}

// ---------- pagina proprietarului ----------

it('r80: PlanGate nu mai cunoaște excepții, iar „Activ acum” a dispărut din pagină', function () {
    $owner = r80Account('owner');
    r80Plan('basic');

    expect(PlanGate::resolve(['loyalty']))->toHaveKey('loyalty', true)->toHaveKey('credits', false);

    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()
        ->assertDontSee('Activ acum')->assertDontSee('Excepție')->assertSee('data-active-plan', false)->assertSee('plan activ');
});

// ---------- contorul de SMS ----------

it('r80: fiecare SMS trimis se numără pe luna curentă, iar un SMS eșuat nu se numără', function () {
    Carbon::setTestNow('2026-10-16 10:00:00');
    $inner = r80Sms();
    $sender = new CountingSmsSender($inner);

    $sender->send('0700000001', 'a');
    $sender->send('0700000002', 'b');
    $inner->fail = true;
    expect(fn () => $sender->send('0700000003', 'c'))->toThrow(RuntimeException::class);

    expect(DB::table('sms_usage')->where('period', '2026-10')->value('sent'))->toBe(2)
        ->and(app(SmsSender::class))->toBeInstanceOf(CountingSmsSender::class);
});

it('r80: peste cotă SMS-urile pleacă în continuare, iar depășirea se calculează la prețul din plan', function () {
    Carbon::setTestNow('2026-10-16 10:00:00');
    r80Plan('basic');
    Plan::where('key', 'basic')->update(['limits' => ['max_superadmins' => 1, 'max_admins' => 1, 'sms_included' => 3]]);
    PlanGate::flush();

    $sender = new CountingSmsSender(r80Sms());
    for ($i = 0; $i < 5; $i++) {
        $sender->send('0700000001', 'x');   // nu aruncă: cota e moale
    }

    $r = SmsUsage::report();
    expect($r['sent'])->toBe(5)->and($r['included'])->toBe(3)->and($r['overage'])->toBe(2)
        ->and($r['price'])->toBe(0.07)->and($r['amount'])->toBe(0.14);

    r80Plan('pro');   // cotă mai mare: luna curentă o folosește
    expect(SmsUsage::report()['overage'])->toBe(0);
});

it('r80: lunile trecute păstrează cota și prețul de atunci, iar o lună nouă pornește de la zero', function () {
    Carbon::setTestNow('2026-09-20 10:00:00');
    r80Plan('basic');
    Plan::where('key', 'basic')->update(['limits' => ['max_superadmins' => 1, 'max_admins' => 1, 'sms_included' => 2]]);
    PlanGate::flush();
    $sender = new CountingSmsSender(r80Sms());
    foreach (range(1, 4) as $_) {
        $sender->send('0700000001', 'x');
    }

    Carbon::setTestNow('2026-10-02 09:00:00');
    r80Plan('pro');
    expect(SmsUsage::report()['sent'])->toBe(0);
    $sender->send('0700000001', 'x');

    $hist = SmsUsage::history();
    expect($hist)->toHaveCount(2)
        ->and($hist[0])->toMatchArray(['period' => '2026-10', 'sent' => 1])
        ->and($hist[1])->toMatchArray(['period' => '2026-09', 'sent' => 4, 'included' => 2, 'overage' => 2, 'amount' => 0.14]);
});

it('r80: pagina proprietarului arată raportul de SMS și salvează prețul SMS-ului peste cotă', function () {
    Carbon::setTestNow('2026-10-16 10:00:00');
    $owner = r80Account('owner');
    r80Plan('basic');
    Plan::where('key', 'basic')->update(['limits' => ['max_superadmins' => 1, 'max_admins' => 1, 'sms_included' => 1]]);
    PlanGate::flush();
    $sender = new CountingSmsSender(r80Sms());
    $sender->send('0700000001', 'x');
    $sender->send('0700000001', 'x');
    $sender->send('0700000001', 'x');

    $this->actingAs($owner, 'admin')->get(route('admin.owner.index'))->assertOk()
        ->assertSee('data-sms-report', false)->assertSee('data-sms-sent', false)->assertSee('0,14 €');

    Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)
        ->set('plans.basic.sms_overage_eur', '0.10')->call('savePlans')->assertHasNoErrors();
    expect((float) Plan::where('key', 'basic')->value('sms_overage_eur'))->toBe(0.1);
    PlanGate::flush();
    expect(SmsUsage::report()['amount'])->toBe(0.2);

    Livewire::actingAs($owner, 'admin')->test(OwnerIndex::class)->set('plans.basic.sms_overage_eur', '')->call('savePlans')->assertHasErrors('plans.basic.sms_overage_eur');
});

// ---------- aplicațiile întregi ----------

it('r80: aplicația Recepție oprită pe plan: 503, dar manifestul și service worker-ul rămân', function () {
    r80Plan('basic', remove: ['app_reception']);

    $this->get(route('receptie.login'))->assertStatus(503)->assertSee('data-app-unavailable', false);
    $this->get(route('receptie.manifest'))->assertOk();
    $this->get(route('receptie.sw'))->assertOk();

    $this->actingAs(r80Account('admin', ['access_admin' => false, 'access_reception' => true]), 'admin')->get(route('receptie.home'))->assertStatus(503);
});

it('r80: aplicația Bar și aplicația participantului oprite pe plan: 503, cu rutele tehnice deschise', function () {
    r80Plan('basic', remove: ['app_bar', 'app_participant']);

    $this->get(route('bar.login'))->assertStatus(503);
    $this->get(route('bar.manifest'))->assertOk();
    $this->get(route('app.home'))->assertStatus(503);
    $this->get(route('app.parties'))->assertStatus(503);
    $this->get(route('app.login'))->assertStatus(503);
    $this->get(route('app.manifest'))->assertOk();
    $this->get(route('app.sw'))->assertOk();
    $this->post(route('app.webhooks.stripe'))->assertStatus(400);   // webhook-ul nu se oprește odată cu aplicația (semnătură lipsă)
});

it('r80: pe Pro toate aplicațiile merg ca înainte', function () {
    r80Plan('pro');

    $this->get(route('app.home'))->assertOk();
    $this->get(route('bar.login'))->assertOk();
    $this->get(route('receptie.login'))->assertOk();
});

// ---------- pagini din aplicații ----------

it('r80: Recepția – paginile și dalele din meniu urmează planul', function () {
    $w = r80Account('admin', ['access_admin' => false, 'access_reception' => true]);
    r80Plan('basic', remove: ['reception_tokens', 'reception_reports']);
    $party = r80PartyToday();
    $this->actingAs($w, 'admin')->withSession(['receptie.party_id' => $party->id]);

    $this->get(route('receptie.home'))->assertOk()
        ->assertSee(route('receptie.entry'), false)->assertDontSee(route('receptie.tokens'), false)
        ->assertDontSee(route('receptie.report'), false)->assertDontSee(route('receptie.summary'), false);
    $this->get(route('receptie.tokens'))->assertForbidden();
    $this->get(route('receptie.report'))->assertForbidden();
    $this->get(route('receptie.summary'))->assertForbidden();
    $this->get(route('receptie.credits'))->assertForbidden();   // creditele sunt doar în Pro

    r80Plan('basic', remove: ['reception_entries']);
    $this->get(route('receptie.entry'))->assertForbidden();
    $this->get(route('receptie.home'))->assertDontSee(route('receptie.entry'), false);
});

it('r80: Barul – vânzarea și rapoartele urmează planul', function () {
    $w = r80Account('admin', ['access_admin' => false, 'access_bar' => true]);
    r80Plan('basic', remove: ['bar_reports']);
    $party = r80PartyToday();
    $this->actingAs($w, 'admin')->withSession([BarApp::PARTY_SESSION_KEY => $party->id]);

    $this->get(route('bar.home'))->assertOk()->assertSee(route('bar.sale'), false)
        ->assertDontSee(route('bar.report'), false)->assertDontSee(route('bar.summary'), false);
    $this->get(route('bar.sale'))->assertOk();
    $this->get(route('bar.report'))->assertForbidden();

    r80Plan('basic', remove: ['bar_sales']);
    $this->get(route('bar.sale'))->assertForbidden();
    $this->get(route('bar.recent'))->assertForbidden();
});

it('r80: participantul – portofelul și încărcările dispar fără Credite, anunțurile fără Anunțuri', function () {
    $p = r80Participant();
    Announcement::create(['title' => 'Anunț plan', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published']);

    r80Plan('pro');
    $this->actingAs($p, 'participant')->get(route('app.wallet'))->assertOk();
    $this->get(route('app.announcements'))->assertOk();
    $this->get(route('app.home'))->assertOk()->assertSee('Anunț plan')->assertSee('Portofel');

    r80Plan('basic', remove: ['announcements']);
    $this->get(route('app.wallet'))->assertForbidden();
    $this->get(route('app.wallet.load'))->assertForbidden();
    $this->get(route('app.announcements'))->assertForbidden();
    $this->get(route('app.home'))->assertOk()->assertDontSee('Anunț plan')->assertDontSee('Portofel');
});

it('r80: încărcarea de credite cu cardul cere funcția „Încărcări de credite”', function () {
    $p = r80Participant();
    r80Plan('basic', add: ['credits']);   // credite da, încărcări cu cardul nu
    Settings::set('credits_purchasable', '1');

    $this->actingAs($p, 'participant')->get(route('app.wallet'))->assertOk();
    $this->get(route('app.wallet.load'))->assertForbidden();
    expect(PaymentMethods::creditsPurchasable())->toBeFalse();

    r80Plan('basic', add: ['credits', 'credit_topups']);
    $credit = PaymentMethod::where('key', 'credit')->first();
    $credit?->update(['is_active' => true]);
    expect(PaymentMethods::isEnabled(PaymentMethods::CREDIT))->toBeTrue();
});

// ---------- servicii centrale ----------

it('r80: metoda „credit” nu se mai acceptă fără funcția Credite, iar tokenii fără funcția Tokeni', function () {
    $credit = PaymentMethod::where('key', 'credit')->first();
    $credit?->update(['is_active' => true]);
    Settings::set('token_mode', PaymentMethods::TOKEN_ACTIVE);

    r80Plan('pro');
    expect(PaymentMethods::enabled())->toHaveKey('credit')->and(PaymentMethods::tokensSellable())->toBeTrue();

    r80Plan('basic', remove: ['credits', 'reception_tokens']);
    expect(PaymentMethods::enabled())->not->toHaveKey('credit')->toHaveKey('cash')
        ->and(PaymentMethods::tokensSellable())->toBeFalse();
});

it('r80: fidelitatea, codurile de reducere și trimiterea biletului urmează planul', function () {
    Settings::set('loyalty_enabled', '1');
    $party = Party::create([
        'name' => 'Plan Night', 'kind' => 'basic', 'start_date' => now()->addWeek()->toDateString(), 'start_time' => '21:00', 'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]], 'payment_methods' => ['cash', 'card'], 'is_free' => false,
        'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
    ]);
    $party->discountCodes()->create(['code' => 'PLAN10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

    r80Plan('pro');
    expect(LoyaltyLedger::enabled())->toBeTrue()->and(DiscountCodes::available())->toBeTrue()->and(DiscountCodes::find($party, 'plan10'))->not->toBeNull()
        ->and(TicketTransfers::enabled())->toBeTrue();

    r80Plan('basic', remove: ['loyalty', 'promoters', 'ticket_sending']);
    expect(LoyaltyLedger::enabled())->toBeFalse()->and(DiscountCodes::available())->toBeFalse()->and(DiscountCodes::find($party, 'plan10'))->toBeNull()
        ->and(TicketTransfers::enabled())->toBeFalse();
    expect(fn () => DiscountCodes::apply($party, 'Bilet', 30.0, 'PLAN10'))->toThrow(DomainException::class);
    expect(fn () => TicketTransfers::send(1, r80Participant(), '0744000111', r80Sms()))->toThrow(DomainException::class, 'nu este disponibilă');
});

it('r80: notificările push ale participanților și ale personalului sunt funcții separate de plan', function () {
    config(['services.push.public_key' => 'pub', 'services.push.private_key' => 'priv']);

    r80Plan('pro');
    expect(Notifier::enabled())->toBeTrue()->and(StaffNotifier::enabled())->toBeTrue();

    r80Plan('basic', remove: ['push_participants']);
    expect(Notifier::enabled())->toBeFalse()->and(StaffNotifier::enabled())->toBeTrue();

    r80Plan('basic', add: ['push_participants'], remove: ['push_staff']);
    expect(Notifier::enabled())->toBeTrue()->and(StaffNotifier::enabled())->toBeFalse();

    $this->actingAs(r80Participant(), 'participant')->postJson(route('app.push.subscribe'), ['endpoint' => 'https://x.test/e', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertOk();
    r80Plan('basic', remove: ['push_participants']);
    $this->postJson(route('app.push.subscribe'), ['endpoint' => 'https://x.test/e2', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertNotFound();
});

// ---------- admin: setările aplicațiilor ----------

it('r80: paginile de setări ale aplicațiilor sunt blocate pe plan și dispar din meniu', function () {
    $super = r80Account('superadmin');
    r80Plan('basic', remove: ['app_bar']);
    $this->actingAs($super, 'admin');

    $this->get(route('admin.settings.bar-app'))->assertForbidden()->assertSee('data-plan-locked', false);
    $this->get(route('admin.settings.reception-app'))->assertOk();
    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.settings.bar-app'), false)->assertSee(route('admin.settings.reception-app'), false);
});

it('r80: catalogul marchează „doar panoul admin” pentru funcțiile fără parte în aplicații', function () {
    expect(Features::hasAppPart('parties'))->toBeFalse()->and(Features::hasAppPart('menu'))->toBeFalse()
        ->and(Features::hasAppPart('credits'))->toBeTrue()->and(Features::hasAppPart('app_bar'))->toBeTrue();
});
