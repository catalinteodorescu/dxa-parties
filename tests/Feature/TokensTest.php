<?php

use App\Livewire\Admin\Parties\Index as PartiesIndex;
use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Reception\TokenSale;
use App\Livewire\Admin\Reception\Tokens as TokensPage;
use App\Livewire\Admin\Settings\PaymentMethods as PaymentMethodsPanel;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Models\TokenTransaction;
use App\Services\PartyStats;
use App\Services\SaleRecorder;
use App\Services\TokenLedger;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function tokAdmin(): Admin
{
    return Admin::create([
        'name' => 'Tokeni Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026, 21:00-03:00, publicata; „acum” = 23:00 din prima zi. */
function tokParty(?array $methods = null, array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere tokeni',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => $methods,
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ], $overrides));
}

/** O vanzare la bar (produs de 30 lei) platita cu tokeni, in sesiunea petrecerii. */
function tokBarSale(Party $party, Admin $admin, int $tokens): \App\Models\Sale
{
    static $item = null;
    if ($item === null || ! MenuItem::find($item)) {
        $stock = StockItem::create(['name' => 'Vodcă tok', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
        $stock->recordInitialStock(100000, 0.10, $admin->id);
        $category = MenuCategory::create(['name' => 'Cocktailuri tok', 'is_active' => true]);
        $menu = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail tok', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
        $menu->recipeLines()->create(['stock_item_id' => $stock->id, 'qty' => 50]);
        $item = $menu->id;
    }

    $group = SalesGroup::query()->where('party_id', $party->id)->where('status', 'open')->first()
        ?? SalesGroup::openFor($party->id, $admin->id);

    return SaleRecorder::record($group, [['menu_item_id' => $item, 'qty' => 1]], [['method' => 'token', 'tokens' => $tokens]], adminId: $admin->id);
}

it('numara tokenii in circulatie: emisi net minus incasati la bar, fara mișcari sau vânzări anulate', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    expect(TokenLedger::circulation())->toBe(0);

    TokenLedger::adjust(100, 'Stoc inițial', $admin->id);
    TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);
    expect(TokenLedger::issued())->toBe(120)->and(TokenLedger::circulation())->toBe(120);

    $sale = tokBarSale($party, $admin, 6); // 6 tokeni x 5 lei = 30 lei
    expect(TokenLedger::collected())->toBe(6)->and(TokenLedger::circulation())->toBe(114);

    $sale->cancel($admin->id, 'Greșeală');
    expect(TokenLedger::collected())->toBe(0)->and(TokenLedger::circulation())->toBe(120);
});

it('vinde tokeni cu cursul inghetat, plata mixta si log', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    $tx = TokenLedger::sell($party, 25, [['method' => 'cash', 'amount' => '100'], ['method' => 'card', 'amount' => '25,00']], $admin->id);

    expect($tx->type)->toBe('sold')->and($tx->tokens)->toBe(25)->and((float) $tx->token_rate)->toBe(5.0)->and((float) $tx->amount)->toBe(125.0)
        ->and($tx->party_id)->toBe($party->id)->and($tx->created_by)->toBe($admin->id)
        ->and($tx->payments->mapWithKeys(fn ($p) => [$p->method => (float) $p->amount])->all())->toBe(['cash' => 100.0, 'card' => 25.0]);

    // Schimbarea cursului nu atinge vanzarea veche.
    PaymentMethods::setTokenRate(6);
    $second = TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 60]], $admin->id);
    expect((float) $second->amount)->toBe(60.0)->and((float) $tx->fresh()->amount)->toBe(125.0)->and((float) $tx->fresh()->token_rate)->toBe(5.0);

    expect(AdminActivityLog::pluck('action')->all())->toContain('tokens.sold');
});

it('valideaza vanzarea de tokeni: cantitate, stare tokeni, plata, metode fara credite, petrecere', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    PaymentMethods::setActive('credit', true);
    $party = tokParty(['cash', 'card', 'credit', 'token']);

    $sell = fn (int $n, array $pay) => fn () => TokenLedger::sell($party, $n, $pay, $admin->id);

    expect($sell(0, [['method' => 'cash', 'amount' => 0]]))->toThrow(DomainException::class, 'între 1 și');
    expect($sell(1001, [['method' => 'cash', 'amount' => 5005]]))->toThrow(DomainException::class, 'între 1 și');
    expect($sell(10, [['method' => 'cash', 'amount' => 40]]))->toThrow(DomainException::class, 'nu se potrivește');
    expect($sell(10, []))->toThrow(DomainException::class, 'nu se potrivește');
    expect($sell(10, [['method' => 'credit', 'amount' => 50]]))->toThrow(DomainException::class, 'nu este acceptată la cumpărarea de tokeni');
    expect($sell(10, [['method' => 'token', 'amount' => 50]]))->toThrow(DomainException::class, 'nu este acceptată');
    expect($sell(10, [['method' => 'transfer', 'amount' => 50]]))->toThrow(DomainException::class, 'nu este acceptată'); // nealeasa pe petrecere

    // Doar in starea „Activ”.
    PaymentMethods::setTokenMode('collect_only');
    expect($sell(10, [['method' => 'cash', 'amount' => 50]]))->toThrow(DomainException::class, 'nu se mai vând');
    PaymentMethods::setTokenMode('off');
    expect($sell(10, [['method' => 'cash', 'amount' => 50]]))->toThrow(DomainException::class, 'nu se mai vând');
    PaymentMethods::setTokenMode('active');

    // Petrecere incheiata.
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00'));
    expect($sell(10, [['method' => 'cash', 'amount' => 50]]))->toThrow(DomainException::class, 'nu primește vânzări');
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00'));

    expect(TokenTransaction::count())->toBe(0);
    expect($sell(10, [['method' => 'card', 'amount' => 20], ['method' => 'cash', 'amount' => 30]])()->tokens)->toBe(10);
});

it('ajusteaza tokenii cu semn si motiv, fara sa duca circulatia sub zero', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');

    expect(fn () => TokenLedger::adjust(50, ' ', $admin->id))->toThrow(DomainException::class, 'motiv');
    expect(fn () => TokenLedger::adjust(0, 'x', $admin->id))->toThrow(DomainException::class);
    expect(fn () => TokenLedger::adjust(100001, 'x', $admin->id))->toThrow(DomainException::class);
    expect(fn () => TokenLedger::adjust(-1, 'Corecție', $admin->id))->toThrow(DomainException::class, 'sub zero');

    $tx = TokenLedger::adjust(300, 'Stoc inițial', $admin->id);
    expect($tx->type)->toBe('adjustment')->and($tx->note)->toBe('Stoc inițial')->and(TokenLedger::circulation())->toBe(300);

    TokenLedger::adjust(-40, 'Corecție numărătoare', $admin->id);
    expect(TokenLedger::circulation())->toBe(260);
    expect(fn () => TokenLedger::adjust(-261, 'Prea mult', $admin->id))->toThrow(DomainException::class, 'sub zero');
    expect(AdminActivityLog::pluck('action')->all())->toContain('tokens.adjusted');
});

it('casarea aduce circulatia la zero si deblocheaza oprirea tokenilor; gardul real blocheaza cat sunt in circulatie', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);

    expect(TokenLedger::offBlockReason())->toContain('Mai sunt 20 tokeni în circulație');
    expect(fn () => PaymentMethods::setTokenMode('off'))->toThrow(DomainException::class, '20 tokeni');
    PaymentMethods::setTokenMode('collect_only'); // retragerea treptata ramane permisa
    expect(PaymentMethods::tokenMode())->toBe('collect_only');

    expect(fn () => TokenLedger::writeOff('', $admin->id))->toThrow(DomainException::class, 'motiv');
    $tx = TokenLedger::writeOff('Închidem sistemul de tokeni', $admin->id);
    expect($tx->type)->toBe('write_off')->and($tx->tokens)->toBe(-20)->and(TokenLedger::circulation())->toBe(0);
    expect(fn () => TokenLedger::writeOff('din nou', $admin->id))->toThrow(DomainException::class, 'Nu sunt tokeni');

    PaymentMethods::setTokenMode('off');
    expect(PaymentMethods::tokenMode())->toBe('off')->and(TokenLedger::offBlockReason())->toBeNull();
    expect(AdminActivityLog::pluck('action')->all())->toContain('tokens.written_off');
});

it('nu blocheaza oprirea cand circulatia e negativa (lipseste stocul initial)', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    tokBarSale($party, $admin, 6); // incasati la bar fara nicio emitere inregistrata

    expect(TokenLedger::circulation())->toBe(-6)->and(TokenLedger::offBlockReason())->toBeNull();
    PaymentMethods::setTokenMode('off');
    expect(PaymentMethods::tokenMode())->toBe('off');
});

it('anuleaza o vanzare de tokeni cu motiv, doar daca nu au fost folositi la bar', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    $a = TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);
    $b = TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 50]], $admin->id);
    $adj = TokenLedger::adjust(5, 'Corecție', $admin->id);

    expect(fn () => TokenLedger::cancel($a->id, ' ', $admin->id))->toThrow(DomainException::class, 'motiv');
    expect(fn () => TokenLedger::cancel($adj->id, 'Nu se poate', $admin->id))->toThrow(DomainException::class, 'nu există');

    tokBarSale($party, $admin, 6); // 6 tokeni folositi la bar: circulatia = 35 - 6 = 29
    expect(fn () => TokenLedger::cancel($a->id, 'Greșeală', $admin->id))->not->toThrow(DomainException::class); // 29 - 20 = 9 >= 0

    expect(fn () => TokenLedger::cancel($b->id, 'Greșeală', $admin->id))->toThrow(DomainException::class, 'deja folosiți'); // 9 - 10 < 0
    expect(fn () => TokenLedger::cancel($a->id, 'din nou', $admin->id))->toThrow(DomainException::class, 'deja anulată');

    expect($a->fresh()->isCancelled())->toBeTrue()->and($a->fresh()->cancel_reason)->toBe('Greșeală')
        ->and(TokenTransaction::count())->toBe(3)
        ->and(TokenLedger::circulation())->toBe(9);
});

it('calculeaza tokenii unei petreceri: vanduti la ea vs incasati la bar in sesiunile ei', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    $other = tokParty(null, ['name' => 'Alta', 'start_date' => '2026-10-03']);

    expect(TokenLedger::partyTotals($party))->toBeNull();

    TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);
    TokenLedger::sell($other, 8, [['method' => 'cash', 'amount' => 40]], $admin->id);
    TokenLedger::adjust(50, 'Stoc inițial', $admin->id);   // nu conteaza pentru petrecere
    tokBarSale($party, $admin, 6);
    tokBarSale($other, $admin, 6);
    tokBarSale($party, $admin, 6)->cancel($admin->id, 'Anulat'); // anulata: nu se numara

    $t = TokenLedger::partyTotals($party);
    expect($t->sold)->toBe(20)->and($t->sold_amount)->toBe(100.0)->and($t->collected)->toBe(6)->and($t->diff)->toBe(14);
    expect(TokenLedger::partyTotals($other)->collected)->toBe(6);
});

it('afiseaza tokenii in statistici (sectiune si KPI) doar cand exista date', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    $component = Livewire::test(PartyStatsPage::class, ['party' => $party])->instance();
    $keys = fn ($stats) => array_map(fn ($k) => $k->key, $component->kpis($stats, null));

    expect($keys(PartyStats::for($party)))->not->toContain('tokens_sold');

    TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);
    tokBarSale($party, $admin, 6);

    $stats = PartyStats::for($party);
    expect($stats->tokens_sold)->toBe(20)->and($stats->tokens_collected)->toBe(6)->and($keys($stats))->toContain('tokens_sold', 'tokens_collected');

    $this->get(route('admin.parties.stats', $party))
        ->assertOk()
        ->assertSee('Încasați la bar')
        ->assertSee('Diferență')
        ->assertSee('Nicio raportare finalizată');
});

it('cardul Vinde tokeni din Recepție: vinde cu plata mixta, fara credite, si poate anula', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    PaymentMethods::setActive('credit', true);
    $party = tokParty();

    $c = Livewire::test(TokenSale::class, ['partyId' => $party->id])
        ->assertSee('1 token = 5,00 lei')
        ->assertSee('50,00')                         // implicit 10 tokeni
        ->assertDontSee('Tot cu Credite');           // tokenii nu se cumpara cu credite

    $c->call('payAll', 'card')->assertSet('payments', [['method' => 'card', 'amount' => '50']]);

    $c->set('tokens', 20)->call('addPayment')
        ->set('payments.0.method', 'cash')->set('payments.0.amount', '80')
        ->set('payments.1.method', 'card')->set('payments.1.amount', '20')
        ->assertSee('Încasat complet')
        ->call('sell')
        ->assertSet('error', null)
        ->assertSee('Vândut: 20 tokeni')
        ->assertSet('tokens', 10);

    $tx = TokenTransaction::first();
    expect($tx->tokens)->toBe(20)->and((float) $tx->amount)->toBe(100.0)->and($tx->created_by)->toBe($admin->id);
    $c->assertDispatched('tokens-changed'); // listele din dreapta (Index) se reimprospateaza

    // Eroare de plata: nimic salvat.
    $c->set('payments.0.amount', '10')->call('sell')->assertSet('error', fn ($e) => str_contains($e, 'nu se potrivește'));
    expect(TokenTransaction::count())->toBe(1);
});

it('ecranul Recepție arata ultimele vanzari de tokeni in coloana din dreapta si le poate anula', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();

    $c = Livewire::test(\App\Livewire\Admin\Reception\Form::class)->assertSee('Nicio vânzare de tokeni încă.');

    $tx = TokenLedger::sell($party, 20, [['method' => 'cash', 'amount' => 100]], $admin->id);
    $c->dispatch('tokens-changed')->assertSee('20 tokeni')->assertSee('Tokeni Admin')->assertSee('Cash 100,00');

    $c->call('cancelTokenSale', $tx->id, ' ')->assertSet('tokenCancelError', fn ($e) => str_contains($e, 'motiv'));
    $c->call('cancelTokenSale', $tx->id, 'Greșeală')
        ->assertSet('tokenCancelMessage', 'Vânzarea de tokeni a fost anulată.')
        ->assertDispatched('tokens-changed')
        ->assertSee('Anulat: Greșeală');
    expect(TokenLedger::circulation())->toBe(0);

    // Tokeni folositi la bar: anularea e refuzata cu mesajul din ledger.
    $used = TokenLedger::sell($party, 6, [['method' => 'cash', 'amount' => 30]], $admin->id);
    tokBarSale($party, $admin, 6);
    $c->call('cancelTokenSale', $used->id, 'Prea târziu')->assertSet('tokenCancelError', fn ($e) => str_contains($e, 'deja folosiți'));
});

it('listele din dreapta au inaltime limitata (aprox. 5 randuri) cu scroll intern', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    foreach (range(1, 8) as $i) {
        TokenLedger::sell($party, $i, [['method' => 'cash', 'amount' => $i * 5]], $admin->id);
    }

    Livewire::test(\App\Livewire\Admin\Reception\Form::class)
        ->assertSeeHtml('max-h-[19rem] overflow-y-auto')
        ->assertSee('8 tokeni')->assertSee('1 tokeni'); // toate cele 8 sunt in lista (derulabila)
});

it('meniul are grupul Recepție cu Intrări si Tokeni, iar pagina Tokeni e „Sumar tokeni”', function () {
    $this->actingAs(tokAdmin(), 'admin');
    tokParty();

    $this->get(route('admin.reception.index'))
        ->assertOk()
        ->assertSee(route('admin.reception.index'))
        ->assertSee(route('admin.reception.tokens'))
        ->assertSeeHtml('receptionOpen')
        ->assertSee('Recepție · Intrări');

    $this->get(route('admin.reception.tokens'))->assertOk()->assertSee('Recepție · Sumar tokeni');
});

it('cardul de tokeni nu permite vanzarea cand tokenii sunt „Doar incasare” sau opriti', function () {
    $this->actingAs(tokAdmin(), 'admin');
    $party = tokParty();

    PaymentMethods::setTokenMode('collect_only');
    Livewire::test(TokenSale::class, ['partyId' => $party->id])
        ->assertSee('Doar încasare')
        ->assertDontSee('Vinde tokenii');

    PaymentMethods::setTokenMode('off');
    Livewire::test(TokenSale::class, ['partyId' => $party->id])
        ->assertSee('Tokenii sunt opriți')
        ->assertDontSee('Vinde tokenii');
});

it('ecranul de adăugare a intrărilor include cardul de tokeni pentru petrecerea aleasă', function () {
    $this->actingAs(tokAdmin(), 'admin');
    tokParty();

    $this->get(route('admin.reception.create'))
        ->assertOk()
        ->assertSee('Intrare nouă')
        ->assertSee('Vinde tokenii')
        ->assertSee('În circulație');
});

it('pagina Tokeni: arata circulatia, ajusteaza, casează si listeaza mișcările', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    tokBarSale($party, $admin, 6);

    $c = Livewire::test(TokensPage::class)
        ->assertSee('-6')
        ->assertSee('ajustare pozitivă');   // circulatie negativa: sugereaza stocul initial

    $c->set('adjustTokens', 'abc')->call('adjust')->assertSet('error', fn ($e) => str_contains($e, 'număr întreg'));
    $c->set('adjustTokens', '+100')->set('adjustReason', '')->call('adjust')->assertSet('error', fn ($e) => str_contains($e, 'motiv'));
    $c->set('adjustReason', 'Stoc inițial')->call('adjust')
        ->assertSet('message', 'Ajustarea a fost înregistrată.')
        ->assertSet('adjustTokens', '')
        ->assertSee('Stoc inițial')
        ->assertSee('Ajustare');
    expect(TokenLedger::circulation())->toBe(94);

    $c->call('writeOff')->assertSet('error', fn ($e) => str_contains($e, 'motiv'));
    $c->set('writeOffReason', 'Închidem tokenii')->call('writeOff')
        ->assertSet('message', 'Tokenii rămași în circulație au fost casați.')
        ->assertSee('Casare');
    expect(TokenLedger::circulation())->toBe(0);

    $this->get(route('admin.reception.tokens'))->assertOk()->assertSee('Mișcări');
});

it('panoul din Setari arata circulatia si blocheaza „Oprit” cat mai sunt tokeni', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    TokenLedger::sell($party, 12, [['method' => 'cash', 'amount' => 60]], $admin->id);

    Livewire::test(PaymentMethodsPanel::class)
        ->assertSee('Tokeni în circulație')
        ->assertSee('Nu se pot opri: Mai sunt 12 tokeni')
        ->call('setTokenMode', 'off')
        ->assertSet('error', fn ($e) => str_contains($e, '12 tokeni'));
    expect(PaymentMethods::tokenMode())->toBe('active');
});

it('nu lasa stergerea unei petreceri cu vanzari de tokeni', function () {
    $admin = tokAdmin();
    $this->actingAs($admin, 'admin');
    $party = tokParty();
    TokenLedger::sell($party, 10, [['method' => 'cash', 'amount' => 50]], $admin->id);

    Livewire::test(PartiesIndex::class)->call('delete', $party->id);

    expect(Party::find($party->id))->not->toBeNull();
});

it('inregistreaza garda tokenilor o singura data (idempotent) si o pastreaza pentru alte garduri', function () {
    PaymentMethods::flushGuards();
    PaymentMethods::registerGuard('token', fn () => 'A', 'x');
    PaymentMethods::registerGuard('token', fn () => 'B', 'x'); // acelasi id: inlocuieste
    expect(PaymentMethods::blockReason('token'))->toBe('B');

    PaymentMethods::registerGuard('token', fn () => null);
    expect(PaymentMethods::blockReason('token'))->toBe('B');
    PaymentMethods::flushGuards();
});
