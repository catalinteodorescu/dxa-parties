<?php

use App\Livewire\Admin\Announcements\Form;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Participant;
use App\Models\Party;
use App\Services\ParticipantRegistry;
use App\Support\AnnouncementLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 44). Butonul anunțului: link extern sau „deep link” în aplicație (petrecere / pagină). */
function alAnn(array $o = []): Announcement
{
    return Announcement::create(array_merge(['title' => 'Anunț link', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published'], $o));
}

function alParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere link', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function alUser(): Participant
{
    $p = ParticipantRegistry::create('Ana', '0722333444');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function alAdmin(): Admin
{
    return Admin::create(['name' => 'Admin link', 'phone' => '+40700'.random_int(100000, 999999), 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']);
}

it('linkul extern rămâne cum era: tab nou, etichetă implicită „Vezi mai mult”', function () {
    $a = alAnn(['url' => 'https://exemplu.ro/x']);
    $this->get(route('app.announcement', $a))->assertOk()
        ->assertSee('href="https://exemplu.ro/x" target="_blank"', false)->assertSee('Vezi mai mult');
});

it('destinație în aplicație: petrecere anume, în aceeași filă, cu eticheta aleasă sau cea implicită', function () {
    $party = alParty();
    $a = alAnn(['link_target' => 'party:'.$party->id]);

    $this->get(route('app.announcement', $a))->assertOk()
        ->assertSee('href="'.route('app.party', $party).'" wire:navigate', false)->assertSee('Vezi petrecerea')->assertDontSee('target="_blank"', false);

    $a->update(['url_label' => 'Ia bilete']);
    $this->get(route('app.announcement', $a))->assertSee('Ia bilete')->assertDontSee('Vezi petrecerea');
});

it('paginile din aplicație: linkurile corecte, inclusiv Favorite (lista=saved)', function () {
    $expected = [
        'parties' => route('app.parties'), 'saved' => route('app.parties', ['lista' => 'saved']), 'tickets' => route('app.tickets'),
        'wallet' => route('app.wallet'), 'account' => route('app.account'), 'register' => route('app.register'), 'announcements' => route('app.announcements'),
    ];
    foreach ($expected as $target => $href) {
        $r = AnnouncementLink::resolve(alAnn(['link_target' => $target]), false);
        expect($r->href)->toBe($href)->and($r->external)->toBeFalse()->and($r->label)->not->toBe('');
    }
});

it('fără buton când destinația nu se potrivește: petrecere ștearsită/ciornă/dezactivată/„doar logați”, „Creare cont” pentru cine e logat', function () {
    $party = alParty();
    $a = alAnn(['link_target' => 'party:'.$party->id]);
    expect(AnnouncementLink::resolve($a, false))->not->toBeNull();

    $party->update(['audience' => 'auth']);
    expect(AnnouncementLink::resolve($a, false))->toBeNull()->and(AnnouncementLink::resolve($a, true))->not->toBeNull();
    $party->update(['audience' => 'all', 'is_active' => false]);
    expect(AnnouncementLink::resolve($a, true))->toBeNull();
    $party->update(['is_active' => true, 'status' => 'draft']);
    expect(AnnouncementLink::resolve($a, true))->toBeNull();
    $party->update(['status' => 'published']);
    $party->delete();
    expect(AnnouncementLink::resolve($a, true))->toBeNull();

    $reg = alAnn(['link_target' => 'register']);
    expect(AnnouncementLink::resolve($reg, false))->not->toBeNull()->and(AnnouncementLink::resolve($reg, true))->toBeNull();
    expect(AnnouncementLink::resolve(alAnn(['link_target' => 'ceva-necunoscut']), false))->toBeNull()
        ->and(AnnouncementLink::resolve(alAnn(), false))->toBeNull();

    $this->actingAs(alUser(), 'participant')->get(route('app.announcement', $reg))->assertOk()->assertDontSee('data-announcement-cta', false);
});

it('formularul admin: mod extern cere adresa, mod aplicație cere o destinație validă; modurile se exclud', function () {
    $this->actingAs(alAdmin(), 'admin');
    $party = alParty();

    $c = Livewire::test(Form::class)->set('title', 'Cu link')->set('link_mode', 'external')->set('url', '')->call('save')->assertHasErrors('url');
    $c->set('link_mode', 'app')->set('link_target', '')->call('save')->assertHasErrors('link_target');
    $c->set('link_target', 'party:999999')->call('save')->assertHasErrors('link_target');
    $c->set('link_target', 'nimic')->call('save')->assertHasErrors('link_target');

    $c->set('url', 'https://vechi.ro')->set('link_target', 'party:'.$party->id)->set('url_label', 'Hai')->call('save')->assertHasNoErrors();
    $a = Announcement::query()->where('title', 'Cu link')->firstOrFail();
    expect($a->link_target)->toBe('party:'.$party->id)->and($a->url)->toBeNull()->and($a->url_label)->toBe('Hai')->and($a->hasLink())->toBeTrue();

    // editare: modul se recunoaște; trecerea la „fără buton” șterge destinația și eticheta
    $e = Livewire::test(Form::class, ['announcement' => $a])->assertSet('link_mode', 'app')->assertSet('link_target', 'party:'.$party->id);
    $e->set('link_mode', 'none')->call('save')->assertHasNoErrors();
    expect($a->fresh()->link_target)->toBeNull()->and($a->fresh()->url)->toBeNull()->and($a->fresh()->url_label)->toBeNull()->and($a->fresh()->hasLink())->toBeFalse();

    // extern: păstrează url, nu destinația
    Livewire::test(Form::class, ['announcement' => $a->fresh()])->set('link_mode', 'external')->set('url', 'https://nou.ro')->call('save')->assertHasNoErrors();
    expect($a->fresh()->url)->toBe('https://nou.ro')->and($a->fresh()->link_target)->toBeNull();
});

it('selectul din admin listă paginile și petrecerile publicate (nu ciornele)', function () {
    $pub = alParty(['name' => 'Publicată']);
    $draft = alParty(['name' => 'Ciornă', 'status' => 'draft']);
    $o = AnnouncementLink::options();

    expect($o)->toHaveKey('wallet')->toHaveKey('saved')->toHaveKey('party:'.$pub->id)->not->toHaveKey('party:'.$draft->id)
        ->and($o['party:'.$pub->id])->toContain('Publicată')->and($o[''])->toBe('Alege destinația…');
    expect(AnnouncementLink::isValidTarget('party:'.$draft->id))->toBeFalse()->and(AnnouncementLink::isValidTarget('party:'.$pub->id))->toBeTrue();
});
