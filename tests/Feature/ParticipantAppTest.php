<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\ParticipantApp\AppSettings as AdminParticipantAppSettings;
use App\Livewire\Participant\Account;
use App\Livewire\Participant\Announcements;
use App\Livewire\Participant\ForgotPassword;
use App\Livewire\Participant\History;
use App\Livewire\Participant\Login;
use App\Livewire\Participant\Parties;
use App\Livewire\Participant\Register;
use App\Livewire\Participant\Tickets;
use App\Livewire\Participant\Verify;
use App\Livewire\Participant\Wallet;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\CreditTransaction;
use App\Models\LoyaltyCard;
use App\Models\Participant;
use App\Models\ParticipantVerification;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantAvatar;
use App\Services\ParticipantRegistry;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use App\Services\TicketOrders;
use App\Support\ParticipantApp;
use App\Support\ParticipantAppSettings;
use App\Support\Permissions;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (Aplicația participanților - runda 1). SMS-urile se captează, nu se trimit. */
class FakeSms implements SmsSender
{
    /** @var array<int, array{0:string,1:string}> */
    public static array $sent = [];

    public function send(string $phone, string $message): void
    {
        self::$sent[] = [$phone, $message];
    }
}

beforeEach(function () {
    FakeSms::$sent = [];
    app()->instance(SmsSender::class, new FakeSms);
});

function paLastCode(): string
{
    preg_match('/\b(\d{6})\b/', end(FakeSms::$sent)[1], $m);

    return $m[1];
}

function paParty(array $o = []): Party
{
    return Party::create(array_merge([
        'name' => 'Petrecere publică',
        'kind' => 'basic',
        'start_date' => now()->addDays(5)->toDateString(),
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => ['cash'],
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ], $o));
}

function paRegister(string $phone = '0722123456', string $pass = 'parola-sigura'): void
{
    ParticipantAccounts::startRegistration('Ana Test', $phone, $pass, app(SmsSender::class));
}

it('partea publică se vede fără cont', function () {
    $party = paParty();

    $this->get('/')->assertOk()->assertSee('Petrecere publică');
    $this->get('/petreceri')->assertOk()->assertSee('Petrecere publică');
    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Petrecere publică');
});

it('petrecerea doar pentru cei cu cont e ascunsă vizitatorilor și vizibilă după login', function () {
    $party = paParty(['name' => 'Doar membri', 'audience' => 'auth']);

    $this->get('/petreceri')->assertOk()->assertDontSee('Doar membri');

    $p = ParticipantRegistry::create('Ana', '0722123456');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    $this->actingAs($p, 'participant')->get('/petreceri')->assertOk()->assertSee('Doar membri');
    expect($party->id)->toBeInt();
});

it('manifestul și service worker-ul aplicației au scope pe rădăcină și ocolesc zonele interne', function () {
    $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('start_url', '/');
    $sw = $this->get('/sw.js')->assertOk()->getContent();

    foreach (['/admin', '/receptie', '/bar'] as $zone) {
        expect($sw)->toContain($zone);
    }
    $this->get('/icon/192.png')->assertOk();
});

it('pagina contului cere login', function () {
    $this->get('/cont')->assertRedirect(route('app.login'));
});

it('înregistrare: cererea nu creează participant până la confirmarea codului', function () {
    paRegister();

    expect(Participant::count())->toBe(0)
        ->and(ParticipantVerification::count())->toBe(1)
        ->and(FakeSms::$sent)->toHaveCount(1)
        ->and(FakeSms::$sent[0][0])->toBe('+40722123456');

    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());

    expect(Participant::count())->toBe(1)
        ->and($p->hasAccount())->toBeTrue()
        ->and($p->phone_verified_at)->not->toBeNull()
        ->and(ParticipantVerification::count())->toBe(0);
});

it('înregistrare prin Livewire: formular → verificare → logat', function () {
    Livewire::test(Register::class)
        ->set('name', 'Ana Test')->set('phone', '0722123456')
        ->set('password', 'parola-sigura')->set('password_confirmation', 'parola-sigura')
        ->call('register')
        ->assertRedirect(route('app.verify'));

    Livewire::test(Verify::class)
        ->set('code', paLastCode())
        ->call('verify')
        ->assertRedirect();

    expect(Auth::guard('participant')->check())->toBeTrue();
});

it('un participant creat la recepție se leagă de cont după verificare și își păstrează datele', function () {
    $existing = ParticipantRegistry::create('Ana Recepție', '0722123456');

    paRegister();
    $linked = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());

    expect($linked->id)->toBe($existing->id)
        ->and(Participant::count())->toBe(1)
        ->and($linked->hasAccount())->toBeTrue()
        ->and($linked->fresh()->name)->toBe('Ana Test');   // numele din aplicație înlocuiește cel de la Recepție
});

it('după verificare ajunge în aplicație, chiar dacă în sesiune era o adresă din admin', function () {
    session(['url.intended' => url('/admin/participanti')]);

    Livewire::test(Register::class)
        ->set('name', 'Ana Test')->set('phone', '0722123456')
        ->set('password', 'parola-sigura')->set('password_confirmation', 'parola-sigura')
        ->call('register');

    Livewire::test(Verify::class)->set('code', paLastCode())->call('verify')->assertRedirect(route('app.home'));
});

it('adresa păstrată dintr-o pagină a aplicației (petrecere) se respectă', function () {
    $party = paParty();
    session(['url.intended' => route('app.party', $party)]);

    expect(ParticipantApp::intendedOrHome())->toBe(route('app.party', $party))
        ->and(session()->has('url.intended'))->toBeFalse();

    session(['url.intended' => 'https://alt-site.example/admin']);
    expect(ParticipantApp::intendedOrHome())->toBe(route('app.home'));
});

it('un număr cu cont deja activ nu se mai poate înregistra', function () {
    paRegister();
    ParticipantAccounts::verifyRegistration('0722123456', paLastCode());

    expect(fn () => paRegister())->toThrow(DomainException::class, 'deja cont');
});

it('parola prea scurtă și numele prea scurt sunt refuzate', function () {
    expect(fn () => paRegister('0722123456', 'scurta'))->toThrow(DomainException::class, 'cel puțin')
        ->and(fn () => ParticipantAccounts::startRegistration('A', '0722123456', 'parola-sigura', app(SmsSender::class)))
        ->toThrow(DomainException::class);
});

it('codul expiră după 10 minute', function () {
    Carbon::setTestNow('2026-10-01 12:00:00');
    paRegister();
    $code = paLastCode();

    Carbon::setTestNow('2026-10-01 12:11:00');
    expect(fn () => ParticipantAccounts::verifyRegistration('0722123456', $code))->toThrow(DomainException::class, 'expirat')
        ->and(Participant::count())->toBe(0);
});

it('după 5 coduri greșite cererea se blochează, chiar și pentru codul corect', function () {
    paRegister();
    $code = paLastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 0; $i < ParticipantAccounts::MAX_ATTEMPTS; $i++) {
        expect(fn () => ParticipantAccounts::verifyRegistration('0722123456', $wrong))->toThrow(DomainException::class);
    }

    expect(fn () => ParticipantAccounts::verifyRegistration('0722123456', $code))->toThrow(DomainException::class, 'Prea multe')
        ->and(Participant::count())->toBe(0);
});

it('retrimiterea codului cere pauză de 60 de secunde și e plafonată la 5 pe oră', function () {
    Carbon::setTestNow('2026-10-01 12:00:00');
    paRegister();

    expect(fn () => ParticipantAccounts::resendCode('0722123456', app(SmsSender::class)))->toThrow(DomainException::class)
        ->and(ParticipantAccounts::resendWaitSeconds('+40722123456'))->toBeGreaterThan(0);

    for ($i = 1; $i < ParticipantAccounts::MAX_SENDS; $i++) {
        Carbon::setTestNow(now()->addSeconds(61));
        ParticipantAccounts::resendCode('0722123456', app(SmsSender::class));
    }
    expect(FakeSms::$sent)->toHaveCount(ParticipantAccounts::MAX_SENDS);

    Carbon::setTestNow(now()->addSeconds(61));
    expect(fn () => ParticipantAccounts::resendCode('0722123456', app(SmsSender::class)))->toThrow(DomainException::class, 'prea multe coduri');
});

it('login: telefon + parolă; eșecurile au același mesaj', function () {
    paRegister();
    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());
    Auth::guard('participant')->logout();

    Livewire::test(Login::class)->set('phone', '0722123456')->set('password', 'gresita-gresita')->call('login')
        ->assertSet('error', ParticipantAccounts::GENERIC_LOGIN_ERROR);
    Livewire::test(Login::class)->set('phone', '0700000000')->set('password', 'parola-sigura')->call('login')
        ->assertSet('error', ParticipantAccounts::GENERIC_LOGIN_ERROR);

    Livewire::test(Login::class)->set('phone', '0722123456')->set('password', 'parola-sigura')->call('login')->assertRedirect();
    expect(Auth::guard('participant')->id())->toBe($p->id);
});

it('login: participantul creat la recepție, fără parolă, nu poate intra', function () {
    ParticipantRegistry::create('Fără cont', '0733000111');

    expect(fn () => ParticipantAccounts::attempt('0733000111', 'orice-parola', false, '1.1.1.1'))
        ->toThrow(DomainException::class, ParticipantAccounts::GENERIC_LOGIN_ERROR);
});

it('login: după 5 încercări greșite se blochează, chiar cu parola corectă', function () {
    RateLimiter::clear('participant-login:+40722123456|9.9.9.9');
    paRegister();
    ParticipantAccounts::verifyRegistration('0722123456', paLastCode());
    Auth::guard('participant')->logout();

    for ($i = 0; $i < ParticipantAccounts::LOGIN_MAX_ATTEMPTS; $i++) {
        expect(fn () => ParticipantAccounts::attempt('0722123456', 'gresita-gresita', false, '9.9.9.9'))->toThrow(DomainException::class);
    }

    expect(fn () => ParticipantAccounts::attempt('0722123456', 'parola-sigura', false, '9.9.9.9'))->toThrow(DomainException::class, 'Prea multe');
});

it('resetare parolă: linkul vine prin SMS, e semnat și se stinge după folosire', function () {
    paRegister();
    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());
    Auth::guard('participant')->logout();
    FakeSms::$sent = [];

    Livewire::test(ForgotPassword::class)->set('phone', '0722123456')->call('send')->assertSet('sent', true);
    expect(FakeSms::$sent)->toHaveCount(1);

    preg_match('#https?://\S+#', FakeSms::$sent[0][1], $m);
    $url = $m[0];

    $this->get($url)->assertOk();
    $this->get($url.'x')->assertForbidden(); // semnătură stricată

    $stamp = ParticipantAccounts::resetStamp($p->fresh());
    ParticipantAccounts::resetPassword($p->fresh(), $stamp, 'parola-noua-1');

    expect(Hash::check('parola-noua-1', $p->fresh()->password))->toBeTrue()
        ->and(fn () => ParticipantAccounts::resetPassword($p->fresh(), $stamp, 'alta-parola-2'))
        ->toThrow(DomainException::class, 'nu mai este valabil');
});

it('resetarea pentru un număr necunoscut nu trimite SMS și nu dezvăluie nimic', function () {
    Livewire::test(ForgotPassword::class)->set('phone', '0799999999')->call('send')->assertSet('sent', true);

    expect(FakeSms::$sent)->toBe([]);
});

it('cel mult 3 cereri de resetare pe oră per telefon', function () {
    RateLimiter::clear('participant-reset:+40722123456');
    paRegister();
    ParticipantAccounts::verifyRegistration('0722123456', paLastCode());
    FakeSms::$sent = [];

    for ($i = 0; $i < 5; $i++) {
        ParticipantAccounts::sendResetLink('0722123456', app(SmsSender::class));
    }

    expect(FakeSms::$sent)->toHaveCount(ParticipantAccounts::RESET_MAX_REQUESTS);
});

it('anonimizarea șterge parola și verificarea contului', function () {
    paRegister();
    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());

    ParticipantRegistry::anonymize($p);

    $p = $p->fresh();
    expect($p->hasAccount())->toBeFalse()->and($p->phone_verified_at)->toBeNull()->and($p->remember_token)->toBeNull();
});

it('guard-urile admin și participant sunt separate', function () {
    $admin = Admin::create(['name' => 'A', 'phone' => '+40700111222', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']);
    $this->actingAs($admin, 'admin');

    $this->get('/cont')->assertRedirect(route('app.login'));

    paRegister();
    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());
    Auth::guard('admin')->logout();
    $this->actingAs($p, 'participant');

    $this->get('/admin')->assertRedirect(route('admin.login'));
    $this->get('/cont')->assertOk();
});

it('logout scoate participantul din sesiune', function () {
    paRegister();
    $p = ParticipantAccounts::verifyRegistration('0722123456', paLastCode());

    $this->actingAs($p, 'participant')->post('/iesire')->assertRedirect(route('app.home'));
    expect(Auth::guard('participant')->check())->toBeFalse();
});

/** ---- Runda 2: contul meu (QR, credite, fidelitate, intrări) ---- */
function paAccount(string $phone = '0722123456'): Participant
{
    $p = ParticipantRegistry::create('Ana Cont', $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

it('contul afișează codul QR personal cu același conținut ca în admin', function () {
    $p = paAccount();

    $this->actingAs($p, 'participant')->get('/cont')->assertOk()
        ->assertSee('data-qr="'.$p->qrPayload().'"', false)
        ->assertSee('qrcode-generator.js', false);
    expect(file_exists(public_path('vendor/qrcode-generator.js')))->toBeTrue();
});

it('portofelul arată soldul evidențiat și toate mișcările proprii (încărcări și plăți; lazy load, câte 10)', function () {
    $p = paAccount();
    $other = paAccount('0733111222');
    CreditLedger::load($p, 40, CreditTransaction::SOURCE_RECEPTION, null, 'Prima încărcare');
    CreditLedger::adjust($p, 5, 'Bonus test', null);
    CreditLedger::load($other, 99, CreditTransaction::SOURCE_RECEPTION, null, 'Al altcuiva');

    $this->actingAs($p, 'participant')->get('/portofel')->assertOk()
        ->assertSee('data-balance', false)->assertSee('45,00')->assertSee('Prima încărcare')->assertSee('Bonus test')
        ->assertDontSee('Al altcuiva')->assertDontSee('Vezi mai mult')->assertSee('data-topup-unavailable', false);   // runda 51b: cu cumpărarea oprită, mesajul spre Recepție în loc de butonul „Încarcă”

    // O plată apare în listă, cu minus.
    CreditLedger::pay($p, 12, Sale::class, 1, null);
    $this->get('/portofel')->assertOk()->assertSee('Plată')->assertSee('-12,00');

    $this->get('/portofel')->assertOk()->assertSee('Ultimele tranzacții')->assertDontSee('Se încarcă');

    foreach (range(1, 12) as $i) {
        CreditLedger::load($p, 10, CreditTransaction::SOURCE_RECEPTION, null, 'Încărcare '.$i);
    }
    // 15 tranzacții: pagina arată 10 și o santinelă de lazy load; „more” aduce următoarele 10.
    $c = Livewire::test(Wallet::class)->assertSee('Se încarcă')->assertSee('Încărcare 12')->assertDontSee('Prima încărcare');
    $c->call('more')->assertSee('Prima încărcare')->assertDontSee('Se încarcă');
    $this->get('/portofel/incarcari')->assertOk()->assertSee('Toate tranzacțiile')->assertSee('Încărcare 12')->assertDontSee('Al altcuiva');
});

it('paginile din meniu cer cont: vizitatorul e trimis la login', function () {
    foreach (['/bilete', '/bilete/toate', '/portofel', '/portofel/incarcari', '/cont/intrari', '/cont/consumatii'] as $url) {
        $this->get($url)->assertRedirect(route('app.login'));
    }
});

it('meniul are Acasă, Petreceri, Cod QR (popup), Bilete și Portofel; QR-ul personal e în popup, nu în cont', function () {
    $p = paAccount();

    $this->get('/')->assertOk()->assertSee('Intră în cont ca să-ți vezi codul QR');

    $this->actingAs($p, 'participant')->get('/cont')->assertOk()
        ->assertSeeInOrder(['Acasă', 'Petreceri', 'Cod QR', 'Bilete', 'Portofel'])
        ->assertSee('data-qr="'.$p->qrPayload().'"', false)->assertSee('qrcode-generator.js', false)
        ->assertDontSee('Codul tău personal</div>', false)->assertDontSee('Portofel credite')->assertDontSee('Biletele mele');
    expect(file_exists(public_path('vendor/qrcode-generator.js')))->toBeTrue();
});

it('contul: ultimele 5 intrări și consumații la bar cu „Vezi tot”, cardul de fidelitate primul', function () {
    $p = paAccount();
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 5);
    $group = SalesGroup::create(['status' => 'open']);
    foreach (range(1, 7) as $i) {
        Sale::create(['sales_group_id' => $group->id, 'source' => 'manual', 'customer_id' => $p->id, 'status' => 'completed', 'total' => 10 + $i, 'sold_at' => now()->subMinutes(10 - $i)]);
    }

    $c = $this->actingAs($p, 'participant')->get('/cont')->assertOk()->assertSeeInOrder(['Aplică pentru card', 'Consumații la bar'])->assertSee('Vezi tot');
    $c->assertSee('17,00 lei')->assertDontSee('11,00 lei');    // doar ultimele 5 (13..17)

    $this->get('/cont/consumatii')->assertOk()->assertSee('Toate consumațiile de la bar')->assertSee('11,00 lei')->assertSee('17,00 lei');
    $this->get('/cont/intrari')->assertOk()->assertSee('Toate intrările');
});

it('contul afișează intrările proprii, nu și pe cele anulate sau ale altora', function () {
    $admin = Admin::create(['name' => 'R', 'phone' => '+40700999888', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']);
    $party = paParty(['name' => 'Seara mea', 'start_date' => now()->toDateString(), 'start_time' => '00:01', 'end_time' => '23:59']);
    $p = paAccount();
    $other = paAccount('0733111222');

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], participants: [$p->id], adminId: $admin->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], participants: [$other->id], adminId: $admin->id);

    $this->actingAs($p, 'participant')->get('/cont')->assertOk()->assertSee('Seara mea')->assertSee('30,00 lei');
    expect(PartyEntry::query()->where('participant_id', $other->id)->count())->toBe(1);
});

it('contul afișează cardul de fidelitate cu ștampile, sau invitația de înscriere când fidelitatea e activă', function () {
    $p = paAccount();
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 5);

    $this->actingAs($p, 'participant')->get('/cont')->assertOk()->assertSee('Aplică pentru card');

    LoyaltyLedger::enroll($p, null, 2);
    $this->actingAs($p, 'participant')->get('/cont')->assertOk()->assertSee('Card de fidelitate: 2 din 5 ștampile', false)->assertDontSee('Nu ești înscris');
});

/** ---- Runda 12: popup-uri, poză de profil, editare cont, fidelitate din aplicație ---- */
it('mesajul flash apare ca popup (toast) în layout și se consumă', function () {
    $p = paAccount();

    $this->actingAs($p, 'participant')->withSession(['status' => 'Profilul a fost salvat.'])->get('/cont')
        ->assertOk()->assertSee('data-flash="Profilul a fost salvat."', false)->assertSee('pa-toast', false);
    $this->get('/cont')->assertDontSee('Profilul a fost salvat.');
});

it('inițialele: două cuvinte, un cuvânt, diacritice', function () {
    $mk = fn (string $n) => (new Participant(['name' => $n]))->initials();

    expect($mk('Ana Maria Pop'))->toBe('AM')->and($mk('ion'))->toBe('I')->and($mk('Ștefan Țurcanu'))->toBe('ȘȚ');
});

it('antetul arată inițialele fără poză și poza când există', function () {
    Storage::fake('local');
    $p = paAccount();

    $this->actingAs($p, 'participant')->get('/')->assertOk()->assertSee('AC', false)->assertDontSee('/cont/poza');

    ParticipantAvatar::store($p, UploadedFile::fake()->image('a.png', 600, 300));
    $this->actingAs($p->fresh(), 'participant')->get('/')->assertOk()->assertSee('/cont/poza?v=', false);
});

it('poza se re-codifică pătrat 256x256 JPEG, se servește doar proprietarului și se șterge la anonimizare', function () {
    Storage::fake('local');
    $p = paAccount();

    ParticipantAvatar::store($p, UploadedFile::fake()->image('a.png', 900, 300));
    $p = $p->fresh();
    $img = imagecreatefromstring(Storage::disk('local')->get($p->avatar_path));
    expect(imagesx($img))->toBe(256)->and(imagesy($img))->toBe(256);

    $this->get('/cont/poza')->assertRedirect(route('app.login')); // fără sesiune
    $this->actingAs($p, 'participant')->get('/cont/poza')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $path = $p->avatar_path;
    ParticipantRegistry::anonymize($p);
    expect($p->fresh()->avatar_path)->toBeNull()->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('un fișier care nu e imagine e refuzat', function () {
    Storage::fake('local');
    $p = paAccount();
    $fake = UploadedFile::fake()->createWithContent('x.jpg', 'nu sunt o poza');

    expect(fn () => ParticipantAvatar::store($p, $fake))->toThrow(DomainException::class);
    expect($p->fresh()->avatar_path)->toBeNull();
});

it('editare profil: numele se schimbă, poza se încarcă prin Livewire, apoi redirect', function () {
    Storage::fake('local');
    $p = paAccount();
    $this->actingAs($p, 'participant');

    Livewire::test(Account::class)
        ->set('name', 'Ana Nouă')
        ->set('photo', UploadedFile::fake()->image('p.jpg', 400, 400))
        ->call('saveProfile')
        ->assertRedirect(route('app.account'));

    $p = $p->fresh();
    expect($p->name)->toBe('Ana Nouă')->and($p->hasAvatar())->toBeTrue();

    Livewire::test(Account::class)->call('removePhoto')->assertRedirect(route('app.account'));
    expect($p->fresh()->hasAvatar())->toBeFalse();
});

it('editare profil: nume prea scurt e refuzat, fără redirect', function () {
    $p = paAccount();
    $this->actingAs($p, 'participant');

    Livewire::test(Account::class)->set('name', 'A')->call('saveProfile')
        ->assertNoRedirect()->assertSet('profileError', 'Scrie numele tău (între 2 și 120 de caractere).');
    expect($p->fresh()->name)->toBe('Ana Cont');
});

it('schimbarea parolei cere parola curentă corectă, parola nouă validă și confirmare', function () {
    $p = paAccount();
    $this->actingAs($p, 'participant');
    $c = fn () => Livewire::test(Account::class);

    $c()->set('currentPassword', 'gresita-gresita')->set('newPassword', 'parola-noua-1')->set('newPasswordConfirmation', 'parola-noua-1')
        ->call('changePassword')->assertSet('passwordError', 'Parola curentă nu e corectă.');
    $c()->set('currentPassword', 'parola-sigura')->set('newPassword', 'scurta')->set('newPasswordConfirmation', 'scurta')
        ->call('changePassword')->assertSet('passwordError', 'Parola trebuie să aibă cel puțin 8 caractere.');
    $c()->set('currentPassword', 'parola-sigura')->set('newPassword', 'parola-noua-1')->set('newPasswordConfirmation', 'diferita-123')
        ->call('changePassword')->assertSet('passwordError', 'Parolele noi nu coincid.');

    $c()->set('currentPassword', 'parola-sigura')->set('newPassword', 'parola-noua-1')->set('newPasswordConfirmation', 'parola-noua-1')
        ->call('changePassword')->assertSet('passwordError', '')->assertDispatched('toast');

    expect(Hash::check('parola-noua-1', $p->fresh()->password))->toBeTrue();
});

it('schimbarea parolei se blochează după 5 parole curente greșite', function () {
    $p = paAccount();
    RateLimiter::clear('participant-password:'.$p->id);
    $this->actingAs($p, 'participant');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Account::class)->set('currentPassword', 'gresita-gresita')->set('newPassword', 'parola-noua-1')->set('newPasswordConfirmation', 'parola-noua-1')->call('changePassword');
    }

    Livewire::test(Account::class)->set('currentPassword', 'parola-sigura')->set('newPassword', 'parola-noua-1')->set('newPasswordConfirmation', 'parola-noua-1')
        ->call('changePassword')->assertSet('passwordError', fn ($v) => str_contains($v, 'Prea multe'));
    expect(Hash::check('parola-sigura', $p->fresh()->password))->toBeTrue();
});

it('înscrierea la fidelitate se face din aplicație, doar când programul e activ, o singură dată', function () {
    $p = paAccount();
    $this->actingAs($p, 'participant');

    Livewire::test(Account::class)->call('enrollLoyalty')->assertDispatched('toast', type: 'err');
    expect($p->fresh()->isLoyaltyEnrolled())->toBeFalse();

    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 5);
    $this->get('/cont')->assertSee('Aplică pentru card');

    Livewire::test(Account::class)->call('enrollLoyalty')->assertDispatched('toast', type: 'ok');
    expect($p->fresh()->isLoyaltyEnrolled())->toBeTrue();

    Livewire::test(Account::class)->call('enrollLoyalty')->assertDispatched('toast', type: 'err');
    expect(LoyaltyCard::where('participant_id', $p->id)->count())->toBe(1);
});

it('lista de participanți din admin arată poza în cerc, sau inițialele; poza se servește doar adminilor', function () {
    Storage::fake('local');
    $admin = Admin::create(['name' => 'Adm', 'phone' => '+40700555444', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']);
    $withPhoto = paAccount('0722000001');
    $without = ParticipantRegistry::create('Ion Popescu', '0722000002');
    ParticipantAvatar::store($withPhoto, UploadedFile::fake()->image('a.png', 300, 300));

    $this->get(route('admin.participants.avatar', $withPhoto->id))->assertRedirect(route('admin.login'));

    $this->actingAs($admin, 'admin');
    $this->get(route('admin.participants.index'))->assertOk()
        ->assertSee('/participants/'.$withPhoto->id.'/poza?v=', false)
        ->assertSee('>IP</span>', false);
    $this->get(route('admin.participants.avatar', $withPhoto->id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('admin.participants.avatar', $without->id))->assertNotFound();
});

it('textul de fidelitate din aplicație folosește numărul de ștampile setat', function () {
    $p = paAccount();
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', 7);

    $this->actingAs($p, 'participant')->get('/cont')->assertSee('după ce strângi 7 ștampile');
});

it('pagina petrecerii: frecvența stilurilor, contactele grupate sub un singur label, link cu iconiță, buton de navigare', function () {
    $party = paParty([
        'music_styles' => [['style' => 'Bachata', 'frequency' => 3], ['style' => 'Salsa', 'frequency' => 2], ['style' => 'Kizomba', 'frequency' => '']],
        'contacts' => [['name' => 'Diana', 'phone' => '0748995202', 'note' => 'WhatsApp'], ['name' => 'Catalin', 'phone' => '0748960817']],
        'links' => [['label' => 'insta', 'url' => 'https://instagram.com/dxa']],
        'location_name' => 'Club X', 'location_url' => 'https://maps.example/x',
    ]);

    $html = $this->get('/petreceri/'.$party->id)->assertOk()
        ->assertSee('3× Bachata')->assertSee('2× Salsa')->assertSee('Kizomba')->assertDontSee('×Kizomba')
        ->assertSee('Diana')->assertSee('Catalin')->assertSee('· WhatsApp')
        ->assertSee('Navighează la locație')->assertDontSee('Vezi pe hartă')->getContent();

    expect(substr_count($html, '>Contact</div>'))->toBe(1)                       // label o singură dată
        ->and($html)->toMatch('~<svg[^>]*>\s*<path d="M10 13a5 5 0 0 0 7\.07 0l3-3[^>]*>.*?</svg>\s*insta~s');   // iconița de link înaintea textului
});

it('bilete: mai multe bilete valabile într-un carusel cu puncte; ultimele 5 folosite + „Vezi mai mult” spre lista completă', function () {
    $p = paAccount();
    $party = paParty(['online_sales' => true, 'start_date' => now()->toDateString(), 'start_time' => '00:01', 'end_time' => '23:59']);
    $order = TicketOrders::place($p, $party, 'Bilet', 3);
    expect($order->tickets)->toHaveCount(3);

    $this->actingAs($p, 'participant')->get('/bilete')->assertOk()
        ->assertSee('data-tickets-carousel', false)->assertSee('pa-dots', false)->assertDontSee('glisează')->assertDontSee('bilete valabile')
        ->assertDontSee('Ultimele bilete')->assertDontSee('Se încarcă');

    // Douăsprezece bilete anulate: apar 10, cu lazy load pentru restul (cele folosite rămân în carusel, runda 54).
    foreach (range(1, 12) as $i) {
        $t = TicketOrders::place($p, $party, 'Bilet', 1)->tickets->first();
        $t->update(['status' => 'void']);
    }
    $this->get('/bilete')->assertOk()->assertSee('Ultimele bilete')->assertSee('Se încarcă');
    Livewire::test(Tickets::class)->assertSet('limit', 10)->call('more')->assertSet('limit', 20)->assertDontSee('Se încarcă');
    $this->get('/bilete/toate')->assertOk()->assertSee('Toate biletele');
});

it('Acasă: petreceri următoare înaintea anunțurilor; anunț cu imagine în stânga, fără imagine cu placeholder; texte noi', function () {
    paParty(['name' => 'Seara viitoare']);
    Announcement::create(['title' => 'Cu poză', 'body' => 'Detalii', 'image_path' => 'announcements/x.jpg', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published']);
    Announcement::create(['title' => 'Fără poză', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published']);

    $html = $this->get('/')->assertOk()
        ->assertSee('Hai în comunitate')->assertSee('Dansează și distrează-te alături de noi')
        ->assertDontSee('Seara următoare')->assertDontSee('Hai la dans')
        ->assertSeeInOrder(['Petreceri următoare', 'Anunțuri'])->assertSee('Vezi toate')->getContent();

    expect(substr_count($html, 'storage/announcements/x.jpg'))->toBe(1)          // doar anunțul cu imagine are <img>
        ->and($html)->toContain('Fără poză')
        ->and(substr_count($html, 'class="pa-ph"'))->toBe(1);          // placeholder doar la anunțul fără imagine (runda 29)
    $this->get('/anunturi')->assertOk()->assertSee('Cu poză')->assertSee('Fără poză');
});

it('lista de petreceri arată și petrecerile trecute, după cele următoare', function () {
    paParty(['name' => 'Urmează', 'start_date' => now()->addDays(3)->toDateString()]);
    $past = paParty(['name' => 'A trecut', 'start_date' => now()->subDays(5)->toDateString()]);
    $past->update(['ends_at' => now()->subDays(4)]);

    $this->get('/petreceri')->assertOk()->assertSeeInOrder(['Următoare', 'Urmează', 'Trecute', 'A trecut']);
    $this->get('/')->assertOk()->assertSee('Urmează')->assertDontSee('A trecut');
});

it('anunțuri, petreceri și istoricul se încarcă treptat (lazy load): o tranșă de 10, apoi „more”', function () {
    $p = paAccount();
    foreach (range(1, 12) as $i) {
        Announcement::create(['title' => 'Anunț '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'body' => 'x', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published']);
        paParty(['name' => 'Urmează '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'start_date' => now()->addDays($i)->toDateString()]);
    }

    Livewire::test(Announcements::class)->assertSet('limit', 10)->assertSee('Se încarcă')->call('more')->assertSet('limit', 20)->assertDontSee('Se încarcă');
    Livewire::test(Parties::class)->assertSee('Se încarcă')->call('more')->assertDontSee('Se încarcă')->assertSee('Urmează 12');

    foreach (range(1, 25) as $i) {
        CreditLedger::load($p, 1, CreditTransaction::SOURCE_RECEPTION, null, 'Mișcare '.$i);
    }
    $this->actingAs($p, 'participant');
    Livewire::test(History::class, ['kind' => 'credits'])->assertSee('Se încarcă')->call('more')->assertSee('Mișcare 1')->assertDontSee('Se încarcă');
});

it('cardul de fidelitate nu mai spune „Mai ai … ștampile”, iar numărul e id-ul cardului pe cel puțin 4 cifre', function () {
    $p = paAccount();
    LoyaltyLedger::enroll($p);
    $card = LoyaltyLedger::activeCard($p);

    $this->actingAs($p, 'participant')->get('/cont')->assertOk()
        ->assertDontSee('Mai ai')->assertDontSee('până la intrarea gratis')
        ->assertSee('#'.str_pad((string) $card->id, 4, '0', STR_PAD_LEFT));
});

/** DXA: teste (runda 22). Setările „Aplicația participanților”. */
function paSuperAdmin(): Admin
{
    return Admin::create(['name' => 'Sa', 'phone' => '0700000077', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']);
}

it('setări: pagina Aplicație participanți are implicite corecte, salvează și respinge valorile greșite', function () {
    $this->actingAs(paSuperAdmin(), 'admin');

    expect(ParticipantAppSettings::homeEyebrow())->toBe('Hai în comunitate')
        ->and(ParticipantAppSettings::homeParties())->toBe(6)
        ->and(ParticipantAppSettings::homeAnnouncements())->toBe(5)
        ->and(ParticipantAppSettings::showPastParties())->toBeTrue()
        ->and(ParticipantAppSettings::registrationOpen())->toBeTrue()
        ->and(ParticipantAppSettings::codeTtlMinutes())->toBe(10)
        ->and(ParticipantAppSettings::codeMaxAttempts())->toBe(5)
        ->and(ParticipantAppSettings::contacts())->toBe([]);

    $this->get(route('admin.settings.participant-app'))->assertOk()->assertSee('Participanți · Aplicație')->assertSee('Texte SMS')->assertDontSee('Politica de confidențialitate (link)');

    Livewire::test(AdminParticipantAppSettings::class)
        ->set('values.app_home_title', 'Dansăm împreună')
        ->set('values.app_home_parties', '2')
        ->set('values.app_code_max_attempts', '3')
        ->set('values.app_registration_open', false)
        ->set('values.app_contact_phone', '0722 111 222')
        ->call('save')->assertHasNoErrors();

    expect(ParticipantAppSettings::homeTitle())->toBe('Dansăm împreună')
        ->and(ParticipantAppSettings::homeParties())->toBe(2)
        ->and(ParticipantAppSettings::codeMaxAttempts())->toBe(3)
        ->and(ParticipantAppSettings::registrationOpen())->toBeFalse()
        ->and(ParticipantAppSettings::contacts()[0])->toMatchArray(['label' => 'Telefon', 'text' => '0722 111 222', 'href' => 'tel:0722111222']);

    Livewire::test(AdminParticipantAppSettings::class)
        ->set('values.app_home_parties', '99')->set('values.app_contact_email', 'nu-e-email')
        ->set('values.app_sms_activation', 'Codul tău e gata')->set('values.app_sms_reset', 'Resetează parola')
        ->call('save')->assertHasErrors(['values.app_home_parties', 'values.app_contact_email', 'values.app_sms_activation', 'values.app_sms_reset']);
});

it('setări: numele și logo-ul aplicației se salvează și apar în antet și în manifest', function () {
    Storage::fake('public');
    $this->actingAs(paSuperAdmin(), 'admin');

    Livewire::test(AdminParticipantAppSettings::class)
        ->set('name', 'Dance Parties')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 200, 80))
        ->call('save')->assertHasNoErrors();

    expect(ParticipantApp::name())->toBe('Dance Parties')->and(ParticipantApp::uploadedLogoPath())->not->toBeNull();
    $this->get('/')->assertOk()->assertSee('storage/'.ParticipantApp::uploadedLogoPath(), false)->assertSee('Dance Parties');
    expect($this->get(route('app.manifest'))->json('name'))->toBe('Dance Parties');
});

it('setări: contactul și paginile legale publicate apar în subsolul aplicației, iar fără ele nu apare nimic', function () {
    $this->get('/')->assertOk()->assertDontSee('data-app-footer', false);

    Settings::set('app_contact_phone', '0722 111 222');
    Settings::set('app_contact_email', 'salut@exemplu.ro');
    Settings::set('app_contact_instagram', 'https://instagram.com/dxa');
    Terms::publish('Termeni de probă', true);
    PrivacyPolicy::publish('Politică de probă');

    $this->get('/')->assertOk()->assertSee('data-app-footer', false)->assertSee('href="tel:0722111222"', false)->assertSee('mailto:salut@exemplu.ro', false)
        ->assertSee('Instagram')->assertSee('Termeni și condiții')->assertSee('Politica de confidențialitate');

    // Pe login / înregistrare apar doar linkurile legale, nu și contactul.
    $this->get('/inregistrare')->assertOk()->assertSee('Termeni și condiții')->assertSee('Politica de confidențialitate')->assertDontSee('salut@exemplu.ro');
});

it('setări: SMS-urile folosesc șabloanele editate (cu {cod}, {minute}, {aplicatie}, {link}); un șablon invalid revine la cel implicit', function () {
    Settings::set('app_code_ttl_minutes', 7);
    Settings::set('app_sms_activation', '{aplicatie}: cod {cod}, valabil {minute} min.');
    paRegister();
    expect(end(FakeSms::$sent)[1])->toBe('DXA Parties: cod '.paLastCode().', valabil 7 min.');

    // Fără {cod} șablonul ar face codul inutil: se folosește cel implicit.
    Settings::set('app_sms_activation', 'Bună!');
    expect(ParticipantAppSettings::smsActivation('123456'))->toContain('123456')->toContain('DXA');

    Settings::set('app_sms_reset', 'Parola ta: {link}');
    expect(ParticipantAppSettings::smsReset('https://x.ro/r'))->toBe('Parola ta: https://x.ro/r');
});

it('setări: titlul, numărul de petreceri și de anunțuri din Acasă și petrecerile trecute urmează setările', function () {
    foreach (range(1, 4) as $i) {
        paParty(['name' => 'Urmează '.$i, 'start_date' => now()->addDays($i)->toDateString()]);
    }
    Announcement::create(['title' => 'Anunț unic', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published']);
    $past = paParty(['name' => 'A trecut', 'start_date' => now()->subDays(5)->toDateString()]);
    $past->update(['ends_at' => now()->subDays(4)]);

    Settings::set('app_home_eyebrow', 'Bun venit');
    Settings::set('app_home_title', 'Titlu nou');
    Settings::set('app_home_parties', 2);
    Settings::set('app_home_announcements', 0);

    $html = $this->get('/')->assertOk()->assertSee('Bun venit')->assertSee('Titlu nou')->assertDontSee('Hai în comunitate')
        ->assertDontSee('Anunț unic')->getContent();
    expect(substr_count($html, 'Urmează '))->toBe(2);

    $this->get('/petreceri')->assertOk()->assertSee('A trecut');
    Settings::set('app_show_past_parties', false);
    $this->get('/petreceri')->assertOk()->assertDontSee('A trecut')->assertDontSee('Trecute');
});

it('setări: înregistrarea închisă oprește conturile noi, dar nu și intrarea în conturile existente', function () {
    $existing = paAccount();
    Settings::set('app_registration_open', false);

    Livewire::test(Register::class)->assertSee('închisă momentan');
    expect(fn () => ParticipantAccounts::startRegistration('Nou Venit', '0744123123', 'parola-sigura', app(SmsSender::class)))
        ->toThrow(DomainException::class, 'închisă');
    expect(Participant::where('phone', '+40744123123')->exists())->toBeFalse();

    Livewire::test(Login::class)->set('phone', $existing->phone)->set('password', 'parola-sigura')->call('login')->assertRedirect();
});

it('setări: valabilitatea codului SMS și numărul de încercări urmează setările', function () {
    Settings::set('app_code_ttl_minutes', 3);
    Settings::set('app_code_max_attempts', 3);
    paRegister();

    $v = ParticipantVerification::first();
    expect((int) round(now()->diffInMinutes($v->expires_at, false)))->toBe(3);

    $code = paLastCode();
    $wrong = $code === '000000' ? '111111' : '000000';
    for ($i = 0; $i < 3; $i++) {
        expect(fn () => ParticipantAccounts::verifyRegistration('0722123456', $wrong))->toThrow(DomainException::class);
    }
    expect(fn () => ParticipantAccounts::verifyRegistration('0722123456', $code))->toThrow(DomainException::class, 'Prea multe');
});
