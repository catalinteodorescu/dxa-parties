<?php

use App\Livewire\Admin\Bar\PartyLive;
use App\Livewire\Admin\Bar\Stats as BarStats;
use App\Livewire\Admin\BarApp\AppSettings as BarAppSettings;
use App\Livewire\Admin\BarReports\Index as BarReportsIndex;
use App\Livewire\Admin\BarReports\Show as BarReportShow;
use App\Livewire\Admin\ReceptionApp\AppSettings as ReceptionAppSettings;
use App\Livewire\Admin\Reconciliation\Index as ReconciliationIndex;
use App\Livewire\Admin\Sales\Index as SalesIndex;
use App\Livewire\Admin\StockReports\Form as StockReportForm;
use App\Livewire\Bar\Home;
use App\Livewire\Bar\Login;
use App\Livewire\Bar\PartyPicker;
use App\Livewire\Bar\Recent;
use App\Livewire\Bar\Report;
use App\Livewire\Bar\Sale;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\BarReport;
use App\Models\CreditTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\Sale as SaleModel;
use App\Models\SalesGroup;
use App\Models\StockReport;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Services\SaleRecorder;
use App\Support\BarApp;
use App\Support\PaymentMethods;
use App\Support\ReceptionApp;
use App\Support\Settings\Settings;
use App\Support\SubmitGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-09-25 21:00:00'));
afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (PWA Bar). Setări aplicație, manifest/iconiță/service worker, login și acces pe /bar, alegerea petrecerii,
 * Acasă (sumar), Vânzare (produse, cele mai vândute, plăți, QR) și Raportare (trimitere care blochează vânzările).
 */
function bpUser(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Barman '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin',
        'is_active' => true,
        'access_admin' => false,
        'access_bar' => true,
        'password' => 'secret-pass',
    ], $attrs));
}

/** Petrecere pe 25.09.2026, 20:00–23:59 („acum” = în timpul ei). */
function bpParty(string $name = 'Latin Party', array $extra = []): Party
{
    return Party::create(array_merge([
        'name' => $name,
        'kind' => 'basic',
        'start_date' => '2026-09-25',
        'start_time' => '20:00',
        'end_time' => '23:59',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
        'payment_methods' => ['cash', 'card', 'token', 'credit'],
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
    ], $extra));
}

function bpItem(string $name, float $price, bool $active = true): MenuItem
{
    $cat = MenuCategory::firstOrCreate(['name' => 'Băuturi'], ['is_active' => true]);

    return MenuItem::create(['menu_category_id' => $cat->id, 'name' => $name, 'price' => $price, 'is_active' => $active]);
}

/** Barmanul logat, cu petrecerea aleasă în sesiune, gata pentru Livewire::test(). */
function bpWorking(?Party $party = null, array $userAttrs = []): array
{
    $user = bpUser($userAttrs);
    $party ??= bpParty();
    test()->actingAs($user, 'admin');
    session([BarApp::PARTY_SESSION_KEY => $party->id]);

    return [$user, $party];
}

function bpSell(Party $party, Admin $user, array $lines, array $payments): SaleModel
{
    return SaleRecorder::record(SalesGroup::openFor($party->id, $user->id), $lines, $payments, source: 'app', adminId: $user->id);
}

// ---- PWA: manifest, iconiță, service worker -----------------------------------

it('serveste manifestul PWA cu scope-ul /bar/ si iconitele dinamice', function () {
    $json = $this->get(route('bar.manifest'))->assertOk()->json();

    expect($json['start_url'])->toBe('/bar/')
        ->and($json['scope'])->toBe('/bar/')
        ->and($json['id'])->toBe('/bar/')
        ->and($json['display'])->toBe('standalone')
        ->and($json['name'])->toBe('Bar')
        ->and($json['icons'][0]['src'])->toContain('/bar/icon/192.png');
});

it('manifestul barului urmeaza numele si tema din setari, separat de recepție', function () {
    Settings::set(BarApp::KEY_NAME, 'Bar DXA');
    Settings::set(BarApp::KEY_THEME, 'indigo');
    Settings::set(ReceptionApp::KEY_NAME, 'Casa DXA');

    $json = $this->get(route('bar.manifest'))->json();

    expect($json['name'])->toBe('Bar DXA')
        ->and($json['theme_color'])->toBe(BarApp::primary())
        ->and($this->get(route('receptie.manifest'))->json('name'))->toBe('Casa DXA');
});

it('iconița barului e un PNG cu gradient, cu versiune proprie', function () {
    Storage::fake('local');
    Settings::set(BarApp::KEY_THEME, 'emerald');
    $v1 = BarApp::iconVersion();

    $r = $this->get(route('bar.icon', ['size' => 192]))->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('image/png');
    $img = imagecreatefromstring($r->getContent());
    expect(imagecolorat($img, 4, 4))->not->toBe(imagecolorat($img, 187, 187));

    Settings::set(BarApp::KEY_THEME, 'indigo');
    expect(BarApp::iconVersion())->not->toBe($v1);

    $this->get('/bar/icon/999.png')->assertNotFound();
});

it('service worker-ul barului are cache propriu', function () {
    $r = $this->get(route('bar.sw'))->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('javascript')
        ->and($r->getContent())->toContain('dxa-bar-static-v1')
        ->and($this->get(route('receptie.sw'))->getContent())->toContain('dxa-receptie-static-v1');
});

// ---- Login / acces ----------------------------------------------------------------

it('trimite vizitatorul nelogat la login-ul de bar', function () {
    $this->get('/bar')->assertRedirect(route('bar.login'));
    $this->get('/bar/vanzare')->assertRedirect(route('bar.login'));
    $this->get(route('bar.login'))->assertOk()->assertSee('Bar');
    $this->get('/receptie')->assertRedirect(route('receptie.login'));
});

it('login-ul de bar refuza un cont fara bifa bar, iar cel logat merge la Acasa din bar', function () {
    bpUser(['phone' => '0722666111', 'access_bar' => false, 'access_reception' => true]);

    Livewire::test(Login::class)
        ->set('phone', '0722666111')->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors('phone');
    expect(auth('admin')->check())->toBeFalse();

    // Un cont cu bifa bar, deja logat, nu mai vede login-ul: e trimis la Acasă din bar (nu în admin).
    $this->actingAs(bpUser(['phone' => '0722666222']), 'admin');
    $this->get(route('bar.login'))->assertRedirect(route('bar.home'));
});

it('un cont fara bifa bar vede pagina „Nu ai acces” (403) si ramane logat, cu link catre recepție', function () {
    $this->actingAs(bpUser(['access_bar' => false, 'access_reception' => true]), 'admin');

    $this->get(route('bar.home'))->assertForbidden()->assertSee('Nu ai acces')
        ->assertSee(route('receptie.home'), false);
    expect(auth('admin')->check())->toBeTrue();
});

it('deconectarea din bar duce la login-ul de bar', function () {
    $this->actingAs(bpUser(), 'admin');

    $this->post(route('session.logout'), ['to' => 'bar'])->assertRedirect(route('bar.login'));
    expect(auth('admin')->check())->toBeFalse();
});

// ---- Setări (admin) ---------------------------------------------------------------

it('pagina de setari a aplicatiei de bar salveaza tema, numele si logo-ul', function () {
    Storage::fake('public');
    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    $this->get(route('admin.settings.bar-app'))->assertOk()->assertSee('Bar · Aplicație');

    Livewire::test(BarAppSettings::class)
        ->call('selectTheme', 'indigo')
        ->set('name', 'Bar DXA')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    expect(BarApp::themeKey())->toBe('indigo')
        ->and(BarApp::name())->toBe('Bar DXA')
        ->and(BarApp::uploadedLogoPath())->not->toBeNull()
        ->and(ReceptionApp::name())->toBe('Recepție');
    expect(AdminActivityLog::pluck('action')->all())->toContain('settings.bar_app_updated');

    Livewire::test(BarAppSettings::class)->call('removeLogo')->call('save');
    expect(BarApp::uploadedLogoPath())->toBeNull();
});

it('setarile barului refuza nume prea lung si fisiere care nu sunt imagini', function () {
    Storage::fake('public');
    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(BarAppSettings::class)
        ->set('name', str_repeat('a', 41))
        ->set('logoUpload', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['name', 'logoUpload']);
});

it('un barman fara drept de admin nu deschide setarile aplicatiei din panou', function () {
    $this->actingAs(bpUser(), 'admin');

    $this->get(route('admin.settings.bar-app'))->assertForbidden();
});

it('pe ambele pagini de setari prima sectiune e cea cu numele, apoi tema, apoi previzualizarea', function () {
    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    foreach ([BarAppSettings::class, ReceptionAppSettings::class] as $component) {
        $html = Livewire::test($component)->html();

        $name = strpos($html, 'Numele aplicației');
        $theme = strpos($html, 'Culoarea temei');
        $preview = strpos($html, 'Previzualizare');

        expect($name)->not->toBeFalse()
            ->and($name)->toBeLessThan($theme)
            ->and($theme)->toBeLessThan($preview);
    }
});

it('meniul din admin are link catre setarile aplicatiei de bar', function () {
    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    $this->get(route('admin.settings.index'))->assertOk()->assertSee(route('admin.settings.bar-app'), false)->assertSee('Aplicație bar');
});

// ---- Alegerea petrecerii ----------------------------------------------------------

it('petrecerile pentru bar: cele curente si cele cu sesiune deschisa, chiar incheiate', function () {
    $user = bpUser();
    $live = bpParty('Curentă');
    $ended = bpParty('Încheiată cu sesiune', ['start_date' => '2026-09-20']);
    $endedClosed = bpParty('Încheiată fără sesiune', ['start_date' => '2026-09-21']);
    bpParty('Ciornă', ['status' => 'draft']);
    bpParty('Inactivă', ['is_active' => false]);
    SalesGroup::openFor($ended->id, $user->id);
    $g = SalesGroup::openFor($endedClosed->id, $user->id);
    $g->close();

    expect(Party::forBar()->pluck('name')->all())->toBe(['Încheiată cu sesiune', 'Curentă']);
});

it('ecranul de alegere retine petrecerea si duce la Acasa', function () {
    $this->actingAs(bpUser(), 'admin');
    $party = bpParty();

    Livewire::test(PartyPicker::class)
        ->assertSee('Alege petrecerea')->assertSee('Latin Party')
        ->call('choose', $party->id)
        ->assertRedirect(route('bar.home'));

    expect(session(BarApp::PARTY_SESSION_KEY))->toBe($party->id);
});

it('nu se poate alege o petrecere indisponibila', function () {
    $this->actingAs(bpUser(), 'admin');
    $draft = bpParty('Ciornă', ['status' => 'draft']);

    Livewire::test(PartyPicker::class)->call('choose', $draft->id)->assertNotFound();
});

it('fara petrecere aleasa, Acasa si Vanzarea trimit la alegere', function () {
    $this->actingAs(bpUser(), 'admin');
    bpParty();

    Livewire::test(Home::class)->assertRedirect(route('bar.party'));
    Livewire::test(Sale::class)->assertRedirect(route('bar.party'));
    Livewire::test(Report::class)->assertRedirect(route('bar.party'));
});

// ---- Acasa ------------------------------------------------------------------------

it('Acasa arata petrecerea (doar numele), butoanele si sumarul sesiunii', function () {
    [$user, $party] = bpWorking(bpParty('Latin Party', ['location_name' => 'Club Vibe']));
    $cola = bpItem('Cola', 10);

    Livewire::test(Home::class)
        ->assertSee('Latin Party')->assertDontSee('Club Vibe')->assertDontSee('25.09.2026')->assertDontSee('În desfășurare')
        ->assertSee('Vânzare')->assertSee('Raportare')
        ->assertSee('Nicio vânzare înregistrată');

    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 3]], [['method' => 'cash', 'amount' => 20], ['method' => 'card', 'amount' => 10]]);

    Livewire::test(Home::class)
        ->assertDontSee('Nicio vânzare înregistrată')
        ->assertSee('Bonuri')->assertSee('30,00 lei')->assertSee('20,00 lei')->assertSee('10,00 lei');
});

// ---- Vanzare ----------------------------------------------------------------------

it('vinde produse alese din select si adaugate rapid, cu plata cash', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $mojito = bpItem('Mojito', 25);

    Livewire::test(Sale::class)
        ->set('pick', (string) $cola->id)
        ->assertSet('pick', '')
        ->assertSet('cart', [$cola->id => 1])
        ->call('addItem', $mojito->id)->call('addItem', $mojito->id)
        ->call('stepQty', $cola->id, 2)
        ->assertSet('cart', [$cola->id => 3, $mojito->id => 2])
        ->call('payAll', 'cash')
        ->call('sell')
        ->assertSet('error', null)
        ->assertSet('cart', [])
        ->assertSee('Vânzare înregistrată')->assertSee('80,00');

    $sale = SaleModel::with('lines', 'payments', 'group')->sole();
    expect((float) $sale->total)->toBe(80.0)
        ->and($sale->source)->toBe('app')
        ->and($sale->created_by)->toBe($user->id)
        ->and($sale->bartender_id)->toBe($user->id)
        ->and($sale->customer_id)->toBeNull()
        ->and($sale->lines->pluck('qty', 'menu_item_id')->map(fn ($q) => (int) $q)->all())->toBe([$cola->id => 3, $mojito->id => 2])
        ->and($sale->payments->sole()->method)->toBe('cash')
        ->and($sale->group->party_id)->toBe($party->id)
        ->and($sale->group->isOpen())->toBeTrue();
});

it('cantitatea 0 scoate produsul din bon, iar produsele inactive nu se adauga', function () {
    bpWorking();
    $cola = bpItem('Cola', 10);
    $old = bpItem('Vechi', 5, false);

    Livewire::test(Sale::class)
        ->call('addItem', $cola->id)->call('stepQty', $cola->id, -1)->assertSet('cart', [])
        ->call('addItem', $old->id)->assertSet('cart', [])->assertSet('error', 'Produsul nu mai e disponibil.')
        ->call('addItem', $cola->id)->call('removeItem', $cola->id)->assertSet('cart', [])
        ->call('addItem', $cola->id)->call('clearCart')->assertSet('cart', []);
});

it('recomandarile: cele mai vandute produse active, in ordinea vanzarilor', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $mojito = bpItem('Mojito', 25);
    $bere = bpItem('Bere', 12);
    $vechi = bpItem('Vechi', 5, false);
    $nevandut = bpItem('Nevândut', 7);

    bpSell($party, $user, [['menu_item_id' => $mojito->id, 'qty' => 5], ['menu_item_id' => $bere->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 137]]);
    bpSell($party, $user, [['menu_item_id' => $bere->id, 'qty' => 2], ['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 34]]);
    $sold = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 50]], [['method' => 'cash', 'amount' => 500]]);
    $sold->cancel($user->id, 'greșit'); // anulata: nu conteaza
    $vechi->update(['is_active' => true]);
    bpSell($party, $user, [['menu_item_id' => $vechi->id, 'qty' => 99]], [['method' => 'cash', 'amount' => 495]]);
    $vechi->update(['is_active' => false]);

    expect(MenuItem::topSellingIds())->toBe([$mojito->id, $bere->id, $cola->id]);

    $component = Livewire::test(Sale::class)->assertSee('Cele mai vândute');
    $html = $component->html();
    expect(strpos($html, 'wire:key="quick-'.$mojito->id.'"'))->toBeLessThan(strpos($html, 'wire:key="quick-'.$bere->id.'"'))
        ->and($html)->not->toContain('wire:key="quick-'.$nevandut->id.'"')
        ->and($html)->not->toContain('wire:key="quick-'.$vechi->id.'"');

    $component->call('addItem', $mojito->id)->assertSet('cart', [$mojito->id => 1]);
});

it('fara vanzari nu apar recomandari', function () {
    bpWorking();
    bpItem('Cola', 10);

    Livewire::test(Sale::class)->assertDontSee('Cele mai vândute');
});

it('plata mixta cash si card acopera exact totalul, iar restul se completeaza automat', function () {
    bpWorking();
    $mojito = bpItem('Mojito', 25);

    Livewire::test(Sale::class)
        ->call('addItem', $mojito->id)->call('addItem', $mojito->id)
        ->set('payments.0.amount', '30')
        ->call('addPayment')->set('payments.1.method', 'card')
        ->call('fillRemaining', 1)
        ->assertSet('payments.1.amount', '20')
        ->assertSee('Încasat complet')
        ->call('sell')->assertSet('error', null);

    $sale = SaleModel::with('payments')->sole();
    expect($sale->payments->pluck('amount', 'method')->map(fn ($a) => (float) $a)->all())->toBe(['cash' => 30.0, 'card' => 20.0]);
});

it('plata cu tokeni: tokenii intregi acopera totalul dupa curs', function () {
    [$user, $party] = bpWorking();
    Settings::set('token_rate', 5);
    $bere = bpItem('Bere', 15);

    Livewire::test(Sale::class)
        ->call('addItem', $bere->id)->call('addItem', $bere->id)
        ->call('payAll', 'token')
        ->assertSet('payments.0.tokens', '6')
        ->assertSee('1 token = 5,00 lei')
        ->call('sell')->assertSet('error', null);

    $payment = SaleModel::with('payments')->sole()->payments->sole();
    expect($payment->method)->toBe('token')->and((int) $payment->tokens)->toBe(6)->and((float) $payment->amount)->toBe(30.0);
});

it('respinge vanzarea cand plata nu acopera totalul, fara sa lase o sesiune goala deschisa', function () {
    bpWorking();
    $cola = bpItem('Cola', 10);

    Livewire::test(Sale::class)
        ->call('addItem', $cola->id)
        ->set('payments.0.amount', '4')
        ->call('sell')
        ->assertSee('nu acoperă exact totalul');

    expect(SaleModel::count())->toBe(0)->and(SalesGroup::count())->toBe(0);

    Livewire::test(Sale::class)->call('sell')->assertSet('error', 'Adaugă cel puțin un produs.');
});

it('metodele de plata sunt cele acceptate la petrecere', function () {
    bpWorking(bpParty('Doar cash', ['payment_methods' => ['cash']]));
    bpItem('Cola', 10);

    $html = Livewire::test(Sale::class)->html();

    expect($html)->toContain('Tot cu Cash')->and($html)->not->toContain('Tot cu Card')->and($html)->not->toContain('Tot cu Tokeni');
});

it('plata cu credite cere participant si debiteaza portofelul', function () {
    [$user, $party] = bpWorking();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $cola = bpItem('Cola', 10);
    $buyer = ParticipantRegistry::create('Ana Pop', '0722111333');
    CreditLedger::load($buyer, 50, CreditTransaction::SOURCE_MANUAL, $user->id, 'stoc initial test');

    Livewire::test(Sale::class)
        ->call('addItem', $cola->id)
        ->call('payAll', 'credit')
        ->call('sell')
        ->assertSee('necesită un participant');
    expect(SaleModel::count())->toBe(0);

    Livewire::test(Sale::class)
        ->call('addItem', $cola->id)
        ->call('addParticipant', $buyer->id)
        ->call('payAll', 'credit')
        ->call('sell')->assertSet('error', null);

    $sale = SaleModel::sole();
    expect($sale->customer_id)->toBe($buyer->id)->and($sale->identified_by)->toBe('phone')
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(40.0);
});

it('scanarea QR alege participantul si marcheaza identificarea prin QR', function () {
    bpWorking();
    $cola = bpItem('Cola', 10);
    $p = ParticipantRegistry::create('Ion Vasile', '0733444555');

    $component = Livewire::test(Sale::class);
    $component->call('scanParticipant', 'DXA:P:00000000-0000-4000-8000-000000000000')
        ->assertSet('participantIds', []);

    $component->call('scanParticipant', $p->qrPayload())
        ->assertSet('participantIds', [$p->id])
        ->assertSet('identifiedBy', 'qr')
        ->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell')->assertSet('error', null)
        ->assertSet('participantIds', [])->assertSet('identifiedBy', 'phone');

    $sale = SaleModel::sole();
    expect($sale->customer_id)->toBe($p->id)->and($sale->identified_by)->toBe('qr');
});

it('ecranul de vanzare are butonul de scanare QR', function () {
    bpWorking();

    Livewire::test(Sale::class)->assertSeeHtml('data-scan-button');
});

it('a doua vanzare reia sesiunea deschisa, nu o creeaza pe alta', function () {
    bpWorking();
    $cola = bpItem('Cola', 10);

    foreach ([1, 2] as $_) {
        Livewire::test(Sale::class)->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell')->assertSet('error', null);
    }

    expect(SalesGroup::count())->toBe(1)->and(SaleModel::count())->toBe(2)
        ->and(AdminActivityLog::where('action', 'sales.group_created')->count())->toBe(1);
});

// ---- Raportare --------------------------------------------------------------------

it('raportarea calculeaza cash-ul asteptat si diferenta din vanzarile sesiunii', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 5]], [['method' => 'cash', 'amount' => 30], ['method' => 'card', 'amount' => 20]]);
    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]])->cancel($user->id, 'greșit');

    Livewire::test(Report::class)
        ->assertSee('50,00 lei')
        ->set('opening_float', '100')
        ->set('handed_over', '20')
        ->set('handed_note', 'Predat administratorului')
        ->set('counted_cash', '105')
        ->assertSee('110,00')           // 100 + 30 - 20
        ->assertSee('−5,00 lei')        // 105 - 110
        ->call('save')->assertSet('error', null);

    $report = BarReport::sole();
    $fig = $report->figures();
    expect((float) $report->opening_float)->toBe(100.0)
        ->and($fig->cash_sales)->toBe(30.0)
        ->and($fig->expected_cash)->toBe(110.0)
        ->and($fig->cash_diff)->toBe(-5.0)
        ->and($fig->sales_count)->toBe(1)->and($fig->cancelled_count)->toBe(1)
        ->and($fig->revenue)->toBe(50.0)
        ->and($report->isSubmitted())->toBeFalse();
});

it('fara sesiune de vanzari deschisa, raportarea spune ca nu e nimic de raportat', function () {
    bpWorking();

    Livewire::test(Report::class)->assertSee('Nicio sesiune de vânzări deschisă');
    expect(BarReport::count())->toBe(0);
});

it('fondul de casa se propune din ultima raportare trimisa a petrecerii', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);

    $g1 = SalesGroup::openFor($party->id, $user->id);
    BarReport::startFor($g1, $user->id)->update(['counted_cash' => 250, 'handed_over' => 100, 'submitted_at' => now(), 'submitted_by' => $user->id]);
    $g1->close();

    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);

    Livewire::test(Report::class)->assertSet('opening_float', '150');
    expect(BarReport::suggestedFloat($party->id))->toBe(150.0)->and(BarReport::suggestedFloat(null))->toBeNull();
});

it('trimiterea cere cash numarat, cere confirmare si blocheaza vanzarea si anularea', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $sale = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 20]]);

    Livewire::test(Report::class)
        ->call('askSubmit')
        ->assertSet('confirming', false)
        ->assertSee('Introdu cash-ul numărat')
        ->set('counted_cash', '20')
        ->call('askSubmit')
        ->assertSet('confirming', true)
        ->assertSee('Trimiți raportarea?')
        ->call('cancelSubmit')->assertSet('confirming', false)
        ->call('askSubmit')
        ->call('submit')
        ->assertSet('confirming', false)
        ->assertSee('Raportare trimisă')
        ->assertDontSee('Trimite raportarea');

    $report = BarReport::sole();
    expect($report->isSubmitted())->toBeTrue()->and($report->submitted_by)->toBe($user->id);
    expect(AdminActivityLog::pluck('action')->all())->toContain('bar.report_submitted');

    // Vanzarea din aplicatie si din admin, plus anularea, sunt blocate.
    Livewire::test(Sale::class)
        ->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell')
        ->assertSee('Raportarea de bar a fost trimisă');
    expect(fn () => bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]))
        ->toThrow(DomainException::class, 'Raportarea de bar a fost trimisă');
    expect($sale->fresh()->canBeCancelled())->toBeFalse();
    expect(fn () => $sale->fresh()->cancel($user->id, 'motiv'))->toThrow(DomainException::class, 'Raportarea de bar a fost trimisă');
    expect(SaleModel::count())->toBe(1);

    // O raportare trimisa nu se mai modifica din aplicatie.
    Livewire::test(Report::class)->set('counted_cash', '5')->call('save')->assertSee('deja trimisă');
    expect((float) $report->fresh()->counted_cash)->toBe(20.0);

    Livewire::test(Home::class)->assertSee('Trimisă, așteaptă adminul');
});

it('adminul redeschide raportarea trimisa din Vanzari, iar barul poate vinde din nou', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $sale = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 2]], [['method' => 'cash', 'amount' => 20]]);
    $report = BarReport::startFor($sale->group, $user->id);
    $report->update(['counted_cash' => 20]);
    $report->submit($user->id);

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(SalesIndex::class)
        ->assertSee('Raportare trimisă')->assertSee('numărat 20,00 lei')
        ->call('askReopen', $sale->group->id)->assertSet('confirmingReopenId', $sale->group->id)
        ->assertSee('Redeschizi raportarea de bar?')
        ->call('cancelReopen')->assertSet('confirmingReopenId', null)
        ->call('askReopen', $sale->group->id)
        ->call('reopenReport')
        ->assertSet('confirmingReopenId', null)
        ->assertDontSee('Raportare trimisă');

    expect($report->fresh()->isSubmitted())->toBeFalse()
        ->and($sale->fresh()->canBeCancelled())->toBeTrue();
    expect(AdminActivityLog::pluck('action')->all())->toContain('bar.report_reopened');

    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
    expect(SaleModel::count())->toBe(2);
});

it('redeschiderea cere o raportare trimisa', function () {
    [$user, $party] = bpWorking();
    $report = BarReport::startFor(SalesGroup::openFor($party->id, $user->id), $user->id);

    expect(fn () => $report->reopen($user->id))->toThrow(DomainException::class, 'nu este în starea');
});

it('raportarea unei sesiuni inchise nu se mai porneste', function () {
    [$user, $party] = bpWorking();
    $group = SalesGroup::openFor($party->id, $user->id);
    $group->close();

    expect(fn () => BarReport::startFor($group->fresh(), $user->id))->toThrow(DomainException::class, 'închisă');
});

it('sumele din raportare accepta virgula si resping valori invalide', function () {
    [$user, $party] = bpWorking();
    bpSell($party, $user, [['menu_item_id' => bpItem('Cola', 10)->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);

    Livewire::test(Report::class)
        ->set('counted_cash', '12,50')->call('save')->assertSet('error', null);
    expect((float) BarReport::sole()->counted_cash)->toBe(12.5);

    Livewire::test(Report::class)
        ->set('counted_cash', 'abc')->call('save')
        ->assertSee('trebuie să fie o sumă');
});

it('paginile barului se randeaza complet (HTTP) cu identitatea barului, nu a receptiei', function () {
    Settings::set(BarApp::KEY_NAME, 'Bar DXA');
    Settings::set(ReceptionApp::KEY_NAME, 'Casa DXA');
    $party = bpParty();
    $this->actingAs(bpUser(), 'admin')->withSession([BarApp::PARTY_SESSION_KEY => $party->id]);

    foreach (['bar.home', 'bar.party', 'bar.sale', 'bar.recent', 'bar.report'] as $name) {
        $this->get(route($name))->assertOk()
            ->assertSee('Bar DXA')->assertDontSee('Casa DXA')
            ->assertSee(route('bar.manifest'), false)
            ->assertSee('/bar/', false)
            ->assertDontSee(route('receptie.manifest'), false);
    }

    $this->get(route('bar.home'))->assertSee('name="to" value="bar"', false)->assertSee('scope: \'/bar/\'', false);
    $this->get(route('receptie.home'))->assertForbidden();   // fara bifa recepție
});

it('login-ul barului se randeaza cu identitatea barului', function () {
    Settings::set(BarApp::KEY_NAME, 'Bar DXA');

    $this->get(route('bar.login'))->assertOk()->assertSee('Bar DXA')->assertSee(route('bar.manifest'), false);
});

it('recepția își păstrează identitatea în layout după generalizare', function () {
    Settings::set(ReceptionApp::KEY_NAME, 'Casa DXA');
    $this->actingAs(bpUser(['access_reception' => true, 'access_bar' => false]), 'admin')
        ->withSession([ReceptionApp::PARTY_SESSION_KEY => bpParty()->id]);

    $this->get(route('receptie.home'))->assertOk()->assertSee('Casa DXA')->assertSee(route('receptie.manifest'), false)
        ->assertSee('name="to" value="receptie"', false)->assertDontSee(route('bar.manifest'), false);
});

// ---- Runda 2: credite in picker, top metode, tokeni ------------------------------

it('in aplicatie participantul aratat are credite (nu intrari) si nu exista participant nou', function () {
    [$user, $party] = bpWorking();
    bpItem('Cola', 10);
    $buyer = ParticipantRegistry::create('Ana Pop', '0722111333');
    CreditLedger::load($buyer, 25, CreditTransaction::SOURCE_MANUAL, $user->id, 'test');

    $html = Livewire::test(Sale::class)->call('addParticipant', $buyer->id)->html();

    expect($html)->toContain('25,00 lei credite')->and($html)->not->toContain('intrări')->and($html)->not->toContain('Participant nou');
});

it('sugereaza doar cele 2 metode de plata cele mai folosite', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'card', 'amount' => 10]]);
    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'card', 'amount' => 10]]);
    bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);

    $c = Livewire::test(Sale::class)->call('addItem', $cola->id);
    $html = $c->html();
    expect($html)->toContain('Tot cu Card')->and($html)->toContain('Tot cu Cash')->and($html)->not->toContain('Tot cu Tokeni');
});

it('totalul bonului arata echivalentul in tokeni', function () {
    bpWorking();
    Settings::set('token_rate', 5);
    $bere = bpItem('Bere', 15);

    Livewire::test(Sale::class)->call('addItem', $bere->id)->assertSee('≈ 3 tokeni');
});

it('raportarea barului numara tokenii primiti si arata diferenta', function () {
    [$user, $party] = bpWorking();
    Settings::set('token_rate', 5);
    $bere = bpItem('Bere', 15);
    bpSell($party, $user, [['menu_item_id' => $bere->id, 'qty' => 2]], [['method' => 'token', 'tokens' => 6, 'amount' => 30]]);

    Livewire::test(Report::class)
        ->assertSee('Tokeni primiți ca plată')
        ->set('counted_tokens', '5')->assertSee('Diferență tokeni')->assertSee('−1')
        ->set('counted_cash', '0')->call('askSubmit')->call('submit')->assertSet('error', null);

    $report = BarReport::sole();
    expect($report->counted_tokens)->toBe(5);
    $fig = $report->figures();
    expect($fig->tokens_received)->toBe(6)->and($fig->tokens_diff)->toBe(-1);

    Livewire::test(Report::class)->set('counted_tokens', 'abc')->call('save')->assertSet('error', fn ($e) => $e !== null);
});

// ---- Runda 3: Recente + anulare, protectie la retrimitere, admin (raportari, reconciliere, live) ----

it('Vanzari recente listeaza bonurile, anuleaza cu motiv si returneaza creditele', function () {
    [$user, $party] = bpWorking();
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    $cola = bpItem('Cola', 10);
    $buyer = ParticipantRegistry::create('Ana Pop', '0722111333');
    CreditLedger::load($buyer, 50, CreditTransaction::SOURCE_MANUAL, $user->id, 'test');
    $sale = SaleRecorder::record(SalesGroup::openFor($party->id, $user->id), [['menu_item_id' => $cola->id, 'qty' => 2]], [['method' => 'credit', 'amount' => 20]], source: 'app', adminId: $user->id, participantId: $buyer->id, identifiedBy: 'qr');

    Livewire::test(Recent::class)
        ->assertSee('2 × Cola')->assertSee('20,00 lei')->assertSee('Anulează')
        ->call('cancelSale', $sale->id, 'x')->assertSee('Scrie motivul anulării')
        ->call('cancelSale', $sale->id, 'client greșit')
        ->assertSee('Bonul a fost anulat')->assertSee('Anulat: client greșit');

    expect($sale->fresh()->isCancelled())->toBeTrue()
        ->and((float) CreditLedger::balance($buyer->fresh()))->toBe(50.0);
    expect(AdminActivityLog::pluck('action')->all())->toContain('sales.sale_cancelled');
});

it('din Vanzari recente nu se anuleaza bonul altei sesiuni sau dupa trimiterea raportarii', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $other = bpParty('Alta petrecere');
    $foreign = bpSell($other, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
    $mine = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);

    Livewire::test(Recent::class)->call('cancelSale', $foreign->id, 'motiv')->assertSee('nu aparține sesiunii');
    expect($foreign->fresh()->isCancelled())->toBeFalse();

    $report = BarReport::startFor($mine->group, $user->id);
    $report->update(['counted_cash' => 10]);
    $report->submit($user->id);

    Livewire::test(Recent::class)->assertSee('bonurile nu se mai pot anula')->assertDontSee('x-on:click="ask(', false)
        ->call('cancelSale', $mine->id, 'motiv')->assertSee('Raportarea de bar a fost trimisă');
    expect($mine->fresh()->isCancelled())->toBeFalse();
});

it('SubmitGuard rulează o încercare o singură dată, iar o respingere nu consumă cheia', function () {
    $n = 0;
    SubmitGuard::run('k1', function () use (&$n) {
        $n++;
    });
    expect(fn () => SubmitGuard::run('k1', function () use (&$n) {
        $n++;
    }))->toThrow(DomainException::class, 'deja înregistrată');
    expect($n)->toBe(1);

    expect(fn () => SubmitGuard::run('k2', fn () => throw new DomainException('respins')))->toThrow(DomainException::class, 'respins');
    SubmitGuard::run('k2', function () use (&$n) {
        $n++;
    });
    expect($n)->toBe(2);
});

it('vanzarea din bar refuza retrimiterea aceleiasi incercari si isi reinnoieste cheia dupa succes', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);

    $c = Livewire::test(Sale::class)->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell');
    $c->assertSet('attempt', '');
    expect(SaleModel::count())->toBe(1);

    // Răspuns pierdut: clientul retrimite aceeași încercare (aceeași cheie).
    $key = (string) Str::uuid();
    $t = Livewire::test(Sale::class)->set('attempt', $key)->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell')->assertSet('error', null);
    expect(SaleModel::count())->toBe(2);
    $t->set('attempt', $key)->call('addItem', $cola->id)->call('payAll', 'cash')->call('sell')->assertSee('deja înregistrată');
    expect(SaleModel::count())->toBe(2);
});

it('admin: lista raportarilor de bar arata ce asteapta adminul, detaliu, PDF si redeschidere', function () {
    [$user, $party] = bpWorking();
    Settings::set('token_rate', 5);
    $bere = bpItem('Bere', 15);
    $sale = bpSell($party, $user, [['menu_item_id' => $bere->id, 'qty' => 2]], [['method' => 'token', 'tokens' => 6, 'amount' => 30]]);
    $report = BarReport::startFor($sale->group, $user->id);
    $report->update(['counted_cash' => 0, 'counted_tokens' => 6]);
    $report->submit($user->id);

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(BarReportsIndex::class)
        ->assertSee('Așteaptă adminul')->assertSee($report->title())->assertSee('tokeni')
        ->set('status', 'draft')->assertDontSee($report->title())
        ->set('status', 'awaiting')->assertSee($report->title())
        ->call('askReopen', $report->id)->assertSee('Redeschizi raportarea?')
        ->call('reopenReport')->assertSee('a fost redeschisă');
    expect($report->fresh()->isSubmitted())->toBeFalse();

    $report->submit($user->id);
    Livewire::test(BarReportShow::class, ['report' => $report])->assertSee('Primiți ca plată')->assertSee('Încasări pe metodă');

    $this->get(route('admin.bar.reports.index'))->assertOk()->assertSee('data-');
    $this->get(route('admin.bar.reports.show', $report))->assertOk();
    $pdf = $this->get(route('admin.bar.reports.pdf', $report));
    $pdf->assertOk();
    expect($pdf->headers->get('content-disposition'))->toContain('raportare-bar-'.$report->id)->toContain('.pdf');

    // Sidebar: insigna cu numărul de raportări în așteptare.
    expect(BarReport::awaitingAdmin()->count())->toBe(1);
});

it('PDF-ul unei raportari de bar in lucru nu se exporta', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $sale = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
    $report = BarReport::startFor($sale->group, $user->id);

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');
    $this->get(route('admin.bar.reports.pdf', $report))->assertNotFound();
});

it('reconcilierea aduna recepția și barul pe petrecere, iar statisticile live merg fără raportare de stoc', function () {
    [$user, $party] = bpWorking();
    Settings::set('token_rate', 5);
    $bere = bpItem('Bere', 15);
    $sale = bpSell($party, $user, [['menu_item_id' => $bere->id, 'qty' => 2]], [['method' => 'token', 'tokens' => 6, 'amount' => 30]]);
    bpSell($party, $user, [['menu_item_id' => $bere->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 15]]);
    $report = BarReport::startFor($sale->group, $user->id);
    $report->update(['counted_cash' => 15, 'counted_tokens' => 5]);
    $report->submit($user->id);

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(ReconciliationIndex::class)->assertSet('party', (string) $party->id)
        ->assertSee('Bilanțul serii')->assertSee($party->name)->assertSee($report->title())
        ->assertSee('primiți la bar 6')->assertSee('Nicio raportare de recepție');
    $this->get(route('admin.reconciliation'))->assertOk()->assertSee('Raportări casă')->assertSee('Raportări stoc')->assertSee('scrollTop', false);

    Livewire::test(BarStats::class, ['tab' => 'party'])->assertSet('tab', 'party')
        ->assertSee('Pe petrecere')->assertSee('Vânzări pe oră')->assertSee($party->name);
    Livewire::test(PartyLive::class)->assertSet('party', (string) $party->id)
        ->assertSee('Vânzări pe oră')->assertSee('Bere')->assertSee('3 buc')->assertSee('45,00');
    $this->get(route('admin.bar.stats', ['tab' => 'party']))->assertOk()->assertSee('Vânzări pe oră');
});

it('raportarea de stoc aduce numaratoarea din raportarea de casa trimisa de barman si scade banii scosi', function () {
    [$user, $party] = bpWorking();
    Settings::set('uses_tokens', true);
    $cola = bpItem('Cola', 10);
    $sale = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 5]], [['method' => 'cash', 'amount' => 50]]);
    $report = BarReport::startFor($sale->group, $user->id);
    $report->update(['opening_float' => 100, 'handed_over' => 30, 'counted_cash' => 120, 'counted_tokens' => 4]);
    $report->submit($user->id);

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(StockReportForm::class)
        ->set('party_id', (string) $party->id)
        ->assertSet('sales_group_id', (string) $sale->group->id)
        ->assertSet('opening_float', '100')
        ->assertSet('handed_over', '30')
        ->assertSet('counted_cash', '120')
        ->assertSet('counted_tokens', '4')
        ->assertSet('broughtFromBar', true)
        ->assertSee('adusă din raportarea de casă')
        ->assertSee('scos din casă');

    $draft = StockReport::sole();
    expect((float) $draft->handed_over)->toBe(30.0)->and((float) $draft->counted_cash)->toBe(120.0)->and($draft->counted_tokens)->toBe(4);
});

it('raportarea de stoc nu suprascrie ce a completat adminul si nu aduce o raportare netrimisa', function () {
    [$user, $party] = bpWorking();
    $cola = bpItem('Cola', 10);
    $sale = bpSell($party, $user, [['menu_item_id' => $cola->id, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
    $report = BarReport::startFor($sale->group, $user->id);
    $report->update(['opening_float' => 100, 'counted_cash' => 110]);   // netrimisa

    $this->actingAs(bpUser(['role' => 'superadmin', 'access_admin' => true]), 'admin');

    Livewire::test(StockReportForm::class)->set('party_id', (string) $party->id)
        ->assertSet('counted_cash', '')->assertSet('broughtFromBar', false);
});
