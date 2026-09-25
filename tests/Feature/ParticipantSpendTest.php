<?php

use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Participants\Index as ParticipantsIndex;
use App\Livewire\Admin\Participants\Show as ParticipantsShow;
use App\Livewire\Admin\Reception\Form as ReceptionForm;
use App\Livewire\Admin\Reception\TokenSale;
use App\Livewire\Admin\Sales\Form as SaleForm;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\Party;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\StockReport;
use App\Models\TokenTransaction;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\ParticipantStats;
use App\Services\PartyStats;
use App\Services\SaleRecorder;
use App\Services\TokenLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function spendAdmin(): Admin
{
    return Admin::create([
        'name' => 'Casier Test',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026, 21:00-03:00, bilet 30 lei; „acum” = 23:00. */
function spendParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere cheltuieli',
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

function spendItem(Admin $admin): MenuItem
{
    $item = MenuItem::query()->where('name', 'Cocktail cheltuieli')->first();
    if ($item) {
        return $item;
    }

    $stock = StockItem::create(['name' => 'Vodcă spend', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $stock->recordInitialStock(100000, 0.10, $admin->id);
    $category = MenuCategory::create(['name' => 'Cocktailuri spend', 'is_active' => true]);
    $item = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail cheltuieli', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $item->recipeLines()->create(['stock_item_id' => $stock->id, 'qty' => 50]);

    return $item;
}

/** O vanzare de 30 lei la bar (sesiunea petrecerii) cu metoda data, pe numele unui participant (optional). */
function spendBarSale(Party $party, Admin $admin, string $method = 'cash', ?int $participantId = null): Sale
{
    $item = spendItem($admin);
    $group = SalesGroup::query()->where('party_id', $party->id)->where('status', 'open')->first() ?? SalesGroup::openFor($party->id, $admin->id);
    $payment = $method === 'token' ? ['method' => 'token', 'tokens' => 6] : ['method' => $method, 'amount' => 30];

    return SaleRecorder::record($group, [['menu_item_id' => $item->id, 'qty' => 1]], [$payment], adminId: $admin->id, participantId: $participantId);
}

/** Pune vanzarile in raportarea FINALIZATA a petrecerii (statisticile de bar numara doar raportarile finalizate). */
function spendFinalize(Party $party, array $sales): StockReport
{
    $report = new StockReport();
    $report->forceFill(['date' => '2026-10-03', 'status' => 'finalized', 'party_id' => $party->id])->save();
    Sale::whereIn('id', array_map(fn ($s) => $s->id, $sales))->update(['report_id' => $report->id]);

    return $report;
}

it('recepția: dupa salvare nu apare „Mai lipsesc”, ci „De incasat”, iar „Restul” completeaza suma', function () {
    $this->actingAs(spendAdmin(), 'admin');
    spendParty();

    $c = Livewire::test(ReceptionForm::class)
        ->assertSee('De încasat 30,00 lei')
        ->assertDontSee('Mai lipsesc');

    $c->call('payAll', 'cash')->call('save')->assertSet('error', null)
        ->assertSee('De încasat 30,00 lei')       // formularul s-a golit: din nou neutru, nu avertisment
        ->assertDontSee('Mai lipsesc');

    // Plata partiala: avertisment; „Restul” completeaza diferenta (tinand cont de celelalte randuri).
    $c->call('addPayment')
        ->set('payments.0.amount', '10')
        ->assertSee('Mai lipsesc 20,00 lei')
        ->call('fillRemaining', 1)
        ->assertSet('payments.1.amount', '20')
        ->assertSee('Încasat complet');

    // „Restul” pe un rand care are deja o suma inlocuieste-o cu ce lipseste fata de celelalte randuri.
    $c->set('payments.1.amount', '5')->call('fillRemaining', 1)->assertSet('payments.1.amount', '20');
    $c->set('payments.0.amount', '30')->call('fillRemaining', 1)->assertSet('payments.1.amount', '');
});

it('recepția: mesajul de succes si cel de eroare apar in cardul „Intrare noua”, nu in capul paginii', function () {
    $this->actingAs(spendAdmin(), 'admin');
    spendParty();

    $c = Livewire::test(ReceptionForm::class);

    // Eroare (plata lipseste): dupa titlul cardului si inainte de listele din dreapta.
    $html = $c->call('save')->html();
    $needle = 'nu se potrivește';
    expect(substr_count($html, $needle))->toBe(1)
        ->and(strpos($html, $needle))->toBeGreaterThan(strpos($html, 'Intrare nouă'))
        ->and(strpos($html, $needle))->toBeLessThan(strpos($html, 'Ultimele intrări'));

    // Succes: la fel.
    $html = $c->call('payAll', 'cash')->call('save')->html();
    $needle = 'Înregistrat: 1 × Bilet';
    expect(substr_count($html, $needle))->toBe(1)
        ->and(strpos($html, $needle))->toBeGreaterThan(strpos($html, 'Intrare nouă'))
        ->and(strpos($html, $needle))->toBeLessThan(strpos($html, 'Ultimele intrări'));

    // Anularea unei intrari: mesajul apare in cardul „Ultimele intrari”.
    $batch = PartyEntry::first()->batch;
    $html = $c->call('cancel', $batch, 'Greșeală')->html();
    expect(strpos($html, 'Intrarea a fost anulată.'))->toBeGreaterThan(strpos($html, 'Ultimele intrări'));
});

it('tokeni: „Restul”, text neutru dupa salvare si mesaj lânga buton', function () {
    $this->actingAs(spendAdmin(), 'admin');
    $party = spendParty();

    $c = Livewire::test(TokenSale::class, ['partyId' => $party->id])
        ->assertSee('De încasat 50,00 lei')->assertDontSee('Mai lipsesc');

    $c->set('payments.0.amount', '20')->assertSee('Mai lipsesc 30,00 lei')
        ->call('addPayment')->call('fillRemaining', 1)->assertSet('payments.1.amount', '30')
        ->assertSee('Încasat complet');

    $c->call('sell')->assertSet('error', null)->assertSee('Vândut: 10 tokeni')
        ->assertSee('De încasat 50,00 lei')->assertDontSee('Mai lipsesc');
});

it('tokeni: alege participantul care cumpara, il salveaza si il arata in listele din Recepție', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');

    $c = Livewire::test(TokenSale::class, ['partyId' => $party->id])
        ->set('participantSearch', '0722')->assertSee('Ion Popescu')
        ->call('addParticipant', $ion->id)
        ->assertSet('participantIds', [$ion->id])
        ->call('addParticipant', $ana->id)                 // un singur cumparator: il inlocuieste pe primul
        ->assertSet('participantIds', [$ana->id])
        ->call('payAll', 'cash')->call('sell')
        ->assertSet('error', null)
        ->assertSet('participantIds', []);                 // selectia se goleste dupa vanzare

    $tx = TokenTransaction::first();
    expect($tx->participant_id)->toBe($ana->id);

    Livewire::test(ReceptionForm::class)->assertSee('Ana Ionescu');
    Livewire::test(\App\Livewire\Admin\Reception\Tokens::class)->assertSee('Ana Ionescu');

    // Participant nou direct din card.
    $c->call('toggleNewParticipant')->set('newName', 'Maria Dumitru')->set('newPhone', '0744 555 666')->call('createParticipant')
        ->assertSet('participantError', null)
        ->assertSet('participantIds', [Participant::where('phone', '+40744555666')->first()->id]);
});

it('vanzarea de tokeni refuza participanti inexistenti sau anonimizati', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $gone = ParticipantRegistry::create('Șters', '0722 000 111');
    ParticipantRegistry::anonymize($gone);

    expect(fn () => TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 50]], $admin->id, participantId: 999))->toThrow(DomainException::class, 'nu există');
    expect(fn () => TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 50]], $admin->id, participantId: $gone->id))->toThrow(DomainException::class, 'anonimizat');
    expect(TokenTransaction::count())->toBe(0);
});

it('bar: vanzarea se salveaza pe numele participantului si il arata in lista de vanzari', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $gone = ParticipantRegistry::create('Șters', '0722 000 111');
    ParticipantRegistry::anonymize($gone);

    $sale = spendBarSale($party, $admin, 'cash', $ion->id);
    expect($sale->customer_id)->toBe($ion->id)->and($sale->identified_by)->toBe('phone')->and($sale->customer->name)->toBe('Ion Popescu');

    expect(spendBarSale($party, $admin)->customer_id)->toBeNull();
    expect(fn () => spendBarSale($party, $admin, 'cash', 999))->toThrow(DomainException::class, 'nu există');
    expect(fn () => spendBarSale($party, $admin, 'cash', $gone->id))->toThrow(DomainException::class, 'anonimizat');
    expect(Sale::count())->toBe(2);

    $this->get(route('admin.sales.index'))->assertOk()->assertSee('Client:')->assertSee('Ion Popescu');
});

it('bar: formularul de vanzare din admin permite alegerea clientului', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $item = spendItem($admin);
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');

    Livewire::test(SaleForm::class)
        ->assertSee('Client')->assertSee('Caută după telefon sau nume')
        ->call('addParticipant', $ion->id)->call('addParticipant', $ana->id)   // un singur client
        ->assertSet('participantIds', [$ana->id])
        ->set('lines', [['menu_item_id' => (string) $item->id, 'qty' => '1']])
        ->set('payments', [['method' => 'cash', 'amount' => '30', 'tokens' => '']])
        ->call('save');

    $sale = Sale::first();
    expect($sale)->not->toBeNull()->and($sale->customer_id)->toBe($ana->id)->and($sale->source)->toBe('manual');
});

it('fisa participantului: cheltuit la bar (fara beneficii), tokeni cumparati si defalcare pe petreceri', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $a = spendParty(['name' => 'Petrecere A']);
    $b = spendParty(['name' => 'Petrecere B', 'start_date' => '2026-10-10']);
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00'));
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');

    EntryRecorder::record($a, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, participants: [$ion->id]);
    TokenLedger::sell($a, 20, [['method' => 'cash', 'amount' => 100]], $admin->id, participantId: $ion->id);
    spendBarSale($a, $admin, 'cash', $ion->id);          // 30
    spendBarSale($a, $admin, 'token', $ion->id);         // 6 tokeni = 30
    spendBarSale($a, $admin, 'benefit', $ion->id);       // cadou: nu conteaza ca cheltuiala
    spendBarSale($a, $admin, 'cash', $ion->id)->cancel($admin->id, 'Anulat');   // anulata: nu conteaza
    $cancelledTokens = TokenLedger::sell($a, 4, [['method' => 'cash', 'amount' => 20]], $admin->id, participantId: $ion->id);
    TokenLedger::cancel($cancelledTokens->id, 'Greșeală', $admin->id);         // anulata: nu conteaza
    spendBarSale($b, $admin, 'cash', $ion->id);          // 30 la alta petrecere

    $s = ParticipantStats::for($ion);
    expect($s->bar_spent)->toBe(90.0)->and($s->bar_sales)->toBe(3)
        ->and($s->tokens)->toBe(20)->and($s->tokens_amount)->toBe(100.0)
        ->and($s->entries)->toBe(1)->and($s->entries_amount)->toBe(30.0)->and($s->entries_paid)->toBe(1)
        ->and($s->parties->pluck('party')->all())->toBe(['Petrecere B', 'Petrecere A']);   // cele mai recente primele

    $rowA = $s->parties->firstWhere('party', 'Petrecere A');
    expect($rowA->bar_spent)->toBe(60.0)->and($rowA->tokens)->toBe(20)->and($rowA->entries)->toBe(1);

    Livewire::test(ParticipantsShow::class, ['participant' => $ion])
        ->assertSee('Cheltuit la bar')->assertSee('90,00')->assertSee('Tokeni cumpărați')->assertSee('Pe petreceri')
        ->assertSee('Petrecere A')->assertSee('Petrecere B');
});

it('statisticile petrecerii: clasament pe participanti, cu bar din raportarile finalizate si tokeni/intrari imediat', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 111 111');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0722 222 222');
    $mihai = ParticipantRegistry::create('Mihai Vasile', '0722 333 333');

    EntryRecorder::record($party, 'Bilet', 3, [['method' => 'cash', 'amount' => 90]], adminId: $admin->id, participants: [$ion->id, $ana->id, $mihai->id]);
    TokenLedger::sell($party, 50, [['method' => 'cash', 'amount' => 250]], $admin->id, participantId: $ana->id);
    TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 50]], $admin->id, participantId: $ion->id);

    $finalized = [
        spendBarSale($party, $admin, 'cash', $ion->id),
        spendBarSale($party, $admin, 'cash', $ion->id),
        spendBarSale($party, $admin, 'card', $ana->id),
        spendBarSale($party, $admin, 'benefit', $mihai->id),   // cadou: nu e cheltuiala
        spendBarSale($party, $admin, 'cash'),                  // anonim
    ];
    spendFinalize($party, $finalized);
    spendBarSale($party, $admin, 'cash', $mihai->id);          // neraportata inca: nu intra in statistici de bar

    $p = PartyStats::participants($party, PartyStats::finalizedReports($party)->pluck('id')->all());
    $row = fn (string $name) => $p->rows->firstWhere('name', $name);
    expect($row('Ion Popescu')->bar_spent)->toBe(60.0)->and($row('Ion Popescu')->bar_sales)->toBe(2)->and($row('Ion Popescu')->tokens)->toBe(10)
        ->and($row('Ana Ionescu')->bar_spent)->toBe(30.0)->and($row('Ana Ionescu')->tokens)->toBe(50)
        ->and($row('Mihai Vasile')->bar_spent)->toBe(0.0)->and($row('Mihai Vasile')->entries)->toBe(1)
        ->and($p->rows)->toHaveCount(3)
        ->and($p->bar_identified)->toBe(120.0)          // 4 din 5 vanzari (finalizate) au participant: 4 x 30
        ->and($p->bar_identified_pct)->toBe(80.0);

    $stats = PartyStats::for($party);
    expect($stats->bar_identified_pct)->toBe(80.0);

    // Pagina: bloc „Participanti”, cu comutator Bar / Tokeni / Intrari.
    $page = Livewire::test(PartyStatsPage::class, ['party' => $party])
        ->assertSee('Cheltuit la bar')->assertSee('Tokeni cumpărați')->assertSee('Vânzări cu participant');
    $byBar = $page->html();
    expect(strpos($byBar, 'Ion Popescu'))->toBeLessThan(strpos($byBar, 'Ana Ionescu'));           // 60 > 30
    expect(strpos($byBar, 'Mihai Vasile'))->toBeFalse();                                            // 0 lei: nu apare la clasamentul de bar

    $byTokens = $page->set('topBy', 'tokens')->html();
    expect(strpos($byTokens, 'Ana Ionescu'))->toBeLessThan(strpos($byTokens, 'Ion Popescu'));      // 50 > 10 tokeni
});

it('statisticile arata participantii si fara raportari finalizate (intrari si tokeni)', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 111 111');
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, participants: [$ion->id]);

    expect(PartyStats::for($party)->participants)->not->toBeNull();

    $this->get(route('admin.parties.stats', ['party' => $party, 'top' => 'entries']))
        ->assertOk()
        ->assertSee('Ion Popescu')
        ->assertSee('Nicio raportare finalizată');
});

it('lista de participanti: coloane de cheltuieli si sortare dupa bar, tokeni, intrari', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 111 111');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0722 222 222');
    $bogdan = ParticipantRegistry::create('Bogdan Ilie', '0722 333 333');

    spendBarSale($party, $admin, 'cash', $ion->id);
    spendBarSale($party, $admin, 'cash', $ion->id);
    spendBarSale($party, $admin, 'cash', $ana->id);
    TokenLedger::sell($party, 40, [['method' => 'cash', 'amount' => 200]], $admin->id, participantId: $bogdan->id);
    EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 60]], adminId: $admin->id, participants: [$ana->id, $bogdan->id]);

    $names = fn ($component) => $component->viewData('participants')->pluck('name')->all();

    $c = Livewire::test(ParticipantsIndex::class)->assertSee('Cheltuit la bar')->assertSee('Tokeni cumpărați');
    expect($names($c))->toBe(['Ana Ionescu', 'Bogdan Ilie', 'Ion Popescu']);                       // implicit: dupa nume

    expect($names($c->set('sort', 'bar')))->toBe(['Ion Popescu', 'Ana Ionescu', 'Bogdan Ilie']);    // 60 > 30 > 0
    expect($names($c->set('sort', 'tokens')))->toBe(['Bogdan Ilie', 'Ana Ionescu', 'Ion Popescu']); // 40 > 0 = 0 (apoi nume)
    expect($names($c->set('sort', 'entries')))->toBe(['Ana Ionescu', 'Bogdan Ilie', 'Ion Popescu']); // 1 = 1 > 0 (apoi nume)

    $first = $c->set('sort', 'bar')->viewData('participants')->first();
    expect((float) $first->bar_spent)->toBe(60.0)->and((int) $first->entries_count)->toBe(0);
    $c->set('sort', 'valoare-necunoscuta')->assertOk();                                             // sortare invalida = dupa nume
});

it('la salvarea unei intrări cu participanți, cardul de tokeni al aceleiași petreceri primește chip-uri rapide de selecție', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');

    $form = Livewire::test(ReceptionForm::class)
        ->set('partyId', $party->id)
        ->call('addParticipant', $ion->id)
        ->call('addParticipant', $ana->id)
        ->set('count', 2)
        ->call('payAll', 'cash')
        ->call('save');

    $form->assertDispatched('entry-recorded', partyId: $party->id, participantIds: [$ion->id, $ana->id]);

    // Cardul de tokeni AL ACELEIAȘI petreceri arată chip-uri, fără căutare.
    $tok = Livewire::test(TokenSale::class, ['partyId' => $party->id]);
    $tok->dispatch('entry-recorded', partyId: $party->id, participantIds: [$ion->id, $ana->id]);
    $tok->assertSee('Ion Popescu')->assertSee('Ana Ionescu')->assertSet('participantIds', []);

    // Un tap alege participantul, fără căutare, și golește chip-urile.
    $tok->call('pickQuickParticipant', $ion->id)
        ->assertSet('participantIds', [$ion->id])
        ->assertDontSee('Ana Ionescu'); // chip-urile s-au golit dupa alegere

    // Cardul de tokeni AL ALTEI petreceri nu primește chip-uri (evenimentul poartă party_id).
    $other = spendParty(['name' => 'Altă petrecere']);
    $tokOther = Livewire::test(TokenSale::class, ['partyId' => $other->id]);
    $tokOther->dispatch('entry-recorded', partyId: $party->id, participantIds: [$ion->id]);
    $tokOther->assertDontSee('Ion Popescu');
});

it('o intrare fără participanți identificați nu anunță niciun chip rapid', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();

    Livewire::test(ReceptionForm::class)
        ->set('partyId', $party->id)
        ->call('payAll', 'cash')
        ->call('save')
        ->assertNotDispatched('entry-recorded');
});

it('chip-urile rapide dispar după o vânzare de tokeni finalizată', function () {
    $admin = spendAdmin();
    $this->actingAs($admin, 'admin');
    $party = spendParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');

    $tok = Livewire::test(TokenSale::class, ['partyId' => $party->id]);
    $tok->dispatch('entry-recorded', partyId: $party->id, participantIds: [$ion->id]);
    $tok->assertSee('Ion Popescu');

    $tok->call('setTokens', 5)->call('payAll', 'cash')->call('sell')
        ->assertSet('error', null)
        ->assertDontSee('Ion Popescu');
});
