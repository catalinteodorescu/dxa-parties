<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\Legal\TermsPanel;
use App\Livewire\Admin\Participants\Index;
use App\Livewire\Participant\Register;
use App\Livewire\Participant\TermsBanner;
use App\Livewire\Participant\Tickets;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\TermsVersion;
use App\Services\AccountExport;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantRegistry;
use App\Services\Terms;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 60). Afișarea telefonului la biletele trimise + Termeni și condiții (un text, ne-blocant). */
class R60Sms implements SmsSender
{
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = [$phone, $message];
    }
}

function r60User(string $name, string $phone): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function r60Admin(array $perm = []): Admin
{
    return Admin::create(['name' => 'Admin R60', 'phone' => '0788'.random_int(100000, 999999), 'role' => 'admin', 'permissions' => $perm ?: Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => true, 'password' => 'secret-pass']);
}

function r60Party(): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create([
        'name' => 'Petrecere R60', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

beforeEach(function () {
    $this->sms = new R60Sms;
    app()->instance(SmsSender::class, $this->sms);
});

// ---- A. Afișare telefon -------------------------------------------------------------------------------------------

it('biletul trimis la un telefon fără cont arată telefonul o singură dată; cu nume arată numele și telefonul', function () {
    $ana = r60User('Ana', '0722111222');
    $bob = r60User('Bob', '0733111222');
    $order = TicketOrders::place($ana, r60Party(), 'Bilet', 3);
    TicketTransfers::send($order->tickets[1]->id, $ana, '0744555666', $this->sms);
    TicketTransfers::send($order->tickets[2]->id, $ana, $bob->phone, $this->sms);

    $this->actingAs($ana, 'participant');
    $html = Livewire::test(Tickets::class)->assertSee('trimis la +40744555666')->assertSee('trimis lui Bob')->html();

    expect(substr_count($html, '+40744555666'))->toBe(1);
});

// ---- B. Termeni și condiții ---------------------------------------------------------------------------------------

it('fără Termeni publicați nu se cere și nu se arată nimic', function () {
    expect(Terms::enabled())->toBeFalse()
        ->and(Terms::needsAcceptance(r60User('Ana', '0722111222')))->toBeFalse();
    $this->get('/termeni')->assertNotFound();

    Livewire::test(Register::class)->assertDontSee('data-accept-terms', false);
    expect(ParticipantAccounts::startRegistration('Ana Test', '0722333444', 'parola-sigura', $this->sms))->toBe('+40722333444');
});

it('publicarea creează versiuni; prima cere mereu acceptarea, următoarele după bifă; textul identic e refuzat', function () {
    $admin = r60Admin();
    $v1 = Terms::publish("# Titlu\n\nText", false, $admin);
    expect($v1->requires_reaccept)->toBeTrue()->and($v1->published_by)->toBe($admin->id);

    $v2 = Terms::publish('Text nou, modificare minoră', false, $admin);
    expect($v2->requires_reaccept)->toBeFalse()->and(Terms::requiredId())->toBe($v1->id);

    $v3 = Terms::publish('Text nou, schimbare importantă', true, $admin);
    expect(Terms::requiredId())->toBe($v3->id)->and(TermsVersion::count())->toBe(3)
        ->and(fn () => Terms::publish('Text nou, schimbare importantă', true))->toThrow(DomainException::class)
        ->and(fn () => Terms::publish('   ', true))->toThrow(DomainException::class);
});

it('pagina /termeni arată textul, escape-uit, cu titluri și paragrafe', function () {
    Terms::publish("# Bilete\n\nPrima linie\nA doua <b>linie</b>\n\nAl doilea paragraf", true);

    $this->get('/termeni')->assertOk()->assertSee('Bilete')->assertSee('Prima linie')->assertSee('&lt;b&gt;linie&lt;/b&gt;', false)->assertDontSee('<b>linie</b>', false);
});

it('contul nou cere bifa Termenilor și păstrează versiunea acceptată', function () {
    $v = Terms::publish('Termeni de probă', true);

    expect(fn () => ParticipantAccounts::startRegistration('Ana Test', '0722333444', 'parola-sigura', $this->sms))->toThrow(DomainException::class, 'Termenilor');
    Livewire::test(Register::class)->assertSee('data-accept-terms', false)
        ->set('name', 'Ana Test')->set('phone', '0722333444')->set('password', 'parola-sigura')->set('password_confirmation', 'parola-sigura')
        ->call('register')->assertHasErrors('accept_terms');

    $phone = ParticipantAccounts::startRegistration('Ana Test', '0722333444', 'parola-sigura', $this->sms, true);
    preg_match('/\d{6}/', end($this->sms->sent)[1], $m);
    $ana = ParticipantAccounts::verifyRegistration($phone, $m[0]);

    expect($ana->terms_version_id)->toBe($v->id)->and($ana->terms_accepted_at)->not->toBeNull()->and(Terms::needsAcceptance($ana))->toBeFalse();
});

it('participantul legat de Recepție primește acceptarea la înregistrare', function () {
    $v = Terms::publish('Termeni de probă', true);
    ParticipantRegistry::create('Ana Recepție', '0722333444');

    $phone = ParticipantAccounts::startRegistration('Ana', '0722333444', 'parola-sigura', $this->sms, true);
    preg_match('/\d{6}/', end($this->sms->sent)[1], $m);

    expect(ParticipantAccounts::verifyRegistration($phone, $m[0])->terms_version_id)->toBe($v->id);
});

it('popup-ul ne-blocant apare doar când lipsește acceptarea și dispare după „Am citit și accept”', function () {
    $ana = r60User('Ana', '0722111222');
    Terms::publish('Versiunea 1', true);

    $this->actingAs($ana, 'participant');
    Livewire::test(TermsBanner::class)->assertSee('data-terms-banner', false)->call('accept')->assertDontSee('data-terms-banner', false);
    expect($ana->fresh()->terms_version_id)->toBe(Terms::current()->id);

    // modificare minoră: nimeni nu e rugat din nou; modificare majoră: toți
    Terms::publish('Versiunea 2 minoră', false);
    Livewire::test(TermsBanner::class)->assertDontSee('data-terms-banner', false);
    Terms::publish('Versiunea 3 majoră', true);
    Livewire::test(TermsBanner::class)->assertSee('data-terms-banner', false);

    // ne-blocant: restul aplicației merge fără acceptare
    $this->get('/bilete')->assertOk()->assertSee('data-terms-banner', false);
    $this->get('/cont')->assertOk();
    $this->get('/cont/date')->assertOk();
});

it('Termenii apar ca buton în subsol și în fereastra de cumpărare, nu și în cont; linkul extern cedează locul textului din aplicație', function () {
    $ana = r60User('Ana', '0722111222');
    $party = r60Party();
    $this->actingAs($ana, 'participant');

    $this->get('/cont')->assertOk()->assertDontSee('data-footer-terms', false);
    $this->get('/petreceri/'.$party->id)->assertOk()->assertDontSee('data-buy-terms', false)->assertDontSee('data-footer-terms', false);

    Terms::publish('Termeni de probă', true);
    $this->get('/cont')->assertSee('data-footer-terms', false)->assertDontSee('data-account-terms', false);
    $this->get('/petreceri/'.$party->id)->assertSee('data-buy-terms', false)->assertSee('data-footer-terms', false);
});

it('pagina Termeni: textul stă într-o fereastră cu scroll, iar butonul de acceptare apare sub text doar cui n-a acceptat', function () {
    $ana = r60User('Ana', '0722111222');
    Terms::publish('Termeni de probă', true);

    $this->get('/termeni')->assertOk()->assertSee('overflow-y: auto', false)->assertDontSee('data-terms-accept', false);

    $this->actingAs($ana, 'participant');
    Livewire::test(App\Livewire\Participant\Terms::class)->assertSee('data-terms-accept', false)->call('accept')
        ->assertDontSee('data-terms-accept', false)->assertSee('data-terms-done', false);
    expect(Terms::needsAcceptance($ana->fresh()))->toBeFalse();
});

it('popup-ul de acceptare se închide doar din X (fără închidere la click pe fundal) și nu apare pe pagina Termeni', function () {
    $ana = r60User('Ana', '0722111222');
    Terms::publish('Termeni de probă', true);
    $this->actingAs($ana, 'participant');

    $html = $this->get('/bilete')->assertOk()->getContent();
    expect($html)->toContain('data-terms-banner')->toContain('data-terms-close');
    $start = strpos($html, 'dxa_terms_popup_closed') - 200;
    $banner = substr($html, $start, strpos($html, 'data-terms-accept', $start) - $start);
    expect($banner)->not->toContain('click.self')->and($banner)->toContain('sessionStorage');

    $this->get('/termeni')->assertOk()->assertDontSee('data-terms-banner', false);
});

it('în lista de participanți conturile provizorii sunt marcate și numărate separat de conturile reale', function () {
    $admin = r60Admin();
    $ana = r60User('Ana', '0722111222');
    $order = TicketOrders::place($ana, r60Party(), 'Bilet', 2);
    TicketTransfers::send($order->tickets[1]->id, $ana, '0744555666', $this->sms);

    Livewire::actingAs($admin, 'admin')->test(Index::class)
        ->assertSee('1 cu cont în aplicație')->assertSee('1 provizoriu')->assertSee('data-provisional', false);
});

it('exportul de date conține acceptarea Termenilor', function () {
    $v = Terms::publish('Termeni de probă', true);
    $ana = r60User('Ana', '0722111222');
    Terms::accept($ana);

    expect(AccountExport::build($ana->fresh())['profile']['terms_version_accepted'])->toBe($v->id);
});

it('panoul din Setări: pornește cu textul de probă (nepublicat), publică după confirmare și arată istoricul', function () {
    $admin = r60Admin();

    $c = Livewire::actingAs($admin, 'admin')->test(TermsPanel::class)->assertSet('body', Terms::sample());
    expect(Terms::enabled())->toBeFalse();

    $c->set('body', "# Termeni\n\nText final")->call('publish')->assertSet('error', '');
    expect(Terms::current()->body)->toBe("# Termeni\n\nText final")->and(Terms::current()->published_by)->toBe($admin->id);

    $c->assertSee('data-terms-history', false)->assertSee('Admin R60')->set('body', '')->call('publish')->assertSet('error', 'Scrie textul Termenilor și condițiilor.');
});

it('panoul Termeni cere permisiunea Legal', function () {
    $fara = r60Admin(array_fill_keys(array_keys(Permissions::legacyAdmin()), 0));

    Livewire::actingAs($fara, 'admin')->test(TermsPanel::class)->call('publish')->assertForbidden();
    expect(Terms::enabled())->toBeFalse();
});
