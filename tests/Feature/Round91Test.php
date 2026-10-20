<?php

use App\Livewire\Bar\Notifications as BarNotifications;
use App\Livewire\Participant\Notifications as ParticipantNotifications;
use App\Livewire\Reception\Notifications as ReceptieNotifications;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\PushNotification;
use App\Services\Push\Inbox;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function r91Participant(string $phone = '0722910001'): Participant
{
    return Participant::create(['uuid' => (string) Str::uuid(), 'name' => 'Ioana '.$phone, 'phone' => $phone, 'password' => 'parola-sigura', 'phone_verified_at' => now()]);
}

function r91Staff(array $access = ['reception', 'bar']): Admin
{
    return Admin::create([
        'name' => 'Cont '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass',
        'access_admin' => false, 'access_bar' => in_array('bar', $access, true), 'access_reception' => in_array('reception', $access, true),
        'permissions' => Permissions::legacyAdmin(),
    ]);
}

function r91Note(array $over = []): PushNotification
{
    $read = $over['read'] ?? false;
    $at = $over['created_at'] ?? null;
    unset($over['read'], $over['created_at']);
    $n = PushNotification::create($over + ['kind' => 'announcement', 'dedupe_key' => 'k'.Str::random(8), 'title' => 'Titlu', 'body' => 'Text', 'url' => '/']);
    $n->forceFill(array_filter(['read_at' => $read ? now() : null, 'created_at' => $at]))->save();

    return $n;
}

it('participant: lista arată doar notificările lui, cele mai noi primele, grupate pe zile, fără cele de test', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 15:00:00'));
    $ana = r91Participant();
    $bob = r91Participant('0722910002');
    r91Note(['participant_id' => $ana->id, 'title' => 'Veche A', 'created_at' => Carbon::parse('2026-10-18 10:00')]);
    r91Note(['participant_id' => $ana->id, 'title' => 'De ieri A', 'created_at' => Carbon::parse('2026-10-19 10:00')]);
    r91Note(['participant_id' => $ana->id, 'title' => 'De azi A']);
    r91Note(['participant_id' => $ana->id, 'title' => 'Doar test A', 'kind' => 'test']);
    r91Note(['participant_id' => $bob->id, 'title' => 'A lui Bob']);

    $html = Livewire::actingAs($ana, 'participant')->test(ParticipantNotifications::class)
        ->assertSee('De azi A')->assertSee('De ieri A')->assertSee('Veche A')->assertSee('Azi')->assertSee('Ieri')->assertSee('18.10.2026')
        ->assertDontSee('Doar test A')->assertDontSee('A lui Bob')->html();

    expect(strpos($html, 'De azi A'))->toBeLessThan(strpos($html, 'De ieri A'))->and(strpos($html, 'De ieri A'))->toBeLessThan(strpos($html, 'Veche A'));
});

it('participant: pagina goală are mesaj; marcarea ca citite și deschiderea merg, doar în lista proprie', function () {
    $ana = r91Participant();
    $bob = r91Participant('0722910002');

    Livewire::actingAs($ana, 'participant')->test(ParticipantNotifications::class)->assertSeeHtml('data-notifications-empty');

    $mine = r91Note(['participant_id' => $ana->id, 'title' => 'A mea', 'url' => '/petreceri/5']);
    $other = r91Note(['participant_id' => $bob->id, 'title' => 'A lui Bob', 'url' => '/cont']);
    r91Note(['participant_id' => $ana->id, 'title' => 'A doua']);

    expect(Inbox::unread(Inbox::forParticipant($ana->id)))->toBe(2);

    $c = Livewire::actingAs($ana, 'participant')->test(ParticipantNotifications::class)->assertSeeHtml('data-mark-all');
    $c->call('open', $other->id)->assertNoRedirect();                       // a altcuiva: nu se deschide, nu se marchează
    expect($other->fresh()->read_at)->toBeNull();

    $c->call('open', $mine->id)->assertRedirect('/petreceri/5');
    expect($mine->fresh()->read_at)->not->toBeNull()->and(Inbox::unread(Inbox::forParticipant($ana->id)))->toBe(1);

    $c->call('markAll');
    expect(Inbox::unread(Inbox::forParticipant($ana->id)))->toBe(0)->and($other->fresh()->read_at)->toBeNull();
});

it('adresa din notificare: doar din aplicație, altfel pagina de start', function () {
    expect(Inbox::safeUrl('/petreceri/3', '/'))->toBe('/petreceri/3')
        ->and(Inbox::safeUrl('https://rau.example/x', '/acasa'))->toBe('/acasa')
        ->and(Inbox::safeUrl('//rau.example/x', '/acasa'))->toBe('/acasa')
        ->and(Inbox::safeUrl('', '/acasa'))->toBe('/acasa');

    $ana = r91Participant();
    $n = r91Note(['participant_id' => $ana->id, 'url' => 'https://rau.example/x']);
    Livewire::actingAs($ana, 'participant')->test(ParticipantNotifications::class)->call('open', $n->id)->assertRedirect('/');
});

it('numărul de necitite: endpoint pentru participant și clopoțel cu insignă în antet', function () {
    $ana = r91Participant();
    r91Note(['participant_id' => $ana->id]);
    r91Note(['participant_id' => $ana->id]);
    r91Note(['participant_id' => $ana->id, 'read' => true]);

    $this->getJson(route('app.notifications.count'))->assertUnauthorized();
    $this->actingAs($ana, 'participant')->getJson(route('app.notifications.count'))->assertOk()->assertJson(['n' => 2]);

    $this->actingAs($ana, 'participant')->get(route('app.home'))->assertOk()
        ->assertSee('data-bell', false)->assertSee('data-inbox-badge', false)->assertSee(route('app.notifications'), false);
});

it('participant: pagina cere cont; panoul de activare nu mai are „Dezactivează” sau „Test”', function () {
    $this->get(route('app.notifications'))->assertRedirect();

    $card = file_get_contents(resource_path('views/livewire/participant/_push-card.blade.php'));
    expect($card)->toContain('data-act="enable"')->toContain('data-hide-on="on"')->not->toContain('data-act="disable"')->not->toContain('data-act="test"');

    $this->actingAs(r91Participant(), 'participant')->get(route('app.notifications'))->assertOk()->assertSee('Notificări');
});

it('Recepție și Bar: fiecare aplicație își vede doar alertele ei, ale contului cu care s-a făcut login', function () {
    $a = r91Staff();
    $b = r91Staff();
    r91Note(['admin_id' => $a->id, 'app' => 'reception', 'kind' => 'r_open', 'title' => 'Deschide casa', 'url' => '/receptie']);
    r91Note(['admin_id' => $a->id, 'app' => 'bar', 'kind' => 'b_low_stock', 'title' => 'Stoc sub minim', 'url' => '/bar']);
    r91Note(['admin_id' => $b->id, 'app' => 'reception', 'kind' => 'r_open', 'title' => 'Alt cont']);
    r91Note(['admin_id' => $a->id, 'app' => 'reception', 'kind' => 'test', 'title' => 'Test staff']);

    $this->actingAs($a, 'admin');

    Livewire::test(ReceptieNotifications::class)->assertSee('Deschide casa')->assertDontSee('Stoc sub minim')->assertDontSee('Alt cont')->assertDontSee('Test staff')
        ->assertSee('Deschide casa');                                   // eticheta tipului vine din tipurile de personal
    Livewire::test(BarNotifications::class)->assertSee('Stoc sub minim')->assertSee('Stoc sub minim')->assertDontSee('Deschide casa');

    expect(Inbox::unread(Inbox::forStaff($a->id, 'reception')))->toBe(1)->and(Inbox::unread(Inbox::forStaff($a->id, 'bar')))->toBe(1);
});

it('Recepție / Bar: atingerea marchează citit și deschide adresa; „toate citite” nu atinge celelalte conturi', function () {
    $a = r91Staff();
    $b = r91Staff();
    $mine = r91Note(['admin_id' => $a->id, 'app' => 'reception', 'kind' => 'r_open', 'url' => '/receptie/raportare']);
    $theirs = r91Note(['admin_id' => $b->id, 'app' => 'reception', 'kind' => 'r_open']);
    $this->actingAs($a, 'admin');

    Livewire::test(ReceptieNotifications::class)->call('open', $theirs->id)->assertNoRedirect()
        ->call('open', $mine->id)->assertRedirect('/receptie/raportare');
    expect($mine->fresh()->read_at)->not->toBeNull()->and($theirs->fresh()->read_at)->toBeNull();

    r91Note(['admin_id' => $a->id, 'app' => 'reception', 'kind' => 'r_open']);
    Livewire::test(ReceptieNotifications::class)->call('markAll');
    expect(Inbox::unread(Inbox::forStaff($a->id, 'reception')))->toBe(0)->and($theirs->fresh()->read_at)->toBeNull();
});

it('Recepție / Bar: pagina, clopoțelul cu insignă și numărătoarea; panoul are doar „Activează”', function () {
    $a = r91Staff();
    r91Note(['admin_id' => $a->id, 'app' => 'bar', 'kind' => 'b_open', 'title' => 'Deschide barul']);
    $this->actingAs($a, 'admin');

    $this->getJson(route('bar.notifications.count'))->assertOk()->assertJson(['n' => 1]);
    $this->getJson(route('receptie.notifications.count'))->assertOk()->assertJson(['n' => 0]);

    $this->get(route('bar.notifications'))->assertOk()->assertSee('Deschide barul')->assertSee('data-bell', false)->assertSee(route('bar.notifications.count', [], false), false);
    $this->get(route('receptie.notifications'))->assertOk()->assertSee('Notificări');

    $view = file_get_contents(resource_path('views/livewire/staff/notifications.blade.php'));
    expect($view)->toContain('data-act="enable"')->toContain('data-hide-on="on"')->not->toContain('data-act="disable"')->not->toContain('data-act="test"');
});

it('conturile fără acces la o aplicație nu-i văd pagina de notificări', function () {
    $onlyBar = r91Staff(['bar']);
    $this->actingAs($onlyBar, 'admin');

    $this->get(route('receptie.notifications'))->assertForbidden();
    $this->get(route('bar.notifications'))->assertOk();
});

it('panoul de activare din participanți și personal se ascunde singur după activare', function () {
    $js = file_get_contents(resource_path('views/layouts/partials-push-init.blade.php'));

    expect($js)->toContain('hideOn')->toContain("root.style.display = 'none'");
});
