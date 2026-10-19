<?php

use App\Livewire\Admin\Announcements\Form;
use App\Livewire\Admin\Push\Index as PushIndex;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyInterest;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\Ticket;
use App\Services\AdminTickets;
use App\Services\ParticipantRegistry;
use App\Services\Push\Events;
use App\Services\Push\Notifier;
use App\Services\Push\PushGateway;
use App\Services\Push\Vapid;
use App\Services\TicketOrders;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 70). Notificări push: chei VAPID, abonări, livrare fără conținut, evenimente și pagina din admin. */
class R70FakeGateway implements PushGateway
{
    public array $sent = [];

    public int $status = 201;

    public function send(PushSubscription $subscription, string $urgency = 'normal'): int
    {
        $this->sent[] = [$subscription->id, $urgency];

        return $this->status;
    }
}

/** Chei VAPID de test: se generează; unde openssl nu poate genera curbe EC (PHP wasm) se folosește o pereche de unică folosință, fără alt rost. */
function r70Keys(): array
{
    try {
        return Vapid::generate();
    } catch (RuntimeException) {
        return ['public' => 'BP5_CEc-TRX2BdLFsiS5b-WC4UnQ0scFhux5Akxe8Zt2mHSPORwoBh9s5HCJL0Lqtrbsbmglgq92TGZosMt4NfA', 'private' => 'Y74jOdkc-Pff3aA3Q4R3YdxbVwwot5uZJpDKQTdMEFg'];
    }
}

function r70Gateway(): R70FakeGateway
{
    $keys = r70Keys();
    config(['services.push.public_key' => $keys['public'], 'services.push.private_key' => $keys['private'], 'services.push.subject' => 'mailto:test@exemplu.ro']);
    $fake = new R70FakeGateway;
    app()->instance(PushGateway::class, $fake);
    Notifier::setType('ticket_new', false);   // runda 70d: testele din această rundă nu urmăresc notificarea de bilet cumpărat

    return $fake;
}

function r70User(string $name, string $phone, bool $subscribed = true): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    if ($subscribed) {
        Notifier::subscribe($p, 'https://push.example.com/send/'.bin2hex(random_bytes(8)), 'p256', 'auth', 'Test UA');
    }

    return $p->fresh();
}

function r70Party(array $over = []): Party
{
    return Party::create($over + [
        'name' => 'Petrecere push', 'kind' => 'basic', 'start_date' => '2026-10-10', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r70Admin(array $levels = ['settings' => 1], array $actions = ['manage_notifications']): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => $actions],
    ]);
}

// ---- VAPID ----------------------------------------------------------------------------------------------------------

it('antetul VAPID are un JWT ES256 cu semnătură validă pentru cheia publică', function () {
    r70Gateway();
    $header = Vapid::authorization('https://fcm.googleapis.com/fcm/send/abc123', 1_800_000_000);
    expect($header)->toStartWith('vapid t=')->toContain(', k='.Vapid::publicKey());

    [$jwt] = explode(', k=', substr($header, strlen('vapid t=')));
    [$h, $c, $s] = explode('.', $jwt);
    expect(json_decode(Vapid::unb64($h), true))->toBe(['typ' => 'JWT', 'alg' => 'ES256'])
        ->and(json_decode(Vapid::unb64($c), true))->toMatchArray(['aud' => 'https://fcm.googleapis.com', 'exp' => 1_800_000_000 + 12 * 3600, 'sub' => 'mailto:test@exemplu.ro']);

    $raw = Vapid::unb64($s);
    expect(strlen($raw))->toBe(64);

    // raw r||s -> DER, ca să verificăm cu openssl
    $int = fn (string $b) => "\x02".chr(strlen($b = (ord($b[0]) & 0x80 ? "\0".$b : $b))).$b;
    $r = $int(ltrim(substr($raw, 0, 32), "\0") ?: "\0");
    $sInt = $int(ltrim(substr($raw, 32), "\0") ?: "\0");
    $der = "\x30".chr(strlen($r.$sInt)).$r.$sInt;
    $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').Vapid::unb64(Vapid::publicKey());
    $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    expect(openssl_verify($h.'.'.$c, $der, $pem, OPENSSL_ALGO_SHA256))->toBe(1);
});

it('cheile VAPID generate au mărimea corectă și comanda refuză să le suprascrie', function () {
    $k = r70Keys();
    expect(strlen(Vapid::unb64($k['public'])))->toBe(65)->and(strlen(Vapid::unb64($k['private'])))->toBe(32);

    config(['services.push.public_key' => '', 'services.push.private_key' => '']);
    if (! openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])) {
        // openssl fără generare EC (PHP wasm din mediul de test): comanda cade controlat, nu cu o eroare PHP
        expect(fn () => Artisan::call('app:push-keys'))->toThrow(RuntimeException::class);
    } else {
        expect(Artisan::call('app:push-keys'))->toBe(0)->and(Artisan::output())->toContain('VAPID_PUBLIC_KEY=')->toContain('VAPID_PRIVATE_KEY=');
    }
    r70Gateway();
    expect(Artisan::call('app:push-keys'))->toBe(1);
});

// ---- Abonări și livrare -----------------------------------------------------------------------------------------------

it('abonarea cere cont, doar endpoint https, și același endpoint trece pe contul curent', function () {
    r70Gateway();
    $ana = r70User('Ana Push', '0722700111', false);
    $bob = r70User('Bob Push', '0722700222', false);
    $payload = ['endpoint' => 'https://push.example.com/send/xyz', 'keys' => ['p256dh' => 'k1', 'auth' => 'a1']];

    $this->postJson('/notificari/abonare', $payload)->assertUnauthorized();
    $this->actingAs($ana, 'participant')->postJson('/notificari/abonare', ['endpoint' => 'http://nesigur.example.com/x'])->assertUnprocessable();
    $this->actingAs($ana, 'participant')->postJson('/notificari/abonare', $payload)->assertOk();
    expect(PushSubscription::query()->count())->toBe(1)->and(PushSubscription::first()->participant_id)->toBe($ana->id);

    $this->actingAs($bob, 'participant')->postJson('/notificari/abonare', $payload)->assertOk();
    expect(PushSubscription::query()->count())->toBe(1)->and(PushSubscription::first()->participant_id)->toBe($bob->id);

    $this->actingAs($ana, 'participant')->postJson('/notificari/dezabonare', ['endpoint' => $payload['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);   // Ana nu poate scoate abonarea lui Bob
    $this->actingAs($bob, 'participant')->postJson('/notificari/dezabonare', ['endpoint' => $payload['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('fără chei VAPID, abonarea și notificările nu fac nimic', function () {
    config(['services.push.public_key' => '', 'services.push.private_key' => '']);
    $ana = r70User('Ana Fără', '0722700333');
    $this->actingAs($ana, 'participant')->postJson('/notificari/abonare', ['endpoint' => 'https://push.example.com/x'])->assertNotFound();
    expect(Notifier::notify($ana, 'test', 'k', 'T', 'B'))->toBeNull();
    expect(Artisan::call('app:push-run'))->toBe(0)->and(Artisan::output())->toContain('nu e configurat');
});

it('notificarea se trimite o dată per cheie, pe fiecare telefon, iar abonările moarte (410) se șterg', function () {
    $gw = r70Gateway();
    $ana = r70User('Ana Două', '0722700444');
    Notifier::subscribe($ana, 'https://push.example.com/send/al-doilea');
    expect(PushSubscription::query()->count())->toBe(2);

    $n = Notifier::notify($ana, 'test', 'k1', 'Titlu', 'Text', '/cont', true);
    expect($n)->toBeInstanceOf(PushNotification::class)->and($gw->sent)->toHaveCount(2)->and($gw->sent[0][1])->toBe('high');
    expect(Notifier::notify($ana, 'test', 'k1', 'Titlu', 'Text'))->toBeNull()->and($gw->sent)->toHaveCount(2);

    $gw->status = 410;
    Notifier::notify($ana, 'test', 'k2', 'Alt titlu', 'Text');
    expect(PushSubscription::query()->count())->toBe(0);
    expect(Notifier::notify($ana, 'test', 'k3', 'Fără abonări', 'Text'))->toBeNull();
});

it('service worker-ul cere textul: fiecare telefon primește notificarea o singură dată', function () {
    r70Gateway();
    $ana = r70User('Ana Două Telefoane', '0722700555', false);
    $a = Notifier::subscribe($ana, 'https://push.example.com/send/telefon-a');
    $b = Notifier::subscribe($ana, 'https://push.example.com/send/telefon-b');
    Notifier::notify($ana, 'announcement', 'x1', 'Anunț', 'Salut', '/anunturi');

    $res = $this->postJson('/notificari/necitite', ['endpoint' => $a->endpoint])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($res->json('items'))->toHaveCount(1)->and($res->json('items.0'))->toMatchArray(['title' => 'Anunț', 'body' => 'Salut', 'url' => '/anunturi']);
    expect($this->postJson('/notificari/necitite', ['endpoint' => $a->endpoint])->json('items'))->toBe([]);
    expect($this->postJson('/notificari/necitite', ['endpoint' => $b->endpoint])->json('items'))->toHaveCount(1);
    expect($this->postJson('/notificari/necitite', ['endpoint' => 'https://necunoscut.example.com/z'])->json('items'))->toBe([]);
    expect($this->postJson('/notificari/necitite', [])->json('items'))->toBe([]);
});

it('notificarea de test din Cont ajunge pe telefoanele proprii', function () {
    $gw = r70Gateway();
    $ana = r70User('Ana Test', '0722700666');
    $this->actingAs($ana, 'participant')->postJson('/notificari/test')->assertOk();
    expect($gw->sent)->toHaveCount(1)->and(PushNotification::first()->kind)->toBe('test');
});

it('cardul Notificări apare în Cont doar când push e configurat; service worker-ul știe de push', function () {
    $ana = r70User('Ana Cont', '0722700777', false);
    config(['services.push.public_key' => '', 'services.push.private_key' => '']);
    $this->actingAs($ana, 'participant')->get(route('app.account'))->assertOk()->assertDontSee('data-push-card', false);

    r70Gateway();
    $this->actingAs($ana, 'participant')->get(route('app.account'))->assertOk()->assertSee('data-push-card', false)->assertSee(Vapid::publicKey(), false);

    $sw = $this->get(route('app.sw'))->assertOk()->getContent();
    expect($sw)->toContain("addEventListener('push'")->toContain("addEventListener('notificationclick'")->toContain('/notificari/necitite');
});

// ---- Evenimente -------------------------------------------------------------------------------------------------------------

it('petrecere nouă: o singură dată, către abonați, nu în orele liniștite, nu pentru ciorne', function () {
    $gw = r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 23:00:00'));
    $ana = r70User('Ana Nouă', '0722700801');
    r70User('Fără Push', '0722700802', false);
    $party = r70Party();
    r70Party(['name' => 'Ciornă', 'status' => 'draft']);

    expect(Events::partyNew())->toBe(0);   // 23:00: oră liniștită
    Carbon::setTestNow(Carbon::parse('2026-10-02 09:30:00'));
    expect(Events::partyNew())->toBe(1)->and(Events::partyNew())->toBe(0);
    $n = PushNotification::first();
    expect($n->kind)->toBe('party_new')->and($n->participant_id)->toBe($ana->id)->and($n->body)->toContain('Petrecere push')->and($n->url)->toBe('/petreceri/'.$party->id)->and($gw->sent)->toHaveCount(1);
});

it('preț care crește: doar favoritele fără bilet, o singură dată', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $fan = r70User('Fan Fără Bilet', '0722700811');
    $cuBilet = r70User('Fan Cu Bilet', '0722700812');
    $fara = r70User('Nu Favorit', '0722700813');
    $party = r70Party(['ticket_types' => [['name' => 'Bilet', 'price' => 80, 'discounts' => [['price' => 50, 'until' => '2026-10-01']], 'qty_tiers' => []]]]);
    foreach ([$fan, $cuBilet] as $p) {
        PartyInterest::create(['participant_id' => $p->id, 'party_id' => $party->id]);
    }
    TicketOrders::place($cuBilet, $party, 'Bilet', 1);

    expect(Events::priceRises())->toBe(1);
    $n = PushNotification::first();
    expect($n->participant_id)->toBe($fan->id)->and($n->kind)->toBe('price')->and($n->body)->toContain('de mâine crește')->toContain('50 lei → 80 lei');
    expect(Events::priceRises())->toBe(0);
    expect(PushNotification::query()->where('participant_id', $fara->id)->count())->toBe(0);
});

it('preț: reducerea cu oră primește mesajul cu ora, iar o reducere care nu mai scade prețul nu trimite nimic', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $fan = r70User('Fan Ora', '0722700821');
    $party = r70Party(['ticket_types' => [['name' => 'Bilet', 'price' => 60, 'discounts' => [['price' => 40, 'until' => '2026-10-02T09:00']], 'qty_tiers' => []]]]);
    PartyInterest::create(['participant_id' => $fan->id, 'party_id' => $party->id]);
    expect(Events::priceRises())->toBe(1)->and(PushNotification::first()->body)->toContain('valabil doar până la 02.10.2026 09:00');

    $far = r70Party(['name' => 'Departe', 'ticket_types' => [['name' => 'Bilet', 'price' => 60, 'discounts' => [['price' => 40, 'until' => '2026-10-20']], 'qty_tiers' => []]]]);
    PartyInterest::create(['participant_id' => $fan->id, 'party_id' => $far->id]);
    expect(Events::priceRises())->toBe(0);   // expiră peste >24 h
});

it('puține locuri: la ≤10% rămase, imediat după vânzare, doar favoritele fără bilet', function () {
    $gw = r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $fan = r70User('Fan Locuri', '0722700831');
    $party = r70Party(['tickets_for_sale' => 10]);
    PartyInterest::create(['participant_id' => $fan->id, 'party_id' => $party->id]);

    foreach ([['A', 4], ['B', 4]] as $i => [$tag, $count]) {
        TicketOrders::place(r70User('Cump '.$tag, '07227008'.(40 + $i), false), $party, 'Bilet', $count);
    }
    expect(PushNotification::query()->count())->toBe(0);   // 2 rămase = 20%

    TicketOrders::place(r70User('Cump C', '0722700850', false), $party, 'Bilet', 1);   // 1 rămas = 10%
    $n = PushNotification::first();
    expect($n->kind)->toBe('seats')->and($n->participant_id)->toBe($fan->id)->and($n->body)->toContain('un singur bilet')->and($gw->sent)->toHaveCount(1);
    expect(Events::lowSeats())->toBe(0);   // deja trimis
});

it('puține locuri în orele liniștite: așteaptă cron-ul de dimineață', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 23:30:00'));
    $fan = r70User('Fan Noapte', '0722700861');
    $party = r70Party(['tickets_for_sale' => 10]);
    PartyInterest::create(['participant_id' => $fan->id, 'party_id' => $party->id]);
    TicketOrders::place(r70User('Cump Noapte', '0722700862', false), $party, 'Bilet', 9);
    expect(PushNotification::query()->count())->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-10-02 09:15:00'));
    expect(Events::lowSeats())->toBe(1);
});

it('mâine: de la 18:00, doar deținătorii de bilete valide, fără mențiune despre bilete netrimise', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $cump = r70User('Cumpărător Mâine', '0722700871');
    $party = r70Party(['start_date' => '2026-10-02', 'location_name' => 'Club Sala']);
    TicketOrders::place($cump, $party, 'Bilet', 2);

    Carbon::setTestNow(Carbon::parse('2026-10-01 17:00:00'));
    expect(Events::partyTomorrow())->toBe(0);
    Carbon::setTestNow(Carbon::parse('2026-10-01 18:05:00'));
    expect(Events::partyTomorrow())->toBe(1)->and(Events::partyTomorrow())->toBe(0);
    $n = PushNotification::first();
    expect($n->kind)->toBe('tomorrow')->and($n->body)->toContain('Mâine, la 21:00, Club Sala')->not->toContain('netrimis')->and($n->url)->toBe('/bilete');
});

it('cu 2 ore înainte: „ești gata?” cu dresscode-ul, doar în fereastra 2h–30min', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
    $ana = r70User('Ana Gata', '0722700881');
    $party = r70Party(['start_date' => '2026-10-02', 'dresscode' => 'Alb']);
    TicketOrders::place($ana, $party, 'Bilet', 1);

    Carbon::setTestNow(Carbon::parse('2026-10-02 17:30:00'));   // 3,5 h înainte
    expect(Events::partyReady())->toBe(0);
    Carbon::setTestNow(Carbon::parse('2026-10-02 20:45:00'));   // 15 min înainte
    expect(Events::partyReady())->toBe(0);
    Carbon::setTestNow(Carbon::parse('2026-10-02 19:15:00'));   // 1h45
    expect(Events::partyReady())->toBe(1)->and(Events::partyReady())->toBe(0);
    expect(PushNotification::first()->body)->toContain('Ești gata de petrecere?')->toContain('Dresscode: Alb');
});

it('fără dresscode, mesajul „ești gata?” spune ora de început; ziua petrecerii multi-zi are dresscode-ul ei', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
    $party = r70Party(['start_date' => '2026-10-02']);
    expect(Events::dresscodeFor($party))->toBeNull();
    $multi = r70Party(['start_date' => '2026-10-02', 'days' => [['date' => '2026-10-02', 'dresscode' => 'Negru'], ['date' => '2026-10-03', 'dresscode' => 'Alb']]]);
    expect(Events::dresscodeFor($multi))->toBe('Negru');

    $ana = r70User('Ana Fără Dress', '0722700891');
    TicketOrders::place($ana, $party, 'Bilet', 1);
    Carbon::setTestNow(Carbon::parse('2026-10-02 19:30:00'));
    Events::partyReady();
    expect(PushNotification::first()->body)->toContain('Începem la 21:00');
});

it('bilet anulat: deținătorul află imediat, cu suma returnată în credite', function () {
    $gw = r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $ana = r70User('Ana Anulat', '0722700901');
    $party = r70Party();
    $t = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();

    AdminTickets::cancel($t, 'test anulare', false);
    $n = PushNotification::first();
    expect($n->kind)->toBe('ticket_cancelled')->and($n->body)->toContain($t->shortCode())->toContain('a fost anulat')->and($n->url)->toBe('/bilete')->and($gw->sent[0][1])->toBe('high');
    expect($t->fresh()->status)->toBe(Ticket::VOID);
});

it('anunț publicat și vizibil: pleacă o singură dată către abonați, fără bifă; programat pentru mai târziu sau ciornă nu pleacă', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $ana = r70User('Ana Anunț', '0722700911');
    $base = ['body' => '<p>Mare <b>noutate</b> la DXA</p>', 'audience' => 'all', 'in_carousel' => false, 'in_list' => true, 'is_active' => true, 'status' => 'published'];
    Announcement::create(array_merge($base, ['title' => 'Ciornă', 'status' => 'draft']));
    Announcement::create($base + ['title' => 'Mai târziu', 'starts_at' => '2026-10-05 10:00:00']);
    $a = Announcement::create($base + ['title' => 'Nou']);

    expect(Events::announcements())->toBe(1)->and(Events::announcements())->toBe(0);
    $n = PushNotification::first();
    expect($n->participant_id)->toBe($ana->id)->and($n->title)->toBe('Nou')->and($n->body)->toBe('Mare noutate la DXA')->and($n->url)->toBe('/anunturi/'.$a->id);
    expect($a->fresh()->push_sent_at)->not->toBeNull();

    Carbon::setTestNow(Carbon::parse('2026-10-05 10:05:00'));
    expect(Events::announcements())->toBe(1);
});

it('comanda programată rulează tot și notează ultima rulare; ștergerea contului scoate abonările', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $ana = r70User('Ana Rulare', '0722700921');
    r70Party();
    expect(Artisan::call('app:push-run'))->toBe(0)->and(Artisan::output())->toContain('party_new=1');
    expect(Settings::get('push_last_run'))->toBe('2026-10-01 12:00:00');

    ParticipantRegistry::anonymize($ana);
    expect(PushSubscription::query()->count())->toBe(0)->and(PushNotification::query()->count())->toBe(0);
});

// ---- Admin ----------------------------------------------------------------------------------------------------------------------

it('pagina Notificări din admin: cifre, listă, filtru; cere Setări + bifa „Notificări”', function () {
    r70Gateway();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $ana = r70User('Ana Admin Push', '0722700931');
    Notifier::notify($ana, 'announcement', 'a1', 'Titlu anunț', 'Text anunț');
    Notifier::notify($ana, 'ticket_cancelled', 'c1', 'Bilet anulat', 'Text bilet');

    Livewire::actingAs(r70Admin(), 'admin')->test(PushIndex::class)
        ->assertSeeHtml('data-push-subscribers')->assertSee('Titlu anunț')->assertSee('Bilet anulat')->assertSee('Ana Admin Push')
        ->set('kind', 'announcement')->assertSee('Titlu anunț')->assertDontSee('Text bilet');

    $this->actingAs(r70Admin(['settings' => 1]), 'admin')->get(route('admin.push.index'))->assertOk();
    $this->actingAs(r70Admin(['settings' => 1], []), 'admin')->get(route('admin.push.index'))->assertForbidden();
    $this->actingAs(r70Admin(['tickets' => 1, 'participants' => 2]), 'admin')->get(route('admin.push.index'))->assertForbidden();
});

it('formularul de anunț nu mai are bifă de push', function () {
    r70Gateway();
    Livewire::actingAs(r70Admin(['announcements' => 2]), 'admin')->test(Form::class)->assertDontSeeHtml('data-push-checkbox')->assertDontSee('Trimite și notificare push');
});
