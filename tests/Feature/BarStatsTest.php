<?php

use App\Livewire\Admin\Bar\Stats as BarStatsPage;
use App\Models\Party;
use App\Services\BarStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * DXA: adaugat (Bar - statistici agregate). Refoloseste helperele din PartyStatsTest
 * (statsAdmin/statsParty/statsReport/statsSale) — functii globale, incarcate de Pest din celalalt fisier.
 *
 * Petrecere veche (10 ian 2026): Cocktail 10buc/300 lei/cost 100 -> revenue 300, cost 100. O vanzare Ana 300 lei.
 * Petrecere recenta (5 sep 2026): Cocktail 4buc/120/40 + Bere 10buc/50/20 -> revenue 170, cost 60.
 *   Vanzari: Dan 100 lei (token), Ana 70 lei.
 */
function twoBarParties(): array
{
    $old = statsParty('Petrecere veche', '2026-01-10');
    $recent = statsParty('Petrecere recentă', '2026-09-05');
    $ana = statsAdmin('Ana');
    $dan = statsAdmin('Dan');

    $r1 = statsReport($old, ['Cocktail' => [10, 300, 100]]);
    statsSale($r1, '2026-01-10 22:00:00', 300, $ana);

    $r2 = statsReport($recent, ['Cocktail' => [4, 120, 40], 'Bere' => [10, 50, 20]], 'finalized', '2026-09-06');
    statsSale($r2, '2026-09-05 23:00:00', 100, $dan, 'token', 20);
    statsSale($r2, '2026-09-06 00:00:00', 70, $ana);

    return [$old, $recent, $r1, $r2, $ana, $dan];
}

it('BarStats::overview aduna cifrele mai multor petreceri, doar din raportari finalizate', function () {
    [$old, $recent] = twoBarParties();
    statsReport($recent, ['Cocktail' => [50, 5000, 100]], 'draft'); // draft: ignorat

    $o = BarStats::overview();

    expect($o->parties_count)->toBe(2)
        ->and($o->revenue)->toBe(470.0)
        ->and($o->cost)->toBe(160.0)
        ->and($o->profit)->toBe(310.0)
        ->and($o->tx_count)->toBe(3)
        ->and($o->payments['cash']['amount'])->toBe(370.0)
        ->and($o->payments['token']['amount'])->toBe(100.0);
});

it('BarStats::overview filtreaza dupa data petrecerii (start_date), nu dupa data raportarii', function () {
    twoBarParties();

    $all = BarStats::overview();
    $recentOnly = BarStats::overview(Carbon::parse('2026-06-01'));

    expect($all->parties_count)->toBe(2)
        ->and($recentOnly->parties_count)->toBe(1)
        ->and($recentOnly->revenue)->toBe(170.0);
});

it('BarStats::products aduna produsele peste toate petrecerile, all-time', function () {
    twoBarParties();

    $p = BarStats::products();
    $byName = $p->top_revenue->keyBy('name');

    expect($byName['Cocktail']->qty)->toBe(14.0)
        ->and($byName['Cocktail']->revenue)->toBe(420.0)
        ->and($p->top_qty->first()->name)->toBe('Cocktail');
});

it('BarStats::topParties sorteaza clasamentul dupa criteriul ales', function () {
    twoBarParties();

    $byRevenue = BarStats::topParties('revenue');

    expect($byRevenue)->toHaveCount(2)
        ->and($byRevenue->first()->party->name)->toBe('Petrecere veche'); // 300 lei venit > 170 lei
});

it('BarStats::staff aduna vanzarile fiecarui admin peste petreceri, cu filtru de perioada', function () {
    twoBarParties();

    $all = BarStats::staff()->keyBy('name');
    $recentOnly = BarStats::staff(Carbon::parse('2026-06-01'))->keyBy('name');

    expect($all['Ana']->count)->toBe(2)
        ->and($all['Ana']->total)->toBe(370.0)
        ->and($all['Dan']->total)->toBe(100.0)
        ->and($recentOnly['Ana']->total)->toBe(70.0)
        ->and($recentOnly['Dan']->total)->toBe(100.0);
});

it('afiseaza pagina Bar > Statistici cu cifrele agregate si permite schimbarea perioadei si a criteriului', function () {
    twoBarParties();
    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(BarStatsPage::class)
        ->assertSee('Bar · Statistici')
        ->assertSee('Petrecere veche')
        ->assertSee('Petrecere recentă')
        ->set('period', 'recent')
        ->assertSet('period', 'recent')
        ->set('rankBy', 'margin')
        ->assertSet('rankBy', 'margin');
});

it('arata starea goala cand nicio petrecere nu are raportare finalizata', function () {
    Party::create(['name' => 'Fara raportare', 'kind' => 'basic', 'start_date' => '2026-01-01']);
    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(BarStatsPage::class)->assertSee('Nicio petrecere cu raportare finalizată');
});
