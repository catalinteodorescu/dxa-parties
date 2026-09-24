<?php

use App\Livewire\Admin\Parties\Stats;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockReportCount;
use App\Services\PartyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function statsAdmin(string $name = 'Ana'): Admin
{
    return Admin::create([
        'name' => $name,
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function statsParty(string $name, string $date, string $kind = 'basic'): Party
{
    return Party::create(['name' => $name, 'kind' => $kind, 'start_date' => $date]);
}

/** Produs de meniu (cate unul per nume). */
function statsMenuItem(string $name): MenuItem
{
    $category = MenuCategory::firstOrCreate(['name' => 'Bar'], ['is_active' => true]);

    return MenuItem::firstOrCreate(['name' => $name], ['menu_category_id' => $category->id, 'price' => 10, 'is_active' => true]);
}

/** Raportare (implicit finalizata) a unei petreceri, cu randuri de vanzare [produs => [qty, venit, cost]]. */
function statsReport(Party $party, array $lines, string $status = 'finalized', string $date = '2026-09-06'): StockReport
{
    $admin = statsAdmin('Raportor'.random_int(1, 99999));
    $report = StockReport::create([
        'date' => $date,
        'status' => $status,
        'finalized_at' => $status === 'finalized' ? now() : null,
        'finalized_by' => $status === 'finalized' ? $admin->id : null,
        'party_id' => $party->id,
        'created_by' => $admin->id,
    ]);

    foreach ($lines as $name => [$qty, $revenue, $cost]) {
        $report->sales()->create([
            'menu_item_id' => statsMenuItem($name)->id,
            'qty' => $qty,
            'unit_price' => $revenue / $qty,
            'total_price' => $revenue,
            'unit_cost' => $cost === null ? null : $cost / $qty,
            'total_cost' => $cost,
        ]);
    }

    return $report;
}

/** Bon din aplicatie postat de raportare, cu o plata. */
function statsSale(StockReport $report, string $soldAt, float $total, Admin $by, string $method = 'cash', ?int $tokens = null, string $status = 'completed'): Sale
{
    $sale = Sale::create([
        'report_id' => $report->id,
        'status' => $status,
        'total' => $total,
        'sold_at' => $soldAt,
        'created_by' => $by->id,
        'source' => 'manual',
    ]);
    SalePayment::create(['sale_id' => $sale->id, 'method' => $method, 'amount' => $total, 'tokens' => $tokens]);

    return $sale;
}

function statsLoss(StockReport $report, float $cost, string $note): void
{
    $item = StockItem::firstOrCreate(['name' => 'Vodcă'], ['unit' => 'ml', 'is_active' => true]);
    StockMovement::create([
        'stock_item_id' => $item->id,
        'report_id' => $report->id,
        'type' => 'out',
        'qty' => 1,
        'unit_cost' => $cost,
        'total_cost' => $cost,
        'note' => $note,
    ]);
}

/** Petrecerea de test cu datele complet cunoscute (vezi asteptarile din teste). */
function statsFullParty(): array
{
    $party = statsParty('Salsa Night', '2026-09-05');
    $ana = statsAdmin('Ana');
    $dan = statsAdmin('Dan');

    $r = statsReport($party, ['Cocktail' => [10, 300, 100], 'Bere' => [20, 100, 40]]);
    statsSale($r, '2026-09-05 23:10:00', 30, $ana);
    statsSale($r, '2026-09-06 00:30:00', 70, $dan, 'token', 14);
    statsSale($r, '2026-09-06 01:15:00', 30, $ana);
    statsSale($r, '2026-09-05 23:40:00', 999, $ana, 'cash', null, 'cancelled'); // anulata: ignorata
    statsLoss($r, 5, 'spart');
    statsLoss($r, 7, StockReport::INVENTORY_DIFF_NOTE);

    return [$party, $r, $ana, $dan];
}

it('calculeaza venit, cost, profit si marja doar din raportarile finalizate', function () {
    [$party, $r] = statsFullParty();
    statsReport($party, ['Cocktail' => [50, 5000, 100]], 'draft'); // draft: ignorat

    $s = PartyStats::for($party);

    expect($s->has_data)->toBeTrue()
        ->and($s->reports)->toHaveCount(1)
        ->and($s->revenue)->toBe(400.0)
        ->and($s->cost)->toBe(152.0)      // 140 cost vanzari + 5 pierdere + 7 lipsa inventar
        ->and($s->profit)->toBe(248.0)
        ->and($s->margin)->toBe(62.0)
        ->and($s->losses_cost)->toBe(5.0)
        ->and($s->inventory_cost)->toBe(7.0)
        ->and($s->cost_unknown)->toBeFalse();
});

it('aduna cifrele mai multor raportari ale aceleiasi petreceri si semnaleaza costul necunoscut', function () {
    [$party] = statsFullParty();
    statsReport($party, ['Cocktail' => [5, 150, null]], 'finalized', '2026-09-07');

    $s = PartyStats::for($party);

    expect($s->reports)->toHaveCount(2)
        ->and($s->revenue)->toBe(550.0)
        ->and($s->cost_unknown)->toBeTrue();
});

it('calculeaza bonurile, plata, "inregistrat de" si ignora vanzarile anulate', function () {
    [$party] = statsFullParty();
    $s = PartyStats::for($party);

    expect($s->tx_count)->toBe(3)
        ->and($s->tx_total)->toBe(130.0)
        ->and($s->avg_ticket)->toBe(43.33)
        ->and($s->payments['cash']['amount'])->toBe(60.0)
        ->and($s->payments['token']['amount'])->toBe(70.0)
        ->and($s->payments['token']['tokens'])->toBe(14)
        ->and($s->payments_total)->toBe(130.0);

    $byName = $s->staff->keyBy('name');
    expect($s->staff->first()->name)->toBe('Dan')
        ->and($byName['Ana']->count)->toBe(2)
        ->and($byName['Ana']->total)->toBe(60.0)
        ->and($byName['Dan']->total)->toBe(70.0);
});

it('grupeaza vanzarile pe ore de ceas, iar axa trece corect peste miezul noptii', function () {
    [$party] = statsFullParty();
    $s = PartyStats::for($party);

    expect(array_keys($s->hours))->toEqualCanonicalizing([23, 0, 1])
        ->and($s->hours[23])->toBe(['count' => 1, 'total' => 30.0])
        ->and($s->hours[0])->toBe(['count' => 1, 'total' => 70.0])
        ->and(PartyStats::hourAxis($s->hours))->toBe([23, 0, 1])
        ->and(PartyStats::hourAxis([21 => [], 22 => []], [22 => [], 23 => [], 0 => []]))->toBe([21, 22, 23, 0])
        ->and(PartyStats::hourAxis([], []))->toBe([]);
});

it('agrega produsele pe raportari, cu profit null cand costul e necunoscut', function () {
    [$party] = statsFullParty();
    statsReport($party, ['Cocktail' => [5, 150, 50], 'Shot' => [4, 40, null]], 'finalized', '2026-09-07');

    $products = PartyStats::for($party)->products->keyBy('name');

    expect($products['Cocktail']->qty)->toBe(15.0)
        ->and($products['Cocktail']->revenue)->toBe(450.0)
        ->and($products['Cocktail']->profit)->toBe(300.0)
        ->and($products['Bere']->profit)->toBe(60.0)
        ->and($products['Shot']->profit)->toBeNull();
});

it('rezuma inventarul de final: cash, produse cu diferente, valoare', function () {
    [$party, $r] = statsFullParty();
    $item = StockItem::first();
    $r->update(['counted_cash' => 100, 'expected_cash' => 110]);
    StockReportCount::create(['report_id' => $r->id, 'stock_item_id' => $item->id, 'counted_qty' => 8, 'expected_qty' => 10, 'diff_qty' => -2, 'unit_cost' => 1.5]);

    $c = PartyStats::for($party)->closing;

    expect($c)->not->toBeNull()
        ->and($c->cash_diff)->toBe(-10.0)
        ->and($c->diff_items)->toBe(1)
        ->and($c->inventory_value)->toBe(-3.0)
        ->and($c->clean)->toBeFalse();

    expect(PartyStats::for(statsParty('Fara inventar', '2026-01-01'))->closing)->toBeNull();
});

it('numara vanzarile neraportate ale sesiunilor petrecerii', function () {
    [$party] = statsFullParty();
    $ana = statsAdmin('Alt cont');
    $group = SalesGroup::create(['party_id' => $party->id, 'status' => 'open']);
    Sale::create(['sales_group_id' => $group->id, 'status' => 'completed', 'total' => 25, 'sold_at' => '2026-09-05 22:00:00', 'created_by' => $ana->id, 'source' => 'manual']);
    Sale::create(['sales_group_id' => $group->id, 'status' => 'cancelled', 'total' => 99, 'sold_at' => '2026-09-05 22:10:00', 'created_by' => $ana->id, 'source' => 'manual']);

    expect(PartyStats::unreported($party))->toBe(['count' => 1, 'total' => 25.0]);
});

it('alege implicit petrecerea precedenta de acelasi tip, cu raportari finalizate', function () {
    $older = statsParty('Veche', '2026-08-01');
    $festival = statsParty('Festival', '2026-08-15', 'festival');
    $noReport = statsParty('Fara raportare', '2026-08-20');
    $draftOnly = statsParty('Doar draft', '2026-08-25');
    $target = statsParty('Tinta', '2026-09-05');
    $later = statsParty('Ulterioara', '2026-10-01');

    foreach ([$older, $festival, $later] as $p) {
        statsReport($p, ['Cocktail' => [1, 10, 5]]);
    }
    statsReport($draftOnly, ['Cocktail' => [1, 10, 5]], 'draft');

    expect(PartyStats::defaultCompare($target)?->id)->toBe($older->id)
        ->and(PartyStats::defaultCompare($older))->toBeNull()
        ->and(PartyStats::comparableParties($target)->pluck('id')->all())->toEqualCanonicalizing([$older->id, $festival->id, $later->id]);

    $this->actingAs(statsAdmin(), 'admin');

    Livewire::test(Stats::class, ['party' => $target])->assertSet('compare', (string) $older->id);
    // fara nicio petrecere anterioara comparabila: fara comparatie
    Livewire::test(Stats::class, ['party' => $older])->assertSet('compare', 'none');
});

it('permite alegerea sau dezactivarea comparatiei si ignora o petrecere fara raportari', function () {
    $older = statsParty('Veche', '2026-08-01');
    $other = statsParty('Alta', '2026-08-10', 'festival');
    $empty = statsParty('Goala', '2026-08-11');
    $target = statsParty('Tinta', '2026-09-05');
    foreach ([$older, $other, $target] as $p) {
        statsReport($p, ['Cocktail' => [1, 10, 5]]);
    }
    $this->actingAs(statsAdmin(), 'admin');

    Livewire::test(Stats::class, ['party' => $target])
        ->set('compare', (string) $other->id)
        ->assertViewHas('compareParty', fn ($p) => $p?->id === $other->id)
        ->set('compare', 'none')
        ->assertViewHas('compareParty', fn ($p) => $p === null)
        ->set('compare', (string) $empty->id)
        ->assertViewHas('compareParty', fn ($p) => $p === null)
        ->set('compare', (string) $target->id)
        ->assertViewHas('compareParty', fn ($p) => $p === null);
});

it('randeaza pagina cu cifrele si comparatia', function () {
    [$party] = statsFullParty();
    $older = statsParty('Vineri veche', '2026-08-01');
    $r = statsReport($older, ['Cocktail' => [10, 200, 100]]);
    statsSale($r, '2026-08-01 22:20:00', 50, statsAdmin('Vechi'));
    $this->actingAs(statsAdmin(), 'admin');

    $this->get(route('admin.parties.stats', $party))
        ->assertOk()
        ->assertSee('Statistici · Salsa Night')
        ->assertSee('248,00 lei')
        ->assertSee('62,0%')
        ->assertSee('Vineri veche')     // comparatia implicita
        ->assertSee('Aleasă automat')
        ->assertSee('vs 200,00 lei')    // valoarea comparata, afisata intreaga
        ->assertSee('Vârf:')
        ->assertSee('00:00–01:00')
        ->assertSee('Înregistrat de')
        ->assertSee('Dan')
        ->assertSee('Cocktail');

    // comparatia aleasa explicit (in URL) nu mai e prezentata ca „automata”
    $this->get(route('admin.parties.stats', ['party' => $party, 'compara' => $older->id]))
        ->assertOk()
        ->assertSee('Vineri veche')
        ->assertDontSee('Aleasă automat');

    $this->get(route('admin.parties.stats', ['party' => $party, 'compara' => 'none']))
        ->assertOk()
        ->assertDontSee('Comparat cu');
});

it('arata starea goala cand petrecerea nu are raportari finalizate', function () {
    $party = statsParty('Fara date', '2026-09-05');
    statsReport($party, ['Cocktail' => [1, 10, 5]], 'draft');
    $this->actingAs(statsAdmin(), 'admin');

    $this->get(route('admin.parties.stats', $party))
        ->assertOk()
        ->assertSee('Nicio raportare finalizată pentru această petrecere');
});

it('are buton Statistici in lista de petreceri si in detaliile petrecerii', function () {
    $party = statsParty('Cu buton', '2026-09-05');
    $this->actingAs(statsAdmin(), 'admin');
    $url = route('admin.parties.stats', $party);

    $this->get(route('admin.parties.index'))->assertOk()->assertSee($url, false);
    $this->get(route('admin.parties.show', $party))->assertOk()->assertSee($url, false);
});


it('marcheaza comparatia ca automata doar pana o schimba utilizatorul', function () {
    $older = statsParty('Veche', '2026-08-01');
    $other = statsParty('Alta', '2026-08-10');
    $target = statsParty('Tinta', '2026-09-05');
    foreach ([$older, $other, $target] as $p) {
        statsReport($p, ['Cocktail' => [1, 10, 5]]);
    }
    $this->actingAs(statsAdmin(), 'admin');

    Livewire::test(Stats::class, ['party' => $target])
        ->assertSet('compareIsDefault', true)
        ->assertSee('Aleasă automat')
        ->set('compare', (string) $other->id)
        ->assertSet('compareIsDefault', false)
        ->assertDontSee('Aleasă automat');
});
