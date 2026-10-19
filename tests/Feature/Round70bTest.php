<?php

use App\Livewire\Admin\ParticipantApp\NotificationTypes;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Services\ParticipantRegistry;
use App\Services\Push\Events;
use App\Services\Push\Notifier;
use App\Services\Push\PushGateway;
use App\Services\Push\Vapid;
use App\Support\Permissions;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 70b). Permisiunea „Aplicație participanți”, bifele pe tip de notificare, baza pentru anunțurile existente, comanda cu date de test. */
class R70bFakeGateway implements PushGateway
{
    public int $sent = 0;

    public function send(PushSubscription $subscription, string $urgency = 'normal'): int
    {
        $this->sent++;

        return 201;
    }
}

function r70bSetup(): R70bFakeGateway
{
    // chei de test; unde openssl nu generează curbe EC (PHP wasm), pereche de unică folosință
    try {
        $keys = Vapid::generate();
    } catch (RuntimeException) {
        $keys = ['public' => 'BP5_CEc-TRX2BdLFsiS5b-WC4UnQ0scFhux5Akxe8Zt2mHSPORwoBh9s5HCJL0Lqtrbsbmglgq92TGZosMt4NfA', 'private' => 'Y74jOdkc-Pff3aA3Q4R3YdxbVwwot5uZJpDKQTdMEFg'];
    }
    config(['services.push.public_key' => $keys['public'], 'services.push.private_key' => $keys['private'], 'services.push.subject' => 'mailto:test@exemplu.ro']);
    $fake = new R70bFakeGateway;
    app()->instance(PushGateway::class, $fake);

    return $fake;
}

function r70bAdmin(array $levels, array $actions = []): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => $actions],
    ]);
}

function r70bUser(string $phone): Participant
{
    $p = ParticipantRegistry::create('Test '.$phone, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    Notifier::subscribe($p, 'https://push.example.com/send/'.bin2hex(random_bytes(8)));

    return $p->fresh();
}

it('nu există secțiune „Aplicație participanți” în permisiuni; notificările sunt o bifă sub Setări', function () {
    expect(Permissions::SECTIONS)->not->toHaveKey('participant_app')->not->toHaveKey('push')
        ->and(Permissions::GROUPS)->not->toHaveKey('participant_app')
        ->and(Permissions::ACTIONS['manage_notifications'][0])->toBe('Notificări')
        ->and(Permissions::ACTIONS['manage_notifications'][2])->toBe('settings');
});

it('pagina Ultimele notificări cere Setări + bifa; meniul nu mai are grupul „Aplicație participanți”', function () {
    r70bSetup();
    $this->actingAs(r70bAdmin(['participants' => 3, 'settings' => 1]), 'admin')->get(route('admin.push.index'))->assertForbidden();
    $this->actingAs(r70bAdmin(['participants' => 3], ['manage_notifications']), 'admin')->get(route('admin.push.index'))->assertForbidden();

    $this->actingAs(r70bAdmin(['settings' => 1], ['manage_notifications']), 'admin')->get(route('admin.push.index'))->assertOk()->assertSee('Ultimele notificări');
    $menu = $this->actingAs(r70bAdmin(['settings' => 1], ['manage_notifications']), 'admin')->get(route('admin.dashboard'))->getContent();
    expect($menu)->not->toContain(route('admin.push.index'));
});

it('în Setări › Aplicație participanți, secțiunea de notificări apare doar cu bifa și deasupra textelor SMS', function () {
    r70bSetup();
    $html = $this->actingAs(r70bAdmin(['settings' => 2], ['manage_notifications']), 'admin')->get(route('admin.settings.participant-app'))->assertOk()->getContent();
    expect($html)->toContain('data-push-types')->toContain('data-push-log-link')->toContain(route('admin.push.index'));
    expect(strpos($html, 'data-push-types'))->toBeLessThan(strpos($html, 'Texte SMS'));
    $fara = $this->actingAs(r70bAdmin(['settings' => 2]), 'admin')->get(route('admin.settings.participant-app'))->assertOk()->getContent();
    expect($fara)->not->toContain('data-push-types');
});

it('fiecare tip are exemplu; tipurile din petrecere/preț/locuri au „între 22:00 și 09:00” în paranteză; „ready” nu mai zice de dresscode în descriere', function () {
    foreach (Notifier::TYPES as $k => $t) {
        expect($t[2])->toHaveCount(2)->and($t[2][0])->not->toBe('');
    }
    foreach (['party_new', 'price', 'seats'] as $k) {
        expect(Notifier::TYPES[$k][1])->toMatch('/\(.*22:00.*09:00.*\)/u');
    }
    foreach (['tomorrow', 'ready', 'ticket_cancelled', 'announcement'] as $k) {
        expect(Notifier::TYPES[$k][1])->not->toContain('22:00');
    }
    expect(Notifier::TYPES['ready'][1])->not->toContain('dresscode');
});

it('migrarea 2024_08_13 scoate nivelul „participant_app” de la admini', function () {
    $admin = r70bAdmin(['participant_app' => 2, 'tickets' => 1]);
    (require database_path('migrations/2024_08_13_000001_remove_participant_app_permission.php'))->up();
    expect($admin->fresh()->permissions['levels'])->toBe(['tickets' => 1]);
});

it('migrarea scoate permisiunea „push” acordată automat și marchează anunțurile deja vizibile ca trimise', function () {
    $admin = r70bAdmin(['push' => 1, 'tickets' => 1]);
    $base = ['body' => 'x', 'audience' => 'all', 'in_carousel' => false, 'in_list' => true, 'is_active' => true];
    $vizibil = Announcement::create($base + ['title' => 'Vechi', 'status' => 'published']);
    $ciorna = Announcement::create($base + ['title' => 'Ciornă', 'status' => 'draft']);
    $programat = Announcement::create($base + ['title' => 'Programat', 'status' => 'published', 'starts_at' => now()->addDays(3)]);

    (require database_path('migrations/2024_08_12_000001_adjust_push_for_participant_app.php'))->up();

    expect($admin->fresh()->permissions['levels'])->toBe(['tickets' => 1])
        ->and($vizibil->fresh()->push_sent_at)->not->toBeNull()
        ->and($ciorna->fresh()->push_sent_at)->toBeNull()
        ->and($programat->fresh()->push_sent_at)->toBeNull()
        ->and(Schema::hasColumn('announcements', 'push'))->toBeFalse();
});

it('adminul cu Modificare pornește/oprește tipuri; cu Vizualizare doar vede; tipul oprit nu se trimite', function () {
    $gw = r70bSetup();
    $ana = r70bUser('0722701001');

    Livewire::actingAs(r70bAdmin(['settings' => 2]), 'admin')->test(NotificationTypes::class)->call('toggleType', 'price')->assertForbidden();
    expect(Notifier::typeEnabled('price'))->toBeTrue();

    $editor = Livewire::actingAs(r70bAdmin(['settings' => 2], ['manage_notifications']), 'admin')->test(NotificationTypes::class);
    $editor->assertSeeHtml('wire:click="toggleType(\'ticket_cancelled\')"')->call('toggleType', 'ticket_cancelled');
    expect(Notifier::typeEnabled('ticket_cancelled'))->toBeFalse()->and(Settings::get('push_type_ticket_cancelled'))->toBe('0');
    expect(Notifier::notify($ana, 'ticket_cancelled', 'k1', 'T', 'B'))->toBeNull()->and($gw->sent)->toBe(0);
    expect(Notifier::notify($ana, 'test', 'k2', 'T', 'B'))->not->toBeNull();   // „test” nu se poate opri

    $editor->call('toggleType', 'ticket_cancelled');
    expect(Notifier::typeEnabled('ticket_cancelled'))->toBeTrue();
    $editor->call('toggleType', 'nu_exista');   // nu face nimic
});

it('tip oprit nu amână: petrecerea și anunțul se consideră tratate și nu pleacă după repornire', function () {
    r70bSetup();
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    r70bUser('0722701002');
    Notifier::setType('party_new', false);
    Notifier::setType('announcement', false);
    $party = Party::create(['name' => 'P oprit', 'kind' => 'basic', 'start_date' => '2026-10-10', 'start_time' => '21:00', 'end_time' => '03:00', 'is_free' => false, 'audience' => 'all', 'in_carousel' => false,
        'is_active' => true, 'status' => 'published', 'payment_methods' => ['cash'], 'online_sales' => true, 'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]]]);
    $a = Announcement::create(['title' => 'A oprit', 'body' => 'x', 'audience' => 'all', 'in_carousel' => false, 'in_list' => true, 'is_active' => true, 'status' => 'published']);

    expect(Events::partyNew())->toBe(0)->and(Events::announcements())->toBe(0);
    Notifier::setType('party_new', true);
    Notifier::setType('announcement', true);
    expect(Events::partyNew())->toBe(0)->and(Events::announcements())->toBe(0)->and(PushNotification::query()->count())->toBe(0)->and($a->fresh()->push_sent_at)->not->toBeNull();
});

it('comanda cu date de test trimite câte o notificare din fiecare tip doar către contul dat, apoi --clean șterge tot', function () {
    $gw = r70bSetup();
    Notifier::setType('buyers', true);   // runda 70d: implicit oprit
    $me = r70bUser('0722701003');
    $altul = r70bUser('0722701004');

    expect(Artisan::call('app:push-demo', ['phone' => '0722701003', '--pause' => 0]))->toBe(0);
    $kinds = PushNotification::query()->where('participant_id', $me->id)->pluck('kind')->sort()->values()->all();
    expect($kinds)->toBe(['announcement', 'buyers', 'card_ready', 'credit_load', 'credit_payment', 'party_new', 'price', 'price', 'ready', 'seats', 'ticket_cancelled', 'ticket_new', 'tomorrow'])
        ->and(PushNotification::query()->where('participant_id', $altul->id)->count())->toBe(0)
        ->and($gw->sent)->toBe(13)->and(Carbon::hasTestNow())->toBeFalse();
    expect(Party::query()->where('name', 'like', '[TEST PUSH]%')->count())->toBe(7)
        ->and(PushNotification::query()->where('kind', 'ready')->first()->body)->toContain('Dresscode: Alb')
        ->and(PushNotification::query()->where('kind', 'seats')->first()->body)->toContain('un singur bilet');

    expect(Artisan::call('app:push-demo', ['phone' => '0722701003', '--clean' => true, '--pause' => 0]))->toBe(0);
    expect(Party::query()->where('name', 'like', '[TEST PUSH]%')->count())->toBe(0)
        ->and(Announcement::query()->where('title', 'like', '[TEST PUSH]%')->count())->toBe(0)
        ->and(PushNotification::query()->count())->toBe(0)
        ->and(Participant::query()->where('phone', 'like', '+40799000%')->count())->toBe(0)
        ->and(Participant::query()->whereKey($me->id)->exists())->toBeTrue();
});

it('comanda cu date de test refuză: în producție, fără cont, fără abonare, fără chei', function () {
    r70bSetup();
    $fara = ParticipantRegistry::create('Fără abonare', '0722701005');
    expect(Artisan::call('app:push-demo', ['phone' => '0700000000']))->toBe(1);   // nu există
    expect(Artisan::call('app:push-demo', ['phone' => '0722701005']))->toBe(1)->and(Artisan::output())->toContain('nicio abonare');

    r70bUser('0722701006');
    app()->detectEnvironment(fn () => 'production');
    expect(Artisan::call('app:push-demo', ['phone' => '0722701006']))->toBe(1)->and(Artisan::output())->toContain('producție');
    app()->detectEnvironment(fn () => 'testing');

    config(['services.push.public_key' => '', 'services.push.private_key' => '']);
    expect(Artisan::call('app:push-demo', ['phone' => '0722701006']))->toBe(1)->and(Artisan::output())->toContain('nu e configurat');
    expect($fara->exists)->toBeTrue();
});
