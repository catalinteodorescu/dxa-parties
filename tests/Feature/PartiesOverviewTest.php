<?php

use App\Livewire\Admin\Parties\Overview as PartiesOverviewPage;
use App\Services\PartiesOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * DXA: adaugat (Petreceri - statistici agregate). Refoloseste helperele din PartyStatsTest
 * (statsAdmin/statsParty/statsReport/statsSale) si din ReceptionReportsTest (rcpAdmin/rcpParty/rcpEntry) —
 * functii globale, incarcate de Pest din celelalte fisiere.
 */
it('PartiesOverview::totals numara petrecerile pe tip, indiferent de raportari', function () {
    statsParty('Simplă 1', '2026-01-05', 'basic');
    statsParty('Simplă 2', '2026-02-05', 'basic');
    statsParty('Festival 1', '2026-03-05', 'festival');

    $t = PartiesOverview::totals();

    expect($t->count)->toBe(3)
        ->and($t->basic)->toBe(2)
        ->and($t->festival)->toBe(1);
});

it('PartiesOverview::frequency grupeaza petrecerile pe luna calendaristica, cronologic', function () {
    statsParty('A', '2026-01-05');
    statsParty('B', '2026-01-20');
    statsParty('C', '2026-03-01');

    $f = PartiesOverview::frequency();

    expect($f)->toHaveCount(2)
        ->and($f->first()->label)->toBe('01.2026')
        ->and($f->first()->count)->toBe(2)
        ->and($f->last()->label)->toBe('03.2026')
        ->and($f->last()->count)->toBe(1);
});

it('PartiesOverview::summary aduna venitul/profitul (din BarStats) si intrarile (din ReceptionStats)', function () {
    $party = statsParty('Salsa Night', '2026-09-05');
    $ana = statsAdmin('Ana');
    $r = statsReport($party, ['Cocktail' => [10, 300, 100]]);
    statsSale($r, '2026-09-05 23:00:00', 300, $ana);

    $admin = rcpAdmin();
    $rcp = rcpParty(['name' => 'Petrecere recepție', 'start_date' => '2026-09-06']);
    rcpEntry($rcp, $admin, 4);

    $s = PartiesOverview::summary();

    expect($s->revenue)->toBe(300.0)
        ->and($s->profit)->toBe(200.0)
        ->and($s->entries_total)->toBe(4)
        ->and($s->entries_avg)->toBe(4.0)
        ->and($s->entries_revenue)->toBe(120.0);
});

it('PartiesOverview::topByAttendance sorteaza petrecerile dupa numarul de intrari', function () {
    $admin = rcpAdmin();
    $small = rcpParty(['name' => 'Mică', 'start_date' => '2026-01-10']);
    rcpEntry($small, $admin, 2);
    $big = rcpParty(['name' => 'Mare', 'start_date' => '2026-02-10']);
    rcpEntry($big, $admin, 5);

    $top = PartiesOverview::topByAttendance();

    expect($top->first()->party->name)->toBe('Mare')
        ->and($top->first()->attendance->count)->toBe(5);
});

it('afiseaza pagina Petreceri > Statistici cu totalurile si topurile', function () {
    $party = statsParty('Salsa Night', '2026-09-05', 'festival');
    $ana = statsAdmin('Ana');
    $r = statsReport($party, ['Cocktail' => [10, 300, 100]]);
    statsSale($r, '2026-09-05 23:00:00', 300, $ana);

    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(PartiesOverviewPage::class)
        ->assertSee('Petreceri · Statistici')
        ->assertSee('Salsa Night')
        ->assertSee('Frecvență');
});

it('arata starea goala cand nu exista nicio petrecere', function () {
    $this->actingAs(statsAdmin('Superadmin'), 'admin');

    Livewire::test(PartiesOverviewPage::class)->assertSee('Nicio petrecere creată încă.');
});
