<?php

use App\Livewire\Admin\Settings\PaymentMethods as PaymentMethodsPanel;
use App\Livewire\Participant\Topup;
use App\Livewire\Participant\TopupPay;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\CreditTopup;
use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\Party;
use App\Services\CreditBonus;
use App\Services\CreditLedger;
use App\Services\CreditsOverview;
use App\Services\CreditTopups;
use App\Services\ParticipantRegistry;
use App\Services\ReceptionSummary;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 51). Încărcarea de credite din aplicație până la pagina de plată + bonusul la prag (online și la Recepție). */
function ctbOn(array $tiers = [['min' => 100, 'percent' => 10], ['min' => 200, 'percent' => 15]]): void
{
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);
    Settings::set('credit_bonus_tiers', $tiers ? json_encode($tiers) : '');
}

function ctbUser(string $name = 'Ana Încarcă', string $phone = '0722888999'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p->fresh();
}

function ctbAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Setări', 'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass',
    ]);
}

// ---- Bonus ------------------------------------------------------------------

it('bonus: fără praguri nu există; se aplică cel mai mare prag atins, cu rotunjire la 2 zecimale', function () {
    ctbOn([]);
    expect(CreditBonus::bonusFor(500))->toBe(0.0)->and(CreditBonus::tiers())->toBe([]);

    ctbOn();
    expect(CreditBonus::bonusFor(99.99))->toBe(0.0)
        ->and(CreditBonus::bonusFor(100))->toBe(10.0)
        ->and(CreditBonus::bonusFor(150))->toBe(15.0)
        ->and(CreditBonus::bonusFor(200))->toBe(30.0)        // 15% din 200, nu 10% + 15%
        ->and(CreditBonus::bonusFor(333.33))->toBe(50.0)     // 15% din 333,33 = 49,9995 → 50,00
        ->and(CreditBonus::percentFor(120))->toBe(10.0);
});

it('bonus: următorul prag pentru îndemnul „încă X lei”', function () {
    ctbOn();

    expect(CreditBonus::nextTier(40))->toBe(['min' => 100.0, 'percent' => 10.0])
        ->and(CreditBonus::nextTier(100))->toBe(['min' => 200.0, 'percent' => 15.0])
        ->and(CreditBonus::nextTier(200))->toBeNull();
});

it('pragurile se salvează sortate, cu validare (min > 0, 0 < % ≤ 100, fără duplicate, rânduri goale ignorate) și jurnal', function () {
    $this->actingAs(ctbAdmin(), 'admin');

    CreditBonus::saveTiers([['min' => '200', 'percent' => '15'], ['min' => '', 'percent' => ''], ['min' => '100', 'percent' => '10,5']]);
    expect(CreditBonus::tiers())->toBe([['min' => 100.0, 'percent' => 10.5], ['min' => 200.0, 'percent' => 15.0]]);
    expect(AdminActivityLog::pluck('action')->all())->toContain('credits.bonus_tiers_updated');

    foreach ([
        [['min' => '0', 'percent' => '10']],
        [['min' => '100', 'percent' => '0']],
        [['min' => '100', 'percent' => '101']],
        [['min' => 'abc', 'percent' => '10']],
        [['min' => '100', 'percent' => '10'], ['min' => '100', 'percent' => '20']],
    ] as $bad) {
        expect(fn () => CreditBonus::saveTiers($bad))->toThrow(DomainException::class);
    }
    expect(CreditBonus::tiers())->toHaveCount(2);   // nimic salvat la erori

    CreditBonus::saveTiers([]);
    expect(CreditBonus::tiers())->toBe([]);
});

it('limitele de încărcare: minim, maxim și sume rapide se validează; sumele din afara limitelor nu apar', function () {
    $this->actingAs(ctbAdmin(), 'admin');

    CreditBonus::saveLimits('100, 20, 50', '10', '300');
    expect(CreditBonus::min())->toBe(10.0)->and(CreditBonus::max())->toBe(300.0)->and(CreditBonus::presets())->toBe([20, 50, 100]);

    expect(fn () => CreditBonus::saveLimits('20', '0', '300'))->toThrow(DomainException::class)
        ->and(fn () => CreditBonus::saveLimits('20', '10', '9'))->toThrow(DomainException::class)
        ->and(fn () => CreditBonus::saveLimits('20', '10', '5000'))->toThrow(DomainException::class)
        ->and(fn () => CreditBonus::saveLimits('5', '10', '300'))->toThrow(DomainException::class)
        ->and(fn () => CreditBonus::saveLimits('1,2,3,4,5,6,7', '1', '300'))->toThrow(DomainException::class);
    expect(CreditBonus::presets())->toBe([20, 50, 100]);
});

// ---- Încărcare (quote / start / complete) -------------------------------------

it('quote: cât plătește, bonusul, cât primește și următorul prag; refuză sub minim, peste maxim și când cumpărarea e oprită', function () {
    ctbOn();

    $q = CreditTopups::quote(100);
    expect($q->amount)->toBe(100.0)->and($q->bonus)->toBe(10.0)->and($q->credited)->toBe(110.0)->and($q->to_pay)->toBe(100.0)
        ->and($q->percent)->toBe(10.0)->and($q->next)->toBe(['min' => 200.0, 'percent' => 15.0, 'missing' => 100.0]);

    $small = CreditTopups::quote(40);
    expect($small->bonus)->toBe(0.0)->and($small->next['missing'])->toBe(60.0);

    expect(fn () => CreditTopups::quote(5))->toThrow(DomainException::class, 'între')
        ->and(fn () => CreditTopups::quote(501))->toThrow(DomainException::class, 'între');

    PaymentMethods::setCreditsPurchasable(false);
    expect(fn () => CreditTopups::quote(100))->toThrow(DomainException::class, 'nu este disponibilă');
});

it('start: încărcare „pending” cu sumele înghețate, expirare peste 30 min; nu atinge ledgerul; anulează încărcarea neplătită anterioară', function () {
    ctbOn();
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $ana = ctbUser();

    $a = CreditTopups::start($ana, 100);
    expect($a->status)->toBe('pending')->and((float) $a->amount)->toBe(100.0)->and((float) $a->bonus)->toBe(10.0)
        ->and($a->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue()->and($a->isPending())->toBeTrue()
        ->and(CreditTransaction::count())->toBe(0)->and((float) $ana->fresh()->credit_balance)->toBe(0.0);

    $b = CreditTopups::start($ana, 50);
    expect($a->fresh()->status)->toBe('cancelled')->and($b->fresh()->status)->toBe('pending');
    expect(AdminActivityLog::pluck('action')->all())->toContain('credits.topup_started');

    expect(fn () => CreditTopups::start(ParticipantRegistry::create('Fără Cont', '0744000111'), 50))->toThrow(DomainException::class, 'cont');
});

it('complete: un singur rând în ledger (plătit + bonus), sursa aplicație, legat de încărcare; soldul crește cu totalul', function () {
    ctbOn();
    $ana = ctbUser();
    $t = CreditTopups::start($ana, 100);

    $tx = CreditTopups::complete($t, 'pi_test_1', 'stripe');

    expect((float) $tx->amount)->toBe(110.0)->and((float) $tx->bonus)->toBe(10.0)->and($tx->type)->toBe('load')
        ->and($tx->source)->toBe(CreditTransaction::SOURCE_APP)->and($tx->reference_type)->toBe(CreditTopup::class)->and($tx->reference_id)->toBe($t->id);
    expect((float) $ana->fresh()->credit_balance)->toBe(110.0)->and(CreditTransaction::count())->toBe(1);

    $t->refresh();
    expect($t->status)->toBe('paid')->and($t->paid_at)->not->toBeNull()->and($t->credit_transaction_id)->toBe($tx->id)
        ->and($t->provider)->toBe('stripe')->and($t->provider_ref)->toBe('pi_test_1');
});

it('complete e idempotent: un webhook repetat nu dublează creditele', function () {
    ctbOn();
    $ana = ctbUser();
    $t = CreditTopups::start($ana, 100);

    $first = CreditTopups::complete($t);
    $second = CreditTopups::complete(CreditTopup::find($t->id));

    expect($second->id)->toBe($first->id)->and(CreditTransaction::count())->toBe(1)->and((float) $ana->fresh()->credit_balance)->toBe(110.0);
});

it('bonusul e înghețat la start: schimbarea pragurilor după aceea nu schimbă ce primește', function () {
    ctbOn();
    $ana = ctbUser();
    $t = CreditTopups::start($ana, 100);

    ctbOn([['min' => 100, 'percent' => 50]]);   // admin schimbă regula între timp
    $tx = CreditTopups::complete($t);

    expect((float) $tx->amount)->toBe(110.0)->and((float) $tx->bonus)->toBe(10.0);
});

it('complete funcționează și pe o încărcare expirată sau anulată (banii au fost luați); fail/cancel nu ating ledgerul', function () {
    ctbOn();
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $ana = ctbUser();

    $late = CreditTopups::start($ana, 100);
    Carbon::setTestNow(now()->addMinutes(45));
    expect(CreditTopups::expireStale())->toBe(1)->and($late->fresh()->status)->toBe('expired');
    CreditTopups::complete($late);
    expect((float) $ana->fresh()->credit_balance)->toBe(110.0)->and($late->fresh()->status)->toBe('paid');

    $failed = CreditTopups::start($ana, 50);
    CreditTopups::fail($failed);
    expect($failed->fresh()->status)->toBe('failed');
    $cancelled = CreditTopups::start($ana, 50);
    CreditTopups::cancel($cancelled);
    expect($cancelled->fresh()->status)->toBe('cancelled')->and(CreditTransaction::count())->toBe(1);

    CreditTopups::cancel($late->fresh());   // una deja plătită nu se mai poate anula
    expect($late->fresh()->status)->toBe('paid');
});

// ---- Recepție -------------------------------------------------------------------

it('la Recepție: participantul primește suma + bonus, plata e pe suma vândută, iar raportul de casă numără doar banii', function () {
    $admin = ctbAdmin();
    $this->actingAs($admin, 'admin');
    ctbOn();
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = Party::create([
        'name' => 'Petrecere bonus', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ]);
    $p = ParticipantRegistry::create('Ion Bonus', '0722 333 444');

    $tx = CreditLedger::sell($party, $p, 100, [['method' => 'cash', 'amount' => 100]], $admin->id);

    expect((float) $tx->amount)->toBe(110.0)->and((float) $tx->bonus)->toBe(10.0)
        ->and($tx->payments->sum(fn ($x) => (float) $x->amount))->toBe(100.0);
    expect(CreditLedger::balance($p->fresh()))->toBe(110.0);

    $session = $tx->session;
    $sum = ReceptionSummary::for($session);
    expect($sum->credits_amount)->toBe(100.0)->and($sum->cash_credits)->toBe(100.0);
    expect(CreditLedger::partyTotals($party)->sold_amount)->toBe(100.0);

    // anularea cere ca toate creditele (și bonusul) să fie nefolosite și le scoate din sold
    CreditLedger::cancel($tx->id, 'greșeală', $admin->id);
    expect(CreditLedger::balance($p->fresh()))->toBe(0.0);
});

it('sub primul prag, vânzarea la Recepție nu are bonus', function () {
    $admin = ctbAdmin();
    $this->actingAs($admin, 'admin');
    ctbOn();
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = Party::create([
        'name' => 'Petrecere fără bonus', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ]);
    $p = ParticipantRegistry::create('Ion Mic', '0722 555 666');

    $tx = CreditLedger::sell($party, $p, 50, [['method' => 'cash', 'amount' => 50]], $admin->id);

    expect((float) $tx->amount)->toBe(50.0)->and((float) $tx->bonus)->toBe(0.0);
});

it('Credite › Sumar: „încărcat” include bonusul, cu „din care bonus” separat; identitatea de sold se păstrează', function () {
    ctbOn();
    $ana = ctbUser();
    CreditTopups::complete(CreditTopups::start($ana, 200));   // 200 + 30 bonus

    $s = CreditsOverview::summary();

    expect($s->loaded)->toBe(230.0)->and($s->bonus)->toBe(30.0)->and($s->active)->toBe(230.0)
        ->and(round($s->loaded - $s->spent - $s->refunded + $s->adjusted, 2))->toBe($s->active);
});

it('partialul de la Recepție arată bonusul și totalul primit, doar când există bonus', function () {
    expect(view('livewire.reception._credit-bonus', ['bonus' => 10.0, 'amount' => 100.0])->render())
        ->toContain('data-credit-bonus')->toContain('10,00')->toContain('110,00');
    expect(trim(view('livewire.reception._credit-bonus', ['bonus' => 0.0, 'amount' => 100.0])->render()))->toBe('');
});

// ---- Aplicația: Portofel › Încarcă (pagină separată) ----------------------------------------

it('Portofel: „Încarcă” e un link spre pagina separată (fără popup); cu cumpărarea oprită apare mesajul spre Recepție', function () {
    ctbOn();
    $this->actingAs(ctbUser(), 'participant');
    $this->get('/portofel')->assertOk()->assertSee('data-topup-open', false)->assertSee('href="'.route('app.wallet.load').'"', false)
        ->assertDontSee('data-topup-continue', false)->assertDontSee('data-topup-preset', false);

    PaymentMethods::setCreditsPurchasable(false);
    $this->get('/portofel')->assertOk()->assertDontSee('data-topup-open', false)->assertSee('data-topup-unavailable', false);
    $this->get(route('app.wallet.load'))->assertRedirect(route('app.wallet'));
});

it('pagina Încarcă: sume rapide, prag de bonus, calcul live, îndemn spre următorul prag; Renunță în stânga, Confirmă în dreapta', function () {
    ctbOn();
    $this->actingAs(ctbUser(), 'participant');

    $this->get(route('app.wallet.load'))->assertOk()->assertSee('data-topup-preset="50"', false)->assertSee('data-bonus-tiers', false)
        ->assertSee('de la 100 lei → +10%')->assertSeeInOrder(['data-topup-cancel', 'data-topup-confirm'], false);

    Livewire::test(Topup::class)->call('setAmount', 50)->assertSet('amount', '50')
        ->assertSee('data-topup-quote', false)->assertSee('Încă 50 lei și primești +10% bonus')->assertDontSee('Bonus (');
    Livewire::test(Topup::class)->set('amount', '100')->assertSee('Bonus (10%)')->assertSee('+10 lei')->assertSee('110 lei');
    Livewire::test(Topup::class)->set('amount', '5')->assertSee('între')->assertDontSee('data-topup-quote', false);
});

it('Confirmă cu plata simulată: creditele (suma + bonus) intră direct în cont, fără pagina de plată, cu mesaj de succes', function () {
    ctbOn();
    config(['app.dxa_tickets_auto_paid' => true]);
    $ana = ctbUser();
    $this->actingAs($ana, 'participant');

    Livewire::test(Topup::class)->set('amount', '100')->call('confirm')->assertRedirect(route('app.wallet'));

    $topup = CreditTopup::first();
    expect($topup->status)->toBe('paid')->and($topup->provider)->toBe('simulated')
        ->and((float) $ana->fresh()->credit_balance)->toBe(110.0)->and(CreditTransaction::count())->toBe(1)
        ->and((float) CreditTransaction::first()->bonus)->toBe(10.0);
    expect(session('status'))->toContain('Ai încărcat 100,00 lei')->toContain('bonus 10,00 lei');

    $this->get('/portofel')->assertOk()->assertSee('110,00');
});

it('Confirmă fără plată simulată: duce la pagina de plată (loc rezervat) și nu creditează nimic', function () {
    ctbOn();
    config(['app.dxa_tickets_auto_paid' => false]);
    $ana = ctbUser();
    $this->actingAs($ana, 'participant');

    Livewire::test(Topup::class)->set('amount', '100')->call('confirm')->assertRedirect(route('app.wallet.topup', CreditTopup::first()));

    expect(CreditTopup::first()->status)->toBe('pending')->and((float) $ana->fresh()->credit_balance)->toBe(0.0)->and(CreditTransaction::count())->toBe(0);
});

it('Confirmă sub minim: eroare, nicio încărcare creată', function () {
    ctbOn();
    $this->actingAs(ctbUser(), 'participant');

    Livewire::test(Topup::class)->set('amount', '5')->call('confirm')->assertNoRedirect()->assertSet('error', fn ($e) => str_contains($e, 'între'));
    expect(CreditTopup::count())->toBe(0);
});

it('Renunță pe pagina Încarcă: doar întoarce la Portofel, fără încărcare și fără mesaj', function () {
    ctbOn();
    $this->actingAs(ctbUser(), 'participant');

    $this->get(route('app.wallet.load'))->assertOk()->assertSee('data-topup-cancel', false)->assertSee('href="'.route('app.wallet').'"', false);
    expect(CreditTopup::count())->toBe(0)->and(session('status'))->toBeNull();
});

it('pagina de plată: rezumatul, „urmează în curând”, doar pentru proprietar; renunțarea anulează fără să atingă soldul', function () {
    ctbOn();
    $ana = ctbUser();
    $bob = ctbUser('Bob Altul', '0733000222');
    $topup = CreditTopups::start($ana, 100);

    $this->actingAs($bob, 'participant')->get(route('app.wallet.topup', $topup))->assertNotFound();

    $this->actingAs($ana, 'participant');
    $this->get(route('app.wallet.topup', $topup))->assertOk()->assertSee('data-topup-summary', false)->assertSee('Plata cu cardul urmează în curând')
        ->assertSee('+10 lei')->assertSee('110 lei')->assertSee('data-topup-cancel', false);

    Livewire::test(TopupPay::class, ['topup' => $topup])->call('cancel')->assertRedirect(route('app.wallet'));
    expect($topup->fresh()->status)->toBe('cancelled')->and((float) $ana->fresh()->credit_balance)->toBe(0.0)->and(session('status'))->toBeNull();   // renunțarea e silențioasă

    $this->get(route('app.wallet.topup', $topup))->assertOk()->assertSee('data-topup-closed', false)->assertSee('Încărcarea a fost anulată')->assertDontSee('data-topup-cancel', false);
});

it('pagina de plată: o încărcare deja plătită duce la Portofel', function () {
    ctbOn();
    $ana = ctbUser();
    $topup = CreditTopups::start($ana, 100);
    CreditTopups::complete($topup);
    $this->actingAs($ana, 'participant');

    $this->get(route('app.wallet.topup', $topup))->assertRedirect(route('app.wallet'));
});

it('în istoricul din Portofel încărcarea apare cu mențiunea „din care bonus”', function () {
    ctbOn();
    $ana = ctbUser();
    CreditTopups::complete(CreditTopups::start($ana, 100));
    $this->actingAs($ana->fresh(), 'participant');

    $this->get('/portofel')->assertOk()->assertSee('Încărcare cu cardul')->assertSee('din care bonus 10,00 lei')->assertSee('+110,00');
});

// ---- Admin: Setări ----------------------------------------------------------------------

it('Setări › Metode de plată: pragurile și limitele se editează din panou (adaugă/șterge prag, salvează, mesaje lângă butoane)', function () {
    $this->actingAs(ctbAdmin(), 'admin');
    ctbOn([]);

    Livewire::actingAs(Admin::first(), 'admin')->test(PaymentMethodsPanel::class)
        ->assertSee('data-credit-topup-settings', false)->assertDontSee('data-bonus-message', false)
        ->call('addTier')->set('tiers.0.min', '100')->set('tiers.0.percent', '10')
        ->call('addTier')->set('tiers.1.min', '200')->set('tiers.1.percent', '15')
        ->call('saveBonus')->assertSet('bonusError', null)->assertSet('bonusMessage', fn ($m) => str_contains((string) $m, 'Bonusul a fost salvat'))
        ->assertSee('data-bonus-message', false)->assertSee('Bonusul a fost salvat')
        ->call('removeTier', 1)->assertSet('tiers', fn ($t) => count($t) === 1)
        ->call('saveBonus');
    expect(CreditBonus::tiers())->toBe([['min' => 100.0, 'percent' => 10.0]]);

    Livewire::actingAs(Admin::first(), 'admin')->test(PaymentMethodsPanel::class)
        ->set('tiers.0.percent', '150')->call('saveBonus')
        ->assertSet('bonusError', fn ($e) => str_contains((string) $e, 'procent'))->assertSet('bonusMessage', null)
        ->assertSee('data-bonus-error', false)->assertDontSee('data-bonus-message', false)
        ->assertSet('tiers.0.percent', '150')   // la eroare rămân valorile scrise, ca să le poți corecta
        ->set('topupMin', '20')->set('topupMax', '400')->set('presets', '20, 100')->call('saveLimits')
        ->assertSet('limitsError', null)->assertSet('limitsMessage', 'Valorile de încărcare au fost salvate.')
        ->assertSee('data-limits-message', false)->assertSee('Valorile de încărcare au fost salvate.')
        ->assertDontSee('data-bonus-error', false);   // un mesaj nou îl scoate pe cel vechi din cealaltă secțiune
    expect(CreditBonus::min())->toBe(20.0)->and(CreditBonus::max())->toBe(400.0)->and(CreditBonus::presets())->toBe([20, 100]);
    expect(CreditBonus::tiers())->toHaveCount(1);   // eroarea n-a salvat nimic

    Livewire::actingAs(Admin::first(), 'admin')->test(PaymentMethodsPanel::class)
        ->set('topupMax', '10')->call('saveLimits')->assertSet('limitsError', fn ($e) => str_contains((string) $e, 'maximă'))->assertSee('data-limits-error', false);
});

it('panoul cu praguri apare doar când participanții pot cumpăra credite', function () {
    $this->actingAs(ctbAdmin(), 'admin');
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(false);

    Livewire::actingAs(Admin::first(), 'admin')->test(PaymentMethodsPanel::class)->assertDontSee('data-credit-topup-settings', false);
});
