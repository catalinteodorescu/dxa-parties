<?php

use App\Livewire\Admin\Reception\Stats as ReceptionStatsPage;
use App\Services\EntryRecorder;
use App\Services\ReceptionStats;
use App\Services\TokenLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: adaugat (Receptie - statistici agregate). Refoloseste helperele din ReceptionReportsTest
 * (rcpAdmin/rcpParty/rcpEntry/rcpFinalized) si statsAdmin din PartyStatsTest — functii globale.
 *
 * Petrecere veche (10 ian 2026): 3 intrari cash (90 lei, nicio gratuita), 20 tokeni vanduti.
 * Petrecere recenta (5 sep 2026): 2 intrari cash + 1 gratuita (pret suprascris 0), 5 tokeni vanduti.
 */
function twoReceptionParties(): array
{
    $admin = rcpAdmin();

    $old = rcpParty(['name' => 'Petrecere veche', 'start_date' => '2026-01-10']);
    rcpEntry($old, $admin, 3);
    TokenLedger::sell($old, 20, [['method' => 'cash', 'amount' => 120]], $admin->id, enforceState: false);

    $recent = rcpParty(['name' => 'Petrecere recentă', 'start_date' => '2026-09-05']);
    rcpEntry($recent, $admin, 2);
    EntryRecorder::record($recent, 'Bilet', 1, [], overridePrice: 0, overrideReason: 'test gratuit', adminId: $admin->id, enforceState: false);
    TokenLedger::sell($recent, 5, [['method' => 'cash', 'amount' => 30]], $admin->id, enforceState: false);

    return [$old, $recent, $admin];
}

it('ReceptionStats::entriesTimeline arata intrarile si % gratuite, cronologic', function () {
    [$old, $recent] = twoReceptionParties();

    $timeline = ReceptionStats::entriesTimeline();

    expect($timeline)->toHaveCount(2)
        ->and($timeline->first()->party->id)->toBe($old->id) // cronologic: veche inainte de recenta
        ->and($timeline->first()->attendance->count)->toBe(3)
        ->and($timeline->first()->attendance->free_pct)->toBe(0.0)
        ->and($timeline->last()->attendance->count)->toBe(3)
        ->and($timeline->last()->attendance->free)->toBe(1);
});

it('ReceptionStats::tokensOverview aduna vanzarile/incasarile de tokeni, filtrat dupa data petrecerii', function () {
    twoReceptionParties();

    $all = ReceptionStats::tokensOverview();
    $recentOnly = ReceptionStats::tokensOverview(Carbon::parse('2026-06-01'));

    expect($all->sold)->toBe(25)
        ->and($recentOnly->sold)->toBe(5);
});

it('ReceptionStats::cashDiffTimeline arata diferenta de casa per petrecere, din raportari finalizate', function () {
    [$old, $recent, $admin] = twoReceptionParties();
    rcpFinalized($old, $admin, counted: 200); // asteptat 90 (intrari) + 120 (tokeni) = 210, numarat 200 => diff -10
    rcpFinalized($recent, $admin, counted: 90); // asteptat 60 (intrari) + 30 (tokeni) = 90 => diff 0

    $timeline = ReceptionStats::cashDiffTimeline();

    expect($timeline)->toHaveCount(2)
        ->and($timeline->first()->party->id)->toBe($old->id)
        ->and($timeline->first()->reception->cash_diff)->toBe(-10.0)
        ->and($timeline->last()->reception->cash_diff)->toBe(0.0);
});

it('afiseaza pagina Receptie > Statistici cu cifrele agregate si permite schimbarea perioadei', function () {
    [$old, $recent, $admin] = twoReceptionParties();
    rcpFinalized($old, $admin, counted: 200);
    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(ReceptionStatsPage::class)
        ->assertSee('Recepție · Statistici')
        ->assertSee('Petrecere veche')
        ->assertSee('Petrecere recentă')
        ->set('period', 'recent')
        ->assertSet('period', 'recent');
});

it('arata starea goala cand nu exista nicio intrare/tokeni/raportare', function () {
    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(ReceptionStatsPage::class)
        ->assertSee('Nicio intrare înregistrată încă.')
        ->assertSee('Nicio raportare de recepție finalizată încă.');
});
