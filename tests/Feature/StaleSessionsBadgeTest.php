<?php

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\BarReport;
use App\Models\Party;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\SalesGroup;
use App\Services\EntryRecorder;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (runda 48). Badge în meniu pentru casele (recepție + bar) rămase deschise de peste 12 ore, la orice petrecere;
 * încercările de intrare la o petrecere încheiată cu casa închisă ajung în Jurnal.
 */
function stlParty(array $o = []): Party
{
    return Party::create(array_merge([
        'name' => 'Petrecere veche', 'kind' => 'basic', 'start_date' => '2026-10-01', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'payment_methods' => ['cash'], 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30, 'qty_tiers' => [], 'discounts' => []]],
    ], $o));
}

function stlAdmin(?array $permissions = null): Admin
{
    return Admin::create(['name' => 'Ana Admin', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'permissions' => $permissions ?? Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => true, 'password' => 'secret-pass']);
}

function stlSession(Party $party, string $createdAt, string $status = 'open'): ReceptionSession
{
    $s = ReceptionSession::create(['party_id' => $party->id, 'session_number' => 1, 'status' => $status]);
    $s->forceFill(['created_at' => $createdAt])->save();

    return $s;
}

it('casele de recepție deschise de peste 12 ore se numără (și la petreceri încheiate); raportarea trimisă și sesiunile închise nu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();   // încheiată (4 oct, 03:00)

    $fresh = stlSession($party, '2026-10-05 00:01:00');            // 11 h 59 min: încă bine
    $old = stlSession($party, '2026-10-04 23:59:00');              // 12 h 01 min: uitată
    $closed = stlSession($party, '2026-10-01 20:00:00', 'closed');
    $submitted = stlSession($party, '2026-10-03 20:00:00');
    ReceptionReport::create(['reception_session_id' => $submitted->id, 'party_id' => $party->id, 'date' => '2026-10-03', 'status' => 'draft', 'submitted_at' => now()]);

    expect(ReceptionSession::staleOpen()->pluck('id')->all())->toBe([$old->id])
        ->and($fresh->isStale())->toBeFalse()->and($old->isStale())->toBeTrue()->and($closed->isStale())->toBeFalse();
});

it('sesiunile de bar deschise de peste 12 ore se numără; cele cu raportarea trimisă nu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();

    $fresh = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    $fresh->forceFill(['created_at' => '2026-10-05 00:01:00'])->save();
    $old = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    $old->forceFill(['created_at' => '2026-10-04 23:59:00'])->save();
    $closed = SalesGroup::create(['party_id' => $party->id, 'status' => 'closed']);
    $closed->forceFill(['created_at' => '2026-10-01 20:00:00'])->save();
    $submitted = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    $submitted->forceFill(['created_at' => '2026-10-03 20:00:00'])->save();
    BarReport::unguarded(fn () => BarReport::create(['sales_group_id' => $submitted->id, 'party_id' => $party->id, 'date' => '2026-10-03', 'submitted_at' => now()]));

    expect(SalesGroup::staleOpen()->pluck('id')->all())->toBe([$old->id]);
});

it('meniul arată badge-ul pe grupurile Recepție și Bar doar celui care le vede', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();
    stlSession($party, '2026-10-03 20:00:00');
    SalesGroup::create(['party_id' => $party->id, 'status' => 'open'])->forceFill(['created_at' => '2026-10-03 20:00:00'])->save();

    $this->actingAs(stlAdmin(), 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertSee('data-stale-badge', false)
        ->assertSee('1 case de recepție deschise de peste 12 ore')->assertSee('1 sesiuni de bar deschise de peste 12 ore')
        // Pe linkurile unde se rezolvă, tooltip-ul custom spune ce de făcut.
        ->assertSee('Intră aici și apasă „Închide casa”')->assertSee('apasă „Adu în raportare”');

    // Fără permisiuni pe Recepție / Bar nu apare niciun badge (grupurile nici nu se afișează).
    $none = ['levels' => ['parties' => Permissions::VIEW], 'actions' => []];
    $this->actingAs(stlAdmin($none), 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-stale-badge', false);
});

it('fără case uitate, meniul nu are badge', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    stlSession(stlParty(), '2026-10-05 08:00:00');

    $this->actingAs(stlAdmin(), 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-stale-badge', false);
});

it('intrare la petrecere încheiată cu casa închisă: refuzată și trecută în Jurnal (o dată la 10 minute)', function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();
    $this->actingAs(stlAdmin(), 'admin');

    $try = fn () => EntryRecorder::record($party, 'Bilet', 2);
    expect($try)->toThrow(DomainException::class);
    expect($try)->toThrow(DomainException::class);   // a doua apăsare: tot refuzată, dar fără al doilea rând în jurnal

    $logs = AdminActivityLog::where('action', 'entries.refused_closed')->get();
    expect($logs)->toHaveCount(1)
        ->and($logs[0]->description)->toContain('Petrecere veche')->toContain('2 persoane')->toContain('casa e închisă')
        ->and($logs[0]->actor_label)->toContain('Ana');

    // După 10 minute se mai înregistrează o dată.
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:11:00'));
    expect($try)->toThrow(DomainException::class);
    expect(AdminActivityLog::where('action', 'entries.refused_closed')->count())->toBe(2);
});

it('refuzul pentru o ciornă sau o petrecere încă neîncheiată nu intră în Jurnal ca „petrecere închisă”', function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $draft = stlParty(['status' => 'draft']);

    expect(fn () => EntryRecorder::record($draft, 'Bilet', 1))->toThrow(DomainException::class);
    expect(AdminActivityLog::where('action', 'entries.refused_closed')->count())->toBe(0);
});

it('petrecere încheiată dar cu casa încă deschisă: intrarea merge, fără rând de refuz', function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();
    stlSession($party, '2026-10-04 22:00:00');

    expect(EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]]))->toHaveCount(1);
    expect(AdminActivityLog::where('action', 'entries.refused_closed')->count())->toBe(0);
});

it('Raportări stoc: sesiunea de bar deschisă de peste 12 ore are marcaj, una proaspătă nu', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
    $party = stlParty();
    $group = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    $group->forceFill(['created_at' => '2026-10-04 20:00:00'])->save();
    $admin = stlAdmin();

    $this->actingAs($admin, 'admin')->get(route('admin.stock-reports.index'))->assertOk()->assertSee('deschisă de peste 12 ore — nu ai uitat să o închizi?');

    $group->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
    $this->get(route('admin.stock-reports.index'))->assertOk()->assertDontSee('nu ai uitat să o închizi?');
});
