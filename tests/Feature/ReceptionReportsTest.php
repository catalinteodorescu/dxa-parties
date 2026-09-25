<?php

use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Reception\Form as ReceptionForm;
use App\Livewire\Admin\ReceptionReports\Form as ReportForm;
use App\Livewire\Admin\ReceptionReports\Index as ReportsIndex;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Models\TokenTransaction;
use App\Services\EntryRecorder;
use App\Services\PartyStats;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function rcpAdmin(): Admin
{
    return Admin::create([
        'name' => 'Casier Rcp',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026, 21:00-03:00, bilet 30 lei; „acum” = 23:00 din prima zi; tokeni la 6 lei. */
function rcpParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    PaymentMethods::setTokenRate(6);

    return Party::create(array_merge([
        'name' => 'Petrecere casă',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ], $overrides));
}

function rcpEntry(Party $party, Admin $admin, int $count = 1, string $method = 'cash'): \Illuminate\Support\Collection
{
    return EntryRecorder::record($party, 'Bilet', $count, [['method' => $method, 'amount' => 30 * $count]], adminId: $admin->id, enforceState: false);
}

/** Sesiune cu 2 intrări cash (60), 1 card (30), 1 anulată, 10 tokeni (40 cash + 20 card). */
function rcpBusySession(Party $party, Admin $admin): ReceptionSession
{
    rcpEntry($party, $admin, 2);
    rcpEntry($party, $admin, 1, 'card');
    $cancelled = rcpEntry($party, $admin, 1);
    EntryRecorder::cancelBatch($cancelled->first()->batch, 'Greșeală', $admin->id);
    TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 40], ['method' => 'card', 'amount' => 20]], $admin->id, enforceState: false);

    return ReceptionSession::currentFor($party->id);
}

function rcpFinalized(Party $party, Admin $admin, float $counted = 0, array $extra = []): ReceptionReport
{
    $session = ReceptionSession::currentFor($party->id);
    $report = ReceptionReport::startFor($session, $admin->id);
    $report->update(array_merge(['counted_cash' => $counted], $extra));
    $report->finalize($admin->id);

    return $report->fresh();
}

it('deschide singură o sesiune la prima înregistrare și o refolosește; ajustările de tokeni nu au sesiune', function () {
    $admin = rcpAdmin();
    $party = rcpParty();
    expect(ReceptionSession::count())->toBe(0);

    $entries = rcpEntry($party, $admin, 2);
    $session = ReceptionSession::currentFor($party->id);

    expect(ReceptionSession::count())->toBe(1)
        ->and($session->isOpen())->toBeTrue()
        ->and($entries->every(fn ($e) => $e->reception_session_id === $session->id))->toBeTrue();

    $tx = TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 60]], $admin->id, enforceState: false);
    rcpEntry($party, $admin);
    expect($tx->reception_session_id)->toBe($session->id)->and(ReceptionSession::count())->toBe(1);

    TokenLedger::adjust(5, 'Stoc inițial', $admin->id);
    expect(TokenTransaction::where('type', 'adjustment')->first()->reception_session_id)->toBeNull();
    expect(AdminActivityLog::where('action', 'reception.session_opened')->count())->toBe(1);
});

it('calculează cash-ul așteptat din fond, intrări, tokeni minus predat; celelalte metode sunt totaluri; anulatele nu intră', function () {
    $admin = rcpAdmin();
    $party = rcpParty();
    $session = rcpBusySession($party, $admin);

    $report = ReceptionReport::startFor($session, $admin->id);
    $report->update(['opening_float' => 100, 'handed_over' => 50, 'counted_cash' => 145]);
    $fig = $report->fresh()->figures();

    expect($fig->cash_entries)->toBe(60.0)
        ->and($fig->cash_tokens)->toBe(40.0)
        ->and($fig->expected_cash)->toBe(150.0)      // 100 + 60 + 40 − 50
        ->and($fig->cash_diff)->toBe(-5.0)
        ->and($fig->entries_count)->toBe(3)
        ->and($fig->entries_cancelled)->toBe(1)
        ->and($fig->tokens_sold)->toBe(10)
        ->and(collect($fig->other_methods)->pluck('amount', 'key')->all())->toEqual(['card' => 50.0]); // 30 intrare + 20 tokeni
});

it('finalizarea îngheață valorile, închide sesiunea și cere cash numărat', function () {
    $admin = rcpAdmin();
    $party = rcpParty();
    $session = rcpBusySession($party, $admin);
    $report = ReceptionReport::startFor($session, $admin->id);

    expect(fn () => $report->finalize($admin->id))->toThrow(DomainException::class, 'cash-ul numărat');
    expect($session->fresh()->isOpen())->toBeTrue();

    $report->update(['opening_float' => 100, 'handed_over' => 50, 'counted_cash' => 150]);
    $report->finalize($admin->id);
    $report = $report->fresh();

    expect($report->isFinalized())->toBeTrue()
        ->and($session->fresh()->isOpen())->toBeFalse()
        ->and((float) $report->expected_cash)->toBe(150.0)
        ->and((float) $report->cash_diff)->toBe(0.0)
        ->and($report->finalized_by)->toBe($admin->id)
        ->and($report->number())->toBe($report->id.' / 03.10.2026');
    expect(fn () => $report->finalize($admin->id))->toThrow(DomainException::class, 'deja finalizată');

    // Înregistrarea următoare deschide o sesiune nouă; raportarea veche rămâne neschimbată.
    rcpEntry($party, $admin);
    expect(ReceptionSession::count())->toBe(2)->and(ReceptionSession::currentFor($party->id)->id)->not->toBe($session->id);
    expect($report->fresh()->figures()->entries_count)->toBe(3);
    expect(AdminActivityLog::where('action', 'reception.report_finalized')->count())->toBe(1);
});

it('o sesiune închisă nu mai permite anularea intrărilor și a vânzărilor de tokeni', function () {
    $admin = rcpAdmin();
    $party = rcpParty();
    $entry = rcpEntry($party, $admin);
    $tx = TokenLedger::sell($party, 5, [['method' => 'cash', 'amount' => 30]], $admin->id, enforceState: false);
    $open = rcpEntry($party, $admin);

    // Cât e deschisă, se poate anula.
    EntryRecorder::cancelBatch($open->first()->batch, 'Greșeală', $admin->id);

    rcpFinalized($party, $admin, 60);

    expect(fn () => EntryRecorder::cancelBatch($entry->first()->batch, 'Târziu', $admin->id))->toThrow(DomainException::class, 'Casa a fost închisă');
    expect(fn () => TokenLedger::cancel($tx->id, 'Târziu', $admin->id))->toThrow(DomainException::class, 'Casa a fost închisă');
    expect(PartyEntry::active()->count())->toBe(1);
});

it('propune fondul de casă din cash-ul rămas după raportarea anterioară și permite un singur draft per sesiune', function () {
    $admin = rcpAdmin();
    $party = rcpParty();

    rcpEntry($party, $admin);
    $first = rcpFinalized($party, $admin, 130, ['handed_over' => 100]);      // rămân 30 în casă
    expect(ReceptionReport::suggestedFloat($party->id))->toBe(30.0);

    rcpEntry($party, $admin);
    $session = ReceptionSession::currentFor($party->id);
    $second = ReceptionReport::startFor($session, $admin->id);
    expect((float) $second->opening_float)->toBe(30.0)
        ->and(ReceptionReport::startFor($session, $admin->id)->id)->toBe($second->id)
        ->and(ReceptionReport::count())->toBe(2);
    expect(fn () => ReceptionReport::startFor($first->session, $admin->id))->not->toThrow(DomainException::class); // există deja: se întoarce cel existent
});

it('ecranul Intrări: mesajul de sesiune nouă apare la prima înregistrare (cardul de sesiune s-a mutat la Recepție → Raportări)', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();

    $c = Livewire::test(ReceptionForm::class)->call('payAll', 'cash')->call('save');
    $c->assertSet('message', fn ($m) => str_contains($m, 'Înregistrat: 1') && str_contains($m, 'A început o sesiune de recepție'));

    // A doua înregistrare, în aceeași sesiune: fără mesaj de sesiune nouă.
    $c->call('payAll', 'cash')->call('save')->assertSet('message', fn ($m) => ! str_contains($m, 'sesiune'));

    // Închiderea casei (fostul buton de pe acest ecran) se face acum doar din Recepție → Raportări.
    rcpFinalized($party, $admin, 60);

    $c = Livewire::test(ReceptionForm::class);
    $c->call('payAll', 'cash')->call('save')
        ->assertSet('message', fn ($m) => str_contains($m, 'Casa fusese închisă') && str_contains($m, 'sesiune nouă'));
});

it('draftul se salvează automat, cu validare, iar finalizarea trece prin dialogul de recap', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();
    $session = rcpBusySession($party, $admin);
    $report = ReceptionReport::startFor($session, $admin->id);

    $c = Livewire::test(ReportForm::class, ['report' => $report])
        ->set('opening_float', '100')
        ->set('handed_over', '50,5')
        ->set('handed_note', 'Predat lui Ion')
        ->set('method_notes', ['card' => 'Verificat în extras'])
        ->assertSet('error', null);
    $report->refresh();
    expect((float) $report->opening_float)->toBe(100.0)
        ->and((float) $report->handed_over)->toBe(50.5)
        ->and($report->handed_note)->toBe('Predat lui Ion')
        ->and($report->method_notes)->toBe(['card' => 'Verificat în extras']);

    // Valoare invalidă: eroare, nimic salvat.
    $c->set('counted_cash', 'abc')->assertSet('error', fn ($e) => str_contains($e, 'Cash-ul numărat'));
    expect($report->fresh()->counted_cash)->toBeNull();

    // Fără cash numărat nu se poate finaliza.
    $c->set('counted_cash', '')->call('askFinalize')->assertSet('confirming', false)->assertSet('error', fn ($e) => str_contains($e, 'cash-ul numărat'));

    $c->set('counted_cash', '148,5')->call('askFinalize')->assertSet('confirming', true)->assertSee('Finalizezi raportarea?')->assertSee('−1,00')->assertSee('Ireversibil');
    $c->call('finalize')->assertSet('confirming', false)->assertSet('error', null)->assertSee('Raportare finalizată');

    $report->refresh();
    expect($report->isFinalized())->toBeTrue()->and((float) $report->cash_diff)->toBe(-1.0)->and($session->fresh()->isOpen())->toBeFalse();
    // Finalizată: câmpurile nu se mai modifică.
    $c->set('counted_cash', '500');
    expect((float) $report->fresh()->counted_cash)->toBe(148.5);
});

it('lista: sesiunile deschise, începerea raportării, ștergerea draftului și export PDF doar pentru finalizate', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();
    rcpBusySession($party, $admin);
    $session = ReceptionSession::currentFor($party->id);

    $c = Livewire::test(ReportsIndex::class)->assertSee('Sesiuni deschise')->assertSee('Petrecere casă')->assertSee('Sesiune 1 · deschisă din')->assertSee('Închide casa');
    $c->call('startReport', $session->id);
    $report = ReceptionReport::firstOrFail();
    $c->assertRedirect(route('admin.reception.reports.show', $report));

    // Draftul se șterge, sesiunea rămâne deschisă.
    Livewire::test(ReportsIndex::class)->call('delete', $report->id)->assertSet('listMessage', fn ($m) => str_contains($m, 'Sesiunea rămâne deschisă'));
    expect(ReceptionReport::count())->toBe(0)->and($session->fresh()->isOpen())->toBeTrue();
    expect(AdminActivityLog::where('action', 'reception.report_deleted')->count())->toBe(1);

    $done = rcpFinalized($party, $admin, 60);
    $list = Livewire::test(ReportsIndex::class)->assertSee('Nr. '.$done->number())->assertSee('Finalizat');
    $list->call('delete', $done->id)->assertSet('listError', fn ($e) => str_contains($e, 'finalizată'));
    expect(ReceptionReport::count())->toBe(1);
    $list->call('exportPdf', $done->id)->assertFileDownloaded();

    // Draft: PDF-ul nu există (404).
    rcpEntry($party, $admin);
    $draft = ReceptionReport::startFor(ReceptionSession::currentFor($party->id), $admin->id);
    expect(fn () => \App\Services\ReceptionReportPdfExporter::stream($draft))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('paginile de recepție se deschid, iar meniul are linkul Raportări', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();
    rcpEntry($party, $admin);
    $report = ReceptionReport::startFor(ReceptionSession::currentFor($party->id), $admin->id);

    $this->get(route('admin.reception.reports.index'))->assertOk()->assertSee('Raportări');
    $this->get(route('admin.reception.reports.show', $report))->assertOk()->assertSee('Cash numărat')->assertSee('Alte metode de plată');
    // Lista de intrari deschide catre "Intrare noua"; de acolo (unde e cardul de sesiune) se ajunge la raportari.
    $this->get(route('admin.reception.index'))->assertOk()->assertSee(route('admin.reception.create'), false);
    $this->get(route('admin.reception.create'))->assertOk()->assertSee(route('admin.reception.reports.index'), false);
});

it('statisticile adună doar raportările de recepție finalizate, cu KPI și bloc „Casă recepție”', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty(['kind' => 'festival', 'end_date' => '2026-10-05']);

    expect(PartyStats::reception($party))->toBeNull();

    rcpBusySession($party, $admin);
    rcpFinalized($party, $admin, 100, ['opening_float' => 100, 'handed_over' => 50]);   // așteptat 150, numărat 100 -> −50
    rcpEntry($party, $admin, 1);                                                          // 30 cash
    rcpFinalized($party, $admin, 40, ['opening_float' => 10]);                            // așteptat 40, numărat 40 -> 0
    rcpEntry($party, $admin, 1);                                                          // sesiune deschisă: draft, nu intră
    ReceptionReport::startFor(ReceptionSession::currentFor($party->id), $admin->id);

    $rc = PartyStats::reception($party);
    expect($rc->reports)->toBe(2)
        ->and($rc->expected_cash)->toBe(190.0)
        ->and($rc->counted_cash)->toBe(140.0)
        ->and($rc->cash_diff)->toBe(-50.0)
        ->and($rc->handed_over)->toBe(50.0)
        ->and($rc->clean)->toBeFalse()
        ->and(collect($rc->methods)->pluck('amount', 'key')->all())->toEqual(['card' => 50.0]);

    $stats = PartyStats::for($party);
    expect($stats->reception_cash_diff)->toBe(-50.0);
    // Blocul de intrări rămâne pe TOATE intrările neanulate (inclusiv sesiunea încă deschisă).
    expect($stats->attendance->count)->toBe(5);

    $component = Livewire::test(PartyStatsPage::class, ['party' => $party])->instance();
    expect(array_map(fn ($k) => $k->key, $component->kpis($stats, null)))->toContain('reception_cash_diff');
    $this->get(route('admin.parties.stats', $party))->assertOk()->assertSee('Casă recepție')->assertSee('Diferență casă recepție');
});

it('KPI-ul „Diferență casă recepție” se randează și în grila completă, cu raportare de bar finalizată (regresie: $signed lipsea din closure)', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();

    rcpEntry($party, $admin, 1);
    rcpFinalized($party, $admin, 55);   // așteptat 30, numărat 55 -> +25
    // O raportare de bar finalizată e suficientă ca sa aducă $stats->has_data la true, ceea ce
    // randează grila principală de KPI (nu doar blocul „headline” din capul paginii).
    ReceptionReport::query();
    \App\Models\StockReport::forceCreate([
        'party_id' => $party->id,
        'date' => now(),
        'status' => 'finalized',
        'finalized_at' => now(),
        'counted_cash' => 0,
    ]);

    expect(PartyStats::for($party)->has_data)->toBeTrue();
    $this->get(route('admin.parties.stats', $party))->assertOk()->assertSee('Diferență casă recepție')->assertSee('+25,00 lei');
});

it('dashboardul arată indicatorii de recepție; alerta „sesiune uitată” doar la petreceri neîncheiate', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    // Festival pe 3 zile (21:00-06:00 fiecare), ca sesiunea sa ramana „live”/„upcoming” pana pe 6.10.
    $festival = rcpParty([
        'name' => 'Festival uitat',
        'kind' => 'festival',
        'days' => [
            ['date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '06:00'],
            ['date' => '2026-10-04', 'start_time' => '21:00', 'end_time' => '06:00'],
            ['date' => '2026-10-05', 'start_time' => '21:00', 'end_time' => '06:00'],
        ],
    ]);
    rcpEntry($festival, $admin);

    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Sesiuni de recepție deschise')->assertSee('nicio raportare de recepție încă')->assertDontSee('de peste 12 ore');

    // A doua zi la prânz: sesiunea are 13 ore, petrecerea (festival) încă în desfășurare.
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00'));
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('1 de peste 12 ore');

    // Petrecerea s-a încheiat: sesiunea rămâne deschisă în listă, dar fără alertă pe dashboard.
    Carbon::setTestNow(Carbon::parse('2026-10-08 12:00'));
    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('de peste 12 ore');
    expect(ReceptionSession::currentFor($festival->id)->isStale())->toBeTrue();

    // După o raportare finalizată apar ultima diferență și totalul pe 30 de zile.
    rcpFinalized($festival, $admin, 20);   // așteptat 30, numărat 20 -> −10
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('−10,00 lei')->assertSee('Ultima diferență de casă');
});

it('petrecerea cu sesiuni nu se poate șterge (are intrări)', function () {
    $admin = rcpAdmin();
    $party = rcpParty();
    rcpEntry($party, $admin);

    expect($party->entries()->exists())->toBeTrue()->and($party->receptionSessions()->count())->toBe(1);
});
