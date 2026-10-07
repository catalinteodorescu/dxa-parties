<?php

use App\Livewire\Admin\Announcements\Index as AnnouncementsIndex;
use App\Livewire\Admin\Parties\Index;
use App\Livewire\Admin\Parties\Show;
use App\Livewire\Admin\Parties\Stats;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Participant;
use App\Models\Party;
use App\Services\ContentStats;
use App\Services\ParticipantRegistry;
use App\Services\PartyInterests;
use App\Services\TicketOrders;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => ContentStats::flush());
afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 40). Contoare reale de afișări / deschideri / click-uri (petreceri + anunțuri) și blocul „Interes în aplicație”. */
const CNT_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

function cntParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere contoare', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function cntAnn(array $o = []): Announcement
{
    return Announcement::create(array_merge(['title' => 'Anunț contoare', 'url' => 'https://exemplu.ro', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'published'], $o));
}

function cntUser(string $phone = '0722111222', string $name = 'Ana'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function cntPost(array $events, string $ua = CNT_UA, string $ip = '10.0.0.1')
{
    return test()->withHeaders(['User-Agent' => $ua])->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/m', ['e' => $events]);
}

it('numără afișări și click-uri trimise în grup, cu total și unici pe zi', function () {
    $party = cntParty();
    $ann = cntAnn();

    cntPost([['p', $party->id, 'i'], ['a', $ann->id, 'i'], ['p', $party->id, 'a']])->assertNoContent();
    cntPost([['p', $party->id, 'i']])->assertNoContent();                                  // același vizitator: total +1, unici neschimbat
    cntPost([['p', $party->id, 'i']], 'Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile', '10.0.0.2')->assertNoContent();   // alt vizitator

    $t = ContentStats::totals('party', $party->id);
    expect($t['impression'])->toBe(3)->and($t['impression_u'])->toBe(2)->and($t['action'])->toBe(1)->and($t['action_u'])->toBe(1)->and($t['open'])->toBe(0);
    $a = ContentStats::totals('announcement', $ann->id);
    expect($a['impression'])->toBe(1);
});

it('nu numără boții, fără User-Agent, adminii conectați, elementele nepublicate sau inexistente, nici evenimentele necunoscute', function () {
    $party = cntParty();
    $draft = cntParty(['status' => 'draft', 'name' => 'Ciornă']);

    cntPost([['p', $party->id, 'i']], 'Googlebot/2.1');
    cntPost([['p', $party->id, 'i']], 'curl/8.0');
    cntPost([['p', $draft->id, 'i'], ['p', 9999, 'i'], ['x', $party->id, 'i'], ['p', $party->id, 'zzz'], 'gunoi', ['p', 'abc', 'i']]);

    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']), 'admin');
    cntPost([['p', $party->id, 'i']]);

    expect(ContentStats::totals('party', $party->id)['impression'])->toBe(0)->and(ContentStats::totals('party', $draft->id)['impression'])->toBe(0);
});

it('limitează un lot la 40 de evenimente', function () {
    $events = [];
    for ($i = 0; $i < 45; $i++) {
        $events[] = ['a', cntAnn(['title' => 'Anunț '.$i])->id, 'i'];
    }
    cntPost($events);

    $counted = (int) DB::table('content_stats')->where('event', 'impression')->sum('total');
    expect($counted)->toBe(ContentStats::MAX_BATCH);
});

it('deschiderea paginii petrecerii și a anunțului se numără pe server', function () {
    $party = cntParty();
    $ann = cntAnn();

    test()->withHeaders(['User-Agent' => CNT_UA])->get(route('app.party', $party))->assertOk();
    test()->withHeaders(['User-Agent' => CNT_UA])->get(route('app.announcement', $ann))->assertOk();
    test()->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get(route('app.party', $party))->assertOk();

    expect(ContentStats::totals('party', $party->id)['open'])->toBe(1)->and(ContentStats::totals('announcement', $ann->id)['open'])->toBe(1);
});

it('paginile din aplicație conțin directiva de contor și butonul „Cumpără” are marcajul de click', function () {
    $party = cntParty(['in_carousel' => true]);
    $ann = cntAnn(['in_carousel' => true]);

    test()->get('/')->assertOk()->assertSee("x-track=\"'p:{$party->id}'\"", false)->assertSee("x-track=\"'a:{$ann->id}'\"", false)->assertSee('__paTrack', false);
    test()->get(route('app.party', $party))->assertSee("data-track-action=\"p:{$party->id}\"", false);
    test()->get(route('app.announcement', $ann))->assertSee("data-track-action=\"a:{$ann->id}\"", false);
});

it('adminul vede cifrele reale în lista de anunțuri, nu cele demo', function () {
    $ann = cntAnn();
    cntPost([['a', $ann->id, 'i']]);
    cntPost([['a', $ann->id, 'i']], 'Mozilla/5.0 (X11; Linux) Firefox/120', '10.0.0.9');
    test()->withHeaders(['User-Agent' => CNT_UA])->get(route('app.announcement', $ann));
    cntPost([['a', $ann->id, 'a']]);

    ContentStats::flush();
    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']), 'admin');

    Livewire::test(AnnouncementsIndex::class)->assertSee('Afișări în aplicație')->assertSee('Click-uri pe butonul cu link')->assertDontSee('(demo)');
    expect($ann->statViews())->toBe(2)->and($ann->statOpens())->toBe(1)->and($ann->statActions())->toBe(1);
});

it('statistici petrecere: blocul „Interes în aplicație” cu pâlnia și interesații care au cumpărat', function () {
    $party = cntParty();
    $ana = cntUser('0722111222', 'Ana');
    $bob = cntUser('0733111222', 'Bob');
    PartyInterests::toggle($ana, $party);
    PartyInterests::toggle($bob, $party);
    TicketOrders::place($ana, $party, 'Bilet', 1);

    cntPost([['p', $party->id, 'i']]);
    cntPost([['p', $party->id, 'a']]);
    test()->withHeaders(['User-Agent' => CNT_UA])->get(route('app.party', $party));
    ContentStats::flush();

    $f = ContentStats::partyFunnel($party);
    expect($f['interested'])->toBe(2)->and($f['interested_bought'])->toBe(1)->and($f['impression'])->toBe(1)->and($f['open'])->toBe(1)->and($f['action'])->toBe(1);

    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');
    Livewire::test(Stats::class, ['party' => $party])->assertSee('Interes în aplicație')->assertSee('Interesați (inimă)')->assertSee('1 au și bilet (50,0%)')->assertSee('Click „Cumpără bilete”');
});

it('admin: contorul de interesați apare în lista de petreceri și în pagina petrecerii', function () {
    $party = cntParty();
    PartyInterests::toggle(cntUser('0722111222', 'Ana'), $party);
    PartyInterests::toggle(cntUser('0733111222', 'Bob'), $party);
    PartyInterests::toggle(cntUser('0744111222', 'Cip'), $party);

    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');

    Livewire::test(Index::class)->assertSee('data-interested-count', false)->assertSee('Interesați (au adăugat petrecerea la favorite)');
    expect($party->fresh()->loadCount('interests')->interests_count)->toBe(3);
    Livewire::test(Show::class, ['party' => $party])->assertSee('Interesați')->assertSee('data-interested-count', false);
});
