<?php

use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\TokenTransaction;
use App\Services\CreditLedger;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (runda 9 - ora de sfârșit la Recepție). După ora de sfârșit petrecerea rămâne selectabilă și se poate
 * înregistra (intrări, tokeni, credite) cât timp există o sesiune de recepție deschisă, ca la bar; după închiderea casei
 * (raportare finalizată) nu mai. Prima înregistrare după sfârșit, fără sesiune deschisă, e refuzată.
 */
function etAdmin(): Admin
{
    return Admin::create([
        'name' => 'Recepționer test',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(),
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026 21:00–03:00 (se termină pe 4.10 la 03:00); „acum” = 23:00, în desfășurare. */
function etParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    PaymentMethods::setTokenRate(6);
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);

    return Party::create(array_merge([
        'name' => 'Petrecere sfârșit',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => ['cash', 'card'],
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ], $overrides));
}

function etEnter(Party $party, Admin $admin): void
{
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
}

/** Trece ceasul după ora de sfârșit (4.10.2026, 04:30). */
function etAfterEnd(): void
{
    Carbon::setTestNow(Carbon::parse('2026-10-04 04:30:00'));
}

function etCloseHouse(Party $party, Admin $admin): void
{
    $report = ReceptionReport::startFor(ReceptionSession::currentFor($party->id), $admin->id);
    $report->update(['counted_cash' => 30]);
    $report->finalize($admin->id);
}

it('după sfârșit, petrecerea cu sesiune deschisă rămâne în forReception; după închiderea casei dispare', function () {
    $admin = etAdmin();
    $party = etParty();
    etEnter($party, $admin);

    etAfterEnd();
    expect($party->fresh()->state())->toBe('past')
        ->and(Party::forReception()->pluck('id')->all())->toBe([$party->id]);

    etCloseHouse($party, $admin);
    expect(Party::forReception()->pluck('id')->all())->toBe([]);
});

it('petrecerile încheiate fără sesiune deschisă, ciornele și cele dezactivate nu apar în forReception', function () {
    $admin = etAdmin();
    $party = etParty();
    etEnter($party, $admin); // sesiune deschisă

    $draft = etParty(['name' => 'Ciornă', 'status' => 'draft']);
    $inactive = etParty(['name' => 'Inactivă', 'is_active' => false]);
    foreach ([$draft, $inactive] as $p) {
        ReceptionSession::query()->create(['party_id' => $p->id, 'session_number' => 1, 'status' => 'open']);
    }
    $old = etParty(['name' => 'Veche fără sesiune', 'start_date' => '2026-09-01']);

    // Sesiunea deschisă nu „înviază” o ciornă sau o petrecere dezactivată; fără sesiune, cea încheiată rămâne afară.
    expect(Party::forReception()->pluck('name')->all())->toBe(['Petrecere sfârșit'])
        ->and(Party::forReception()->pluck('id')->all())->not->toContain($old->id);
});

it('cât timp sesiunea e deschisă, după sfârșit merg intrările, tokenii și creditele, pe aceeași sesiune', function () {
    $admin = etAdmin();
    $party = etParty();
    etEnter($party, $admin);
    $session = ReceptionSession::currentFor($party->id);

    etAfterEnd();
    expect($party->fresh()->acceptsReceptionRecords())->toBeTrue();

    $party = $party->fresh();
    etEnter($party, $admin);
    $tx = TokenLedger::sell($party, 5, [['method' => 'cash', 'amount' => 30]], $admin->id);
    $buyer = ParticipantRegistry::create('Cumpărător Târziu', '0722'.random_int(100000, 999999));
    $credit = CreditLedger::sell($party, $buyer, 50, [['method' => 'cash', 'amount' => 50]], $admin->id);

    expect(ReceptionSession::count())->toBe(1)
        ->and(PartyEntry::where('reception_session_id', $session->id)->count())->toBe(2)
        ->and($tx->reception_session_id)->toBe($session->id)
        ->and($credit->reception_session_id)->toBe($session->id);
});

it('după închiderea casei, o petrecere încheiată nu mai primește nimic și nu se deschide sesiune nouă', function () {
    $admin = etAdmin();
    $party = etParty();
    etEnter($party, $admin);
    etAfterEnd();
    etCloseHouse($party, $admin);

    $party = $party->fresh();
    $buyer = ParticipantRegistry::create('Cumpărător Închis', '0722'.random_int(100000, 999999));

    expect($party->acceptsReceptionRecords())->toBeFalse()
        ->and(fn () => etEnter($party, $admin))->toThrow(DomainException::class, 'nu primește intrări')
        ->and(fn () => TokenLedger::sell($party, 5, [['method' => 'cash', 'amount' => 30]], $admin->id))->toThrow(DomainException::class, 'nu primește vânzări')
        ->and(fn () => CreditLedger::sell($party, $buyer, 50, [['method' => 'cash', 'amount' => 50]], $admin->id))->toThrow(DomainException::class, 'nu primește vânzări');

    expect(ReceptionSession::count())->toBe(1)
        ->and(PartyEntry::count())->toBe(1)
        ->and(TokenTransaction::count())->toBe(0)
        ->and(CreditTransaction::query()->where('source', CreditTransaction::SOURCE_RECEPTION)->count())->toBe(0);
});

it('prima înregistrare după sfârșit, fără nicio sesiune deschisă, e refuzată', function () {
    $admin = etAdmin();
    $party = etParty();
    etAfterEnd();

    expect(fn () => etEnter($party->fresh(), $admin))->toThrow(DomainException::class, 'nu primește intrări');
    expect(ReceptionSession::count())->toBe(0)->and(PartyEntry::count())->toBe(0);
});

it('ciorna și petrecerea dezactivată nu primesc înregistrări nici cu sesiune deschisă', function () {
    $admin = etAdmin();
    $party = etParty();
    etEnter($party, $admin);

    $party->update(['is_active' => false]);
    expect(fn () => etEnter($party->fresh(), $admin))->toThrow(DomainException::class, 'nu primește intrări');

    $party->update(['is_active' => true, 'status' => 'draft']);
    expect(fn () => etEnter($party->fresh(), $admin))->toThrow(DomainException::class, 'nu primește intrări');

    expect(PartyEntry::count())->toBe(1);
});

it('openFor cu onlyExisting refuză când casa s-a închis între verificare și înregistrare', function () {
    $party = etParty();

    expect(fn () => ReceptionSession::openFor($party->id, null, onlyExisting: true))
        ->toThrow(DomainException::class, 'casa e închisă');
    expect(ReceptionSession::count())->toBe(0);

    $open = ReceptionSession::openFor($party->id);
    expect(ReceptionSession::openFor($party->id, null, onlyExisting: true)->id)->toBe($open->id);
});

it('petrecerea viitoare sau în desfășurare deschide sesiune la prima înregistrare, ca înainte', function () {
    $admin = etAdmin();
    $party = etParty();

    etEnter($party, $admin);
    expect(ReceptionSession::count())->toBe(1);

    // Petrecere viitoare (mâine): tot deschide sesiune.
    $next = etParty(['name' => 'Mâine', 'start_date' => '2026-10-10']);
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    etEnter($next, $admin);
    expect(ReceptionSession::where('party_id', $next->id)->count())->toBe(1);
});
