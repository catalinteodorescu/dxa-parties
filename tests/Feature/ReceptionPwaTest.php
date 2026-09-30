<?php

use App\Livewire\Admin\ReceptionApp\AppSettings;
use App\Livewire\Admin\ReceptionReports\Form as ReportAdminForm;
use App\Livewire\Reception\CreditSale;
use App\Livewire\Reception\Entry;
use App\Livewire\Reception\Home;
use App\Livewire\Reception\Login;
use App\Livewire\Reception\PartyPicker;
use App\Livewire\Reception\Recent;
use App\Livewire\Reception\Report;
use App\Livewire\Reception\TokenSale;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\CreditTransaction;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\TokenTransaction;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\ReceptionStats;
use App\Support\PaymentMethods;
use App\Support\ReceptionApp;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (PWA Recepție - Etapa 1). Manifest + service worker, login/acces pe /receptie, pagina Acasă
 * (alegerea petrecerii, rezumatul sesiunii) și pagina „Nu ai acces” (403) fără delogare.
 */
function rpUser(array $attrs = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Recepționer '.random_int(1, 99999),
        'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin',
        'is_active' => true,
        'access_admin' => false,
        'access_reception' => true,
        'password' => 'secret-pass',
    ], $attrs));
}

function rpParty(string $name, string $date = '2026-10-03', array $extra = []): Party
{
    return Party::create(array_merge([
        'name' => $name,
        'kind' => 'basic',
        'start_date' => $date,
        'start_time' => '21:00',
        'end_time' => '03:00',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
        'payment_methods' => ['cash', 'card'],
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
    ], $extra));
}

it('serveste manifestul PWA cu scope-ul /receptie/ si iconitele dinamice', function () {
    $json = $this->get(route('receptie.manifest'))->assertOk()->json();

    expect($json['start_url'])->toBe('/receptie/')
        ->and($json['scope'])->toBe('/receptie/')
        ->and($json['display'])->toBe('standalone')
        ->and($json['name'])->toBe('Recepție')
        ->and(collect($json['icons'])->pluck('sizes')->all())->toBe(['192x192', '512x512'])
        ->and($json['icons'][0]['src'])->toContain('/receptie/icon/192.png');
});

it('manifestul urmează numele și tema din setările aplicației', function () {
    Settings::set(ReceptionApp::KEY_NAME, 'Casa DXA');
    Settings::set(ReceptionApp::KEY_THEME, 'indigo');

    $json = $this->get(route('receptie.manifest'))->json();

    expect($json['name'])->toBe('Casa DXA')
        ->and($json['theme_color'])->toBe(ReceptionApp::primary());
});

it('iconița e un PNG cu gradient, iar versiunea ei se schimbă odată cu tema', function () {
    Storage::fake('local');
    Settings::set(ReceptionApp::KEY_THEME, 'emerald');
    $v1 = ReceptionApp::iconVersion();

    $r = $this->get(route('receptie.icon', ['size' => 192]))->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('image/png');
    $img = imagecreatefromstring($r->getContent());
    expect(imagesx($img))->toBe(192);
    // colțuri opuse au culori diferite = gradient
    expect(imagecolorat($img, 4, 4))->not->toBe(imagecolorat($img, 187, 187));

    Settings::set(ReceptionApp::KEY_THEME, 'indigo');
    expect(ReceptionApp::iconVersion())->not->toBe($v1);

    $this->get('/receptie/icon/999.png')->assertNotFound();
});

it('serveste service worker-ul ca javascript', function () {
    $r = $this->get(route('receptie.sw'))->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('javascript')
        ->and($r->getContent())->toContain('addEventListener(\'fetch\'');
});

it('trimite vizitatorul nelogat la login-ul de recepție, iar în admin rămâne login-ul admin', function () {
    $this->get('/receptie')->assertRedirect(route('receptie.login'));
    $this->get(route('receptie.login'))->assertOk()->assertSee('Recepție');
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('login-ul de recepție refuză un cont fără bifa recepție, cu parola corectă', function () {
    rpUser(['phone' => '0722555111', 'access_reception' => false, 'access_bar' => true]);

    Livewire::test(Login::class)
        ->set('phone', '0722555111')->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors(['phone'])
        ->assertSee('nu are acces la această aplicație');

    expect(AdminActivityLog::pluck('action')->all())->toContain('admin.login.blocked_no_access');
});

it('un recepționer intră în aplicație, dar nu în panoul admin (403, rămâne logat)', function () {
    $user = rpUser();
    $this->actingAs($user, 'admin');

    $this->get(route('receptie.party'))->assertOk();

    $this->get(route('admin.dashboard'))->assertForbidden()->assertSee('Mergi la aplicația de recepție');
    $this->get(route('receptie.party'))->assertOk();
});

it('un cont fără recepție primește 403 pe /receptie și link către panou', function () {
    $this->actingAs(rpUser(['access_admin' => true, 'access_reception' => false]), 'admin');

    $this->get(route('receptie.home'))->assertForbidden()->assertSee('Mergi la panoul admin');
});

it('superadmin-ul are acces la recepție fără bife', function () {
    $this->actingAs(rpUser(['role' => 'superadmin', 'access_reception' => false]), 'admin');

    $this->get(route('receptie.party'))->assertOk();
});

it('după login petrecerea se alege pe un ecran separat, doar din cele permise', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $live = rpParty('Petrecere Live');
    $later = rpParty('Petrecere Viitoare', '2026-10-10');
    rpParty('Petrecere Încheiată', '2026-09-01');
    rpParty('Petrecere Ciornă', '2026-10-05', ['status' => 'draft']);
    $this->actingAs(rpUser(), 'admin');

    Livewire::test(PartyPicker::class)
        ->assertSee('Petrecere Live')->assertSee('În desfășurare')->assertSee('Petrecere Viitoare')
        ->assertDontSee('Petrecere Încheiată')->assertDontSee('Petrecere Ciornă')
        ->call('choose', $later->id)
        ->assertRedirect(route('receptie.home'));

    expect(session('receptie.party_id'))->toBe($later->id);

    Livewire::test(Home::class)->assertSet('partyId', $later->id)->assertSee('Petrecere selectată')->assertSee('Petrecere Viitoare')->assertSee('Schimbă')
        ->assertDontSee('Petrecere Live');
});

it('nu se poate alege o petrecere încheiată', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    rpParty('Bună');
    $old = rpParty('Trecută', '2026-09-01');
    $this->actingAs(rpUser(), 'admin');

    Livewire::test(PartyPicker::class)->call('choose', $old->id)->assertNotFound();
    expect(session('receptie.party_id'))->toBeNull();
});

it('fără petrecere aleasă, Acasă și Intrare trimit la ecranul de alegere', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    rpParty('Bună');
    $this->actingAs(rpUser(), 'admin');

    Livewire::test(Home::class)->assertRedirect(route('receptie.party'));
    Livewire::test(Entry::class)->assertRedirect(route('receptie.party'));
});

it('ecranul de petrecere se deschide, iar antetul nu are link către panoul admin', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $p = rpParty('Bună');
    $this->actingAs(rpUser(['access_admin' => true]), 'admin');

    $this->get(route('receptie.party'))->assertOk()->assertSee('Alege petrecerea');

    $this->withSession(['receptie.party_id' => $p->id])->get(route('receptie.home'))
        ->assertOk()
        ->assertDontSee(route('admin.dashboard'), false)
        ->assertSee('rc-glass', false);
});

it('Acasă arată rezumatul sesiunii deschise și mesaj gol când nu e nimic înregistrat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Rezumat');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    session(['receptie.party_id' => $party->id]);

    Livewire::test(Home::class)->assertSee('Nicio operațiune înregistrată încă');

    EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 60]], adminId: $user->id);
    expect(ReceptionSession::currentFor($party->id))->not->toBeNull()
        ->and(PartyEntry::count())->toBe(2);

    Livewire::test(Home::class)
        ->assertSee('Sesiunea de azi')
        ->assertSee('60,00 lei')
        ->assertDontSee('Nicio operațiune înregistrată încă');
});

it('Acasă ascunde acțiunile de tokeni și credite când nu sunt disponibile', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $p = rpParty('Petrecere Acțiuni');
    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $p->id]);

    PaymentMethods::setActive(PaymentMethods::CREDIT, false);
    Livewire::test(Home::class)->assertSee('Intrare')->assertDontSee('Vânzare credite');

    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);
    Livewire::test(Home::class)->assertSee('Vânzare credite');
});

it('deconectarea comună duce la login-ul aplicației de recepție', function () {
    $this->actingAs(rpUser(), 'admin');

    $this->post(route('session.logout'), ['to' => 'receptie'])->assertRedirect(route('receptie.login'));
    $this->assertGuest('admin');
});

it('scope-ul forReception exclude ciornele, inactivele și petrecerile încheiate', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    rpParty('Bună');
    rpParty('Ciornă', '2026-10-03', ['status' => 'draft']);
    rpParty('Inactivă', '2026-10-03', ['is_active' => false]);
    rpParty('Trecută', '2026-09-01');

    expect(Party::forReception()->pluck('name')->all())->toBe(['Bună']);
});

it('pagina de setări a aplicației de recepție salvează tema, numele și logo-ul', function () {
    Storage::fake('public');
    $this->actingAs(rpUser(['role' => 'superadmin']), 'admin');

    $this->get(route('admin.settings.reception-app'))->assertOk()->assertSee('Recepție');

    Livewire::test(AppSettings::class)
        ->call('selectTheme', 'indigo')
        ->set('name', 'Casa DXA')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    expect(ReceptionApp::themeKey())->toBe('indigo')
        ->and(ReceptionApp::name())->toBe('Casa DXA')
        ->and(ReceptionApp::uploadedLogoPath())->not->toBeNull();
    expect(AdminActivityLog::pluck('action')->all())->toContain('settings.reception_app_updated');

    Livewire::test(AppSettings::class)->call('removeLogo')->call('save');
    expect(ReceptionApp::uploadedLogoPath())->toBeNull();
});

it('setările aplicației refuză nume prea lung și fișiere care nu sunt imagini', function () {
    Storage::fake('public');
    $this->actingAs(rpUser(['role' => 'superadmin']), 'admin');

    Livewire::test(AppSettings::class)
        ->set('name', str_repeat('a', 41))
        ->set('logoUpload', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['name', 'logoUpload']);
});

it('un recepționer fără drept de admin nu deschide setările aplicației din panou', function () {
    $this->actingAs(rpUser(), 'admin');

    $this->get(route('admin.settings.reception-app'))->assertForbidden();
});

it('Intrare înregistrează o intrare plătită cu numerar', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Intrare');
    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $party->id]);

    Livewire::test(Entry::class)
        ->assertSee('Intrare')->assertSee('Bilet')
        ->set('count', 2)
        ->call('payAll', 'cash')
        ->call('save')
        ->assertSet('error', null)
        ->assertSee('Intrare înregistrată');

    expect(PartyEntry::count())->toBe(2)
        ->and((float) PartyEntry::sum('price_paid'))->toBe(60.0);
});

it('în Intrare, câmpul de test „Cod de reducere" arată reducerea, refuză codurile invalide și înregistrează prețul cu cod', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Cod');
    PartyDiscountCode::create(['party_id' => $party->id, 'code' => 'ANA10', 'type' => 'percent', 'value' => 10, 'promoter' => 'Ana', 'is_active' => true]);
    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $party->id]);

    $c = Livewire::test(Entry::class)->assertSee('Cod de reducere')->assertSee('doar test');

    // Cod inexistent: motivul apare, prețul rămâne cel întreg.
    $c->set('discountCode', 'nuexista')->assertSee('nu există')->assertSet('codeError', 'Codul de reducere nu există la această petrecere.');

    // Cod valid (litere mici, cu spații): reducerea se vede, iar totalul include codul.
    $c->set('discountCode', ' ana10 ')->assertSet('codeError', null)->assertSee('Cod aplicat')->assertSee('27,00')
        ->call('payAll', 'cash')->call('save')->assertSet('error', null)->assertSee('Intrare înregistrată')
        ->assertSet('discountCode', '');

    $e = PartyEntry::first();
    expect((float) $e->price_paid)->toBe(27.0)->and((float) $e->list_price)->toBe(30.0)->and((float) $e->discount_amount)->toBe(3.0)
        ->and($e->discount_code_id)->not->toBeNull();

    // Fără cod, ca înainte.
    Livewire::test(Entry::class)->call('payAll', 'cash')->call('save')->assertSet('error', null);
    expect((float) PartyEntry::latest('id')->first()->price_paid)->toBe(30.0);
});

it('în Intrare doar un admin poate schimba prețul', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Preț');

    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $party->id]);
    Livewire::test(Entry::class)->assertDontSee('Schimb prețul')
        ->set('override', true)->set('overridePrice', '10')->set('overrideReason', 'x')
        ->call('payAll', 'cash')->call('save')
        ->assertSet('error', 'Doar un admin poate schimba prețul.');
    expect(PartyEntry::count())->toBe(0);

    $this->actingAs(rpUser(['access_admin' => true]), 'admin');
    Livewire::test(Entry::class)->assertSee('Schimb prețul')
        ->set('override', true)->set('overridePrice', '10')->set('overrideReason', 'invitat')
        ->call('payAll', 'cash')->call('save')
        ->assertSet('error', null);
    expect((float) PartyEntry::sum('price_paid'))->toBe(10.0);
});

it('Intrare arată după salvare un ecran de confirmare pe tot ecranul, nu un mesaj mic', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Confirmare');
    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $party->id]);

    Livewire::test(Entry::class)
        ->set('count', 2)->call('payAll', 'cash')->call('save')
        ->assertSet('done.count', 2)
        ->assertSee('Intrare înregistrată')->assertSee('60,00 lei')
        ->call('dismissDone')
        ->assertSet('done', null)->assertDontSee('Intrare înregistrată');
});

it('butoanele „Tot cu…” sunt doar cele mai folosite 2 metode, după istoric', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Top', extra: []);
    $user = rpUser();
    $this->actingAs($user, 'admin');

    // card folosit de 2 ori, numerar o dată
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'card', 'amount' => 30]], adminId: $user->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'card', 'amount' => 30]], adminId: $user->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $user->id);

    $usage = ReceptionStats::entryMethodUsage();
    expect($usage->pluck('method')->all())->toBe(['card', 'cash'])
        ->and($usage->first()->count)->toBe(2);

    $all = ['cash' => 'Numerar', 'card' => 'Card', 'transfer' => 'Transfer', 'voucher' => 'Voucher'];
    $top = EntryRecorder::topMethods($all);
    expect(array_keys($top))->toBe(['card', 'cash'])
        ->and(EntryRecorder::topMethods($all, 3))->toHaveCount(3);
});

it('statisticile din admin arată metodele de plată de la intrări', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Stat');
    $user = rpUser(['role' => 'superadmin']);
    $this->actingAs($user, 'admin');
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'card', 'amount' => 30]], adminId: $user->id);

    $this->get(route('admin.reception.stats'))->assertOk()->assertSee('Metode de plată la intrări')->assertSee('buton rapid');
});

it('loginul are fundal colorat, iar antetul de alegere a petrecerii e pe fundal colorat', function () {
    $this->get(route('receptie.login'))->assertOk()->assertSee('bg-primary text-white', false);

    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    rpParty('Bună');
    $this->actingAs(rpUser(), 'admin');
    $this->get(route('receptie.party'))->assertOk()->assertSee('bg-primary text-white', false);
});

it('setările aplicației de recepție sunt în meniul Setări, sub Setări generale', function () {
    $this->actingAs(rpUser(['role' => 'superadmin']), 'admin');

    $html = $this->get(route('admin.settings.index'))->assertOk()->getContent();
    expect(strpos($html, 'Aplicație recepție'))->toBeGreaterThan(strpos($html, 'Setări generale'));
});

function rpReady(Party $party): void
{
    PaymentMethods::setTokenMode('active');
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);
    session(['receptie.party_id' => $party->id]);
}

it('ecranele de vânzare au în antet casuța Acasă și butoane spre celelalte vânzări', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Butoane');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    Livewire::test(Entry::class)
        ->assertSee(route('receptie.home'), false)->assertSee(route('receptie.tokens'), false)->assertSee(route('receptie.credits'), false)
        ->assertDontSee('‹ Acasă')->assertDontSee('identificați');

    // pe fiecare ecran lipsește butonul spre el însuși, dar există casuța și celelalte
    Livewire::test(TokenSale::class)->assertSee(route('receptie.home'), false)->assertSee(route('receptie.entry'), false)
        ->assertSee(route('receptie.credits'), false)->assertDontSee(route('receptie.tokens'), false)
        ->assertDontSee('1 token =');
    Livewire::test(CreditSale::class)->assertSee(route('receptie.home'), false)->assertSee(route('receptie.entry'), false)
        ->assertSee(route('receptie.tokens'), false)->assertDontSee(route('receptie.credits'), false)
        ->assertDontSee('1 credit =')->assertDontSee('portofelul lui');
});

it('în alegerea petrecerii nu mai e textul de sub titlu, iar petrecerea selectată e evidențiată', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $p = rpParty('Bună');
    $this->actingAs(rpUser(), 'admin');
    session(['receptie.party_id' => $p->id]);

    Livewire::test(PartyPicker::class)->assertDontSee('La ce petrecere')->assertSee('bg-primary-dark', false);
});

it('ecranul de succes al intrării are butoanele Tokeni și Credite și butonul Gata', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Succes');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    $html = Livewire::test(Entry::class)->call('payAll', 'cash')->call('save')->assertSee('Intrare înregistrată')->html();

    // Tokeni și Credite pe aceeași linie (grid cu 2 coloane), butonul Gata dedesubt
    $pos = fn (string $re) => preg_match($re, $html, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
    expect($html)->toContain('grid grid-cols-2')
        ->and($pos('/Tokeni\s*<\/a>/'))->not->toBeFalse()
        ->and($pos('/Tokeni\s*<\/a>/'))->toBeLessThan($pos('/Credite\s*<\/a>/'))
        ->and($pos('/Credite\s*<\/a>/'))->toBeLessThan($pos('/>Gata</'));
});

it('un singur participant din intrare apare ca sugestie la credite și tokeni, nu precompletat', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Sugestie Unică');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $p = ParticipantRegistry::create('Ana Test', '0722111222');

    Livewire::test(Entry::class)->call('addParticipant', $p->id)->call('payAll', 'cash')->call('save');

    Livewire::test(CreditSale::class)->assertSet('participantIds', [])->assertSet('quickPickIds', [$p->id])
        ->assertSee('Ana Test')
        ->call('pickQuickParticipant', $p->id)->assertSet('participantIds', [$p->id]);
    Livewire::test(TokenSale::class)->assertSet('participantIds', [])->assertSet('quickPickIds', [$p->id]);
});

it('mai mulți participanți din intrare apar ca sugestii, iar un tap îl alege', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Sugestii');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $a = ParticipantRegistry::create('Ana Sugestie', '0722333444');
    $b = ParticipantRegistry::create('Bogdan Sugestie', '0722555666');

    Livewire::test(Entry::class)->set('count', 2)->call('addParticipant', $a->id)->call('addParticipant', $b->id)
        ->call('payAll', 'cash')->call('save');

    Livewire::test(CreditSale::class)
        ->assertSet('participantIds', [])
        ->assertSee('Ana Sugestie')->assertSee('Bogdan Sugestie')
        ->call('pickQuickParticipant', $b->id)
        ->assertSet('participantIds', [$b->id])->assertSet('quickPickIds', []);
});

it('fără intrare recentă (sau după 20 de minute) nu se precompletează niciun participant', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Expirat');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $p = ParticipantRegistry::create('Ana Expirat', '0722777888');

    Livewire::test(CreditSale::class)->assertSet('participantIds', []);

    Livewire::test(Entry::class)->call('addParticipant', $p->id)->call('payAll', 'cash')->call('save');
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:30:00'));
    Livewire::test(CreditSale::class)->assertSet('participantIds', []);
});

it('vânzarea de credite cere participant, apoi merge și arată confirmarea', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Credite');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $p = ParticipantRegistry::create('Ana Credite', '0722999000');

    Livewire::test(CreditSale::class)->set('amount', '50')->call('payAll', 'cash')->call('sell')
        ->assertSet('error', fn ($e) => str_contains((string) $e, 'Alege participantul'));

    Livewire::test(CreditSale::class)->call('addParticipant', $p->id)->set('amount', '50')->call('payAll', 'cash')->call('sell')
        ->assertSet('error', null)->assertSee('Credite vândute')->assertSee('50,00 lei');

    expect(CreditTransaction::where('type', CreditTransaction::LOAD)->where('source', CreditTransaction::SOURCE_RECEPTION)->count())->toBe(1);
});

it('vânzarea de tokeni merge fără participant și arată confirmarea', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Tokeni');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $rate = PaymentMethods::tokenRate();

    Livewire::test(TokenSale::class)->set('tokens', 10)->call('payAll', 'cash')->call('sell')
        ->assertSet('error', null)->assertSee('Tokeni vânduți')->assertSee(number_format(10 * $rate, 2, ',', '.').' lei');

    expect(TokenTransaction::where('type', TokenTransaction::SOLD)->count())->toBe(1);
});

it('Acasă are tile-uri active spre tokeni și credite', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Tile');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    Livewire::test(Home::class)->assertSee(route('receptie.tokens'), false)->assertSee(route('receptie.credits'), false)->assertDontSee('În curând');
});

it('Tranzacții arată intrările, tokenii și creditele sesiunii, cele mai noi primele', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:00:00'));
    $party = rpParty('Petrecere Operațiuni');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    $p = ParticipantRegistry::create('Ana Operații', '0722121212');

    EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 60]], adminId: $user->id);
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:10:00'));
    Livewire::test(TokenSale::class)->set('tokens', 10)->call('payAll', 'cash')->call('sell');
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:20:00'));
    Livewire::test(CreditSale::class)->call('addParticipant', $p->id)->set('amount', '40')->call('payAll', 'cash')->call('sell');

    $html = Livewire::test(Recent::class)->assertSee('2 × Bilet')->assertSee('10 tokeni')->assertSee('40,00 lei credite')->assertSee('Ana Operații')->html();
    expect(strpos($html, '40,00 lei credite'))->toBeLessThan(strpos($html, '10 tokeni'))
        ->and(strpos($html, '10 tokeni'))->toBeLessThan(strpos($html, '2 × Bilet'));
});

it('recepționerul anulează o intrare cu motiv, iar motivul e obligatoriu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:00:00'));
    $party = rpParty('Petrecere Anulare');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    $entries = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $user->id);
    $batch = $entries->first()->batch;

    Livewire::test(Recent::class)->call('cancelOperation', 'entry', $batch, '  ')
        ->assertSet('error', 'Spune de ce anulezi intrarea (motiv obligatoriu).');
    expect(PartyEntry::active()->count())->toBe(1);

    Livewire::test(Recent::class)->call('cancelOperation', 'entry', $batch, 'greșeală')
        ->assertSet('error', null)->assertSee('Intrarea a fost anulată')->assertSee('Anulat: greșeală');
    expect(PartyEntry::active()->count())->toBe(0);
});

it('recepționerul anulează o vânzare de credite și de tokeni', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:00:00'));
    $party = rpParty('Petrecere Anulare Vânzări');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);
    $p = ParticipantRegistry::create('Ana Anulare', '0722343434');

    Livewire::test(CreditSale::class)->call('addParticipant', $p->id)->set('amount', '40')->call('payAll', 'cash')->call('sell');
    Livewire::test(TokenSale::class)->set('tokens', 5)->call('payAll', 'cash')->call('sell');
    $credit = CreditTransaction::where('type', CreditTransaction::LOAD)->first();
    $token = TokenTransaction::where('type', TokenTransaction::SOLD)->first();
    expect(CreditLedger::balance($p->fresh()))->toBe(40.0);

    Livewire::test(Recent::class)
        ->call('cancelOperation', 'credit', (string) $credit->id, 'client răzgândit')->assertSet('error', null)
        ->call('cancelOperation', 'token', (string) $token->id, 'greșit')->assertSet('error', null);

    expect($credit->fresh()->cancelled_at)->not->toBeNull()->and($token->fresh()->cancelled_at)->not->toBeNull()
        ->and(CreditLedger::balance($p->fresh()))->toBe(0.0);
});

it('nu se poate anula din altă sesiune sau după închiderea casei', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:00:00'));
    $party = rpParty('Petrecere Sesiune');
    $other = rpParty('Altă Petrecere', '2026-10-04');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);

    $foreign = EntryRecorder::record($other, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $user->id)->first()->batch;
    $mine = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $user->id)->first()->batch;

    Livewire::test(Recent::class)->call('cancelOperation', 'entry', $foreign, 'x y z')
        ->assertSet('error', 'Intrarea nu aparține sesiunii deschise.');
    expect(PartyEntry::active()->count())->toBe(2);

    ReceptionSession::currentFor($party->id)->update(['status' => 'closed', 'closed_at' => now()]);
    Livewire::test(Recent::class)->call('cancelOperation', 'entry', $mine, 'x y z')
        ->assertSet('error', fn ($e) => str_contains((string) $e, 'închisă'));
    expect(PartyEntry::active()->count())->toBe(2);
});

it('doar Acasă trimite la Tranzacții; ecranele de vânzare au în antet doar Acasă și celelalte vânzări', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:00:00'));
    $party = rpParty('Petrecere Linkuri');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    Livewire::test(Home::class)->assertSee(route('receptie.recent'), false);
    Livewire::test(Entry::class)->assertSee(route('receptie.home'), false)->assertDontSee(route('receptie.recent'), false);
    Livewire::test(Recent::class)->assertSee(route('receptie.home'), false)->assertDontSee(route('receptie.entry'), false)
        ->assertDontSee(route('receptie.tokens'), false)->assertDontSee(route('receptie.report'), false);
    Livewire::test(Report::class)->assertSee(route('receptie.home'), false)->assertDontSee(route('receptie.entry'), false)
        ->assertDontSee(route('receptie.recent'), false);
});

function rpSell(Party $party, Admin $user, float $cash = 60): void
{
    EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => $cash]], adminId: $user->id);
}

it('Raportarea din app arată totalurile și recalculează diferența pe măsură ce recepționerul numără', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Raport');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    rpSell($party, $user);

    Livewire::test(Report::class)
        ->assertSee('Cash așteptat')->assertSee('60,00 lei')
        ->set('opening_float', '100')->assertSee('160,00')
        ->set('handed_over', '20')->assertSee('140,00')->assertSee('Bani scoși din casă în timpul serii')->assertSee('− Scos din casă')
        ->set('counted_cash', '135')->assertSee('Lipsesc 5,00 lei');

    expect(ReceptionReport::count())->toBe(0); // doar calcul, nimic salvat încă
});

it('Salvează creează draftul, iar Trimite cere cash-ul numărat și marchează raportarea trimisă fără să închidă casa', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Trimitere');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    rpSell($party, $user);

    $c = Livewire::test(Report::class)->set('opening_float', '100')->call('save')->assertSet('error', null);
    $report = ReceptionReport::first();
    expect($report->isDraft())->toBeTrue()->and((float) $report->opening_float)->toBe(100.0);

    $c->call('askSubmit')->assertSet('confirming', false)->assertSet('error', fn ($e) => str_contains((string) $e, 'cash-ul numărat'));

    $c->set('counted_cash', '160')->call('askSubmit')->assertSet('confirming', true)->call('submit')
        ->assertSet('error', null)->assertSee('Raportare trimisă')
        ->assertDontSee('Un admin o verifică și închide casa.');

    $report->refresh();
    expect($report->isSubmitted())->toBeTrue()->and($report->submitted_by)->toBe($user->id)
        ->and($report->status)->toBe('draft')
        ->and(ReceptionSession::currentFor($party->id))->not->toBeNull(); // casa NU e închisă
});

it('cât raportarea e trimisă nu se mai înregistrează și nu se mai anulează nimic; redeschiderea deblochează', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Blocare');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    $batch = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $user->id)->first()->batch;

    Livewire::test(Report::class)->set('counted_cash', '30')->call('askSubmit')->call('submit');

    Livewire::test(Entry::class)->call('payAll', 'cash')->call('save')
        ->assertSet('error', ReceptionSession::SUBMITTED_MESSAGE);
    Livewire::test(TokenSale::class)->call('payAll', 'cash')->call('sell')->assertSet('error', ReceptionSession::SUBMITTED_MESSAGE);
    Livewire::test(Recent::class)->call('cancelOperation', 'entry', $batch, 'test motiv')->assertSet('error', ReceptionSession::SUBMITTED_MESSAGE);
    expect(PartyEntry::active()->count())->toBe(1);

    // câmpurile nu se mai pot modifica
    Livewire::test(Report::class)->set('counted_cash', '5')->call('save')->assertSet('error', 'Raportarea a fost deja trimisă.');

    ReceptionReport::first()->reopen($user->id);

    Livewire::test(Entry::class)->call('payAll', 'cash')->call('save')->assertSet('error', null);
    expect(PartyEntry::active()->count())->toBe(2);
});

it('adminul vede raportarea trimisă din app, o redeschide sau o finalizează', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Admin Raport');
    $rec = rpUser();
    $this->actingAs($rec, 'admin');
    rpReady($party);
    rpSell($party, $rec);
    Livewire::test(Report::class)->set('counted_cash', '60')->call('askSubmit')->call('submit');
    $report = ReceptionReport::first();

    $admin = rpUser(['role' => 'superadmin']);
    $this->actingAs($admin, 'admin');

    Livewire::test(ReportAdminForm::class, ['report' => $report])->assertSee('Trimisă din aplicație')->assertSee($rec->name)
        ->call('askReopen')->assertSet('confirmingReopen', true)->assertSee('Redeschizi raportarea?')
        ->call('cancelReopen')->assertSet('confirmingReopen', false)
        ->call('askReopen')->call('reopen')->assertSet('confirmingReopen', false)->assertRedirect(route('admin.reception.reports.show', $report));
    // Redeschiderea reîncarcă pagina (insigna din sidebar se recalculează); noua pagină nu mai arată „Trimisă”.
    $this->get(route('admin.reception.reports.show', $report))->assertOk()->assertDontSee('Trimisă din aplicație');
    expect($report->fresh()->isSubmitted())->toBeFalse();

    Livewire::test(Report::class); // recepția poate lucra din nou
    $this->actingAs($rec, 'admin');
    Livewire::test(Report::class)->call('askSubmit')->call('submit');
    $this->actingAs($admin, 'admin');

    Livewire::test(ReportAdminForm::class, ['report' => $report->fresh()])->call('finalize');
    expect($report->fresh()->isFinalized())->toBeTrue()->and(ReceptionSession::currentFor($party->id))->toBeNull();
});

it('Raportarea nu are ce raporta fără sesiune deschisă, iar Acasă trimite la ea', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Fără Sesiune');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    Livewire::test(Report::class)->assertSee('Nicio sesiune de recepție deschisă');
    Livewire::test(Home::class)->assertSee(route('receptie.report'), false);
    $this->get(route('receptie.report'))->assertOk();
});

it('Tranzacții și Raportare au în antet titlul corect și doar iconița Acasă', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Titluri');
    $this->actingAs(rpUser(), 'admin');
    rpReady($party);

    Livewire::test(Recent::class)->assertSee('Tranzacții')->assertDontSee('Ultimele operațiuni');
});

it('web-ul și PDF-ul spun „Bani scoși din casă în timpul serii”', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Eticheta Web');
    $user = rpUser(['role' => 'superadmin']);
    $this->actingAs($user, 'admin');
    rpReady($party);
    rpSell($party, $user);
    $report = ReceptionReport::startFor(ReceptionSession::currentFor($party->id), $user->id);

    Livewire::test(ReportAdminForm::class, ['report' => $report])->assertSee('Bani scoși din casă în timpul serii')->assertDontSee('Predat / scos');
});

it('Raportarea din app numără tokenii: fond, vânduți, așteptați și diferență', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    $party = rpParty('Petrecere Tokeni');
    $user = rpUser();
    $this->actingAs($user, 'admin');
    rpReady($party);
    rpSell($party, $user);

    $c = Livewire::test(Report::class)
        ->assertSee('Fond de tokeni')
        ->set('opening_tokens', '100')
        ->set('counted_tokens', '98')->assertSee('Diferență tokeni')
        ->set('counted_cash', '60')->call('save')->assertSet('error', null);

    $report = ReceptionReport::first();
    expect($report->opening_tokens)->toBe(100)->and($report->counted_tokens)->toBe(98);
    $fig = $report->figures();
    expect($fig->expected_tokens)->toBe(100 - $fig->tokens_sold)->and($fig->tokens_diff)->toBe(98 - $fig->expected_tokens);
});
