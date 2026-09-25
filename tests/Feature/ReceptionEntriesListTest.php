<?php

use App\Livewire\Admin\Reception\Index as ReceptionEntriesIndex;
use App\Models\Admin;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Services\EntryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

it('listează grupurile de intrări (nu persoanele) cu rezumat, filtre pe petrecere/metodă/stare/dată', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');

    $party1 = rcpParty(['name' => 'Petrecere A']);
    rcpEntry($party1, $admin, 2, 'cash');                                  // grup 1: 2 persoane, cash, 60 lei
    $card = rcpEntry($party1, $admin, 1, 'card');                          // grup 2: 1 persoana, card, 30 lei
    EntryRecorder::cancelBatch($card->first()->batch, 'Greșeală', $admin->id);

    $party2 = rcpParty(['name' => 'Petrecere B', 'start_date' => '2026-10-10']);
    Carbon::setTestNow('2026-10-10 21:00:00'); // rcpParty() a resetat "now" la 03.10 — o avansăm ca intrarea să cadă pe 10.10
    rcpEntry($party2, $admin, 3, 'cash');                                  // grup 3: 3 persoane, cash, 90 lei

    $c = Livewire::test(ReceptionEntriesIndex::class);
    $c->assertSee('Petrecere A')->assertSee('Petrecere B')
        ->assertSee('2 persoane')->assertSee('3 persoane')
        ->assertSee('Anulată');

    // Rezumatul numara doar ACTIVE: 2 (petr. A) + 3 (petr. B) = 5 persoane, 150 lei; grupul anulat nu intra.
    expect($c->viewData('summary'))->toEqual(['entries' => 5, 'batches' => 2, 'revenue' => 150.0]);

    // Filtru pe petrecere: in randurile listei (numele apare oricum in dropdown-ul de filtru, indiferent de filtru).
    $c->set('party', (string) $party2->id);
    expect($c->viewData('batches')->pluck('party.name')->unique()->all())->toBe(['Petrecere B']);
    expect($c->viewData('summary')['entries'])->toBe(3);
    $c->set('party', '');

    // Filtru pe stare: doar grupul anulat (al petrecerii A).
    $c->set('state', 'cancelled');
    expect($c->viewData('batches')->pluck('party.name')->unique()->all())->toBe(['Petrecere A']);
    $c->set('state', 'all');

    // Filtru pe metodă (doar grupul cu plată card, chiar dacă e anulat — filtrul de metodă nu exclude anulatele).
    $c->set('method', 'card');
    expect($c->viewData('batches'))->toHaveCount(1);
    expect($c->viewData('batches')->first()->cancelled)->toBeTrue();
    $c->set('method', 'all');

    // Filtru pe interval de dată: doar ziua petrecerii B.
    $c->set('dateFrom', '2026-10-10')->set('dateTo', '2026-10-10');
    expect($c->viewData('batches')->pluck('party.name')->unique()->all())->toBe(['Petrecere B']);
});

it('anulează un grup din lista de intrări, cu motiv obligatoriu', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();
    $entries = rcpEntry($party, $admin, 2);
    $batch = $entries->first()->batch;

    $c = Livewire::test(ReceptionEntriesIndex::class);

    $c->call('cancel', $batch, '  ')->assertSet('cancelError', fn ($e) => str_contains($e, 'motiv obligatoriu'));
    expect(PartyEntry::where('batch', $batch)->whereNull('cancelled_at')->count())->toBe(2);

    $c->call('cancel', $batch, 'Greșeală la înregistrare')
        ->assertSet('cancelMessage', fn ($m) => str_contains($m, 'anulate'));
    expect(PartyEntry::where('batch', $batch)->whereNotNull('cancelled_at')->count())->toBe(2);
});

it('paginează pe grupuri (batch-uri), nu pe persoane', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');
    $party = rcpParty();

    foreach (range(1, 16) as $i) {
        rcpEntry($party, $admin, 1);
    }

    $c = Livewire::test(ReceptionEntriesIndex::class);
    expect($c->viewData('paginator')->total())->toBe(16);
    expect($c->viewData('batches'))->toHaveCount(15); // prima pagina
});

it('lista are butonul „Intrare nouă” către pagina de adăugare, iar meniul rămâne pe Intrări', function () {
    $admin = rcpAdmin();
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.reception.index'))
        ->assertOk()
        ->assertSee(route('admin.reception.create'), false)
        ->assertSee('Intrare nouă');
});
