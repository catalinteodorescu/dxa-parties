<?php

use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Settings\PaymentMethods as PaymentMethodsPanel;
use App\Models\Admin;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Party;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalesGroup;
use App\Models\StockItem;
use App\Services\SaleRecorder;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => PaymentMethods::flushGuards());

function payAdmin(): Admin
{
    return Admin::create([
        'name' => 'Pay Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere simpla cu lista de metode dorita. */
function payParty(?array $methods, string $name = 'Petrecere plăți'): Party
{
    return Party::create([
        'name' => $name,
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'payment_methods' => $methods,
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'draft',
    ]);
}

/** Cocktail de 30 lei, fara stoc (doar venit): ajunge pentru testele de plata. */
function payCocktail(Admin $admin): MenuItem
{
    $vodka = StockItem::create(['name' => 'Vodcă', 'unit' => 'ml', 'is_active' => true, 'created_by' => $admin->id]);
    $vodka->recordInitialStock(1000, 0.10, $admin->id);
    $category = MenuCategory::create(['name' => 'Cocktailuri', 'is_active' => true]);
    $cocktail = MenuItem::create(['menu_category_id' => $category->id, 'name' => 'Cocktail', 'price' => 30, 'is_active' => true, 'created_by' => $admin->id]);
    $cocktail->recipeLines()->create(['stock_item_id' => $vodka->id, 'qty' => 50]);

    return $cocktail;
}

function payLine(MenuItem $item, int $qty = 1): array
{
    return [['menu_item_id' => $item->id, 'qty' => $qty]];
}

it('porneste cu cash, card, transfer, Revolut si tokeni activi, iar creditele oprite', function () {
    expect(array_keys(PaymentMethods::enabled()))->toBe(['cash', 'card', 'transfer', 'revolut', 'token'])
        ->and(PaymentMethods::tokenMode())->toBe('active')
        ->and(PaymentMethods::isEnabled('credit'))->toBeFalse()
        ->and(PaymentMethods::creditsPurchasable())->toBeFalse()
        ->and(PaymentMethods::labels())->toHaveKeys(['cash', 'credit', 'benefit']);
});

it('nu lasa cash-ul sa fie dezactivat', function () {
    expect(fn () => PaymentMethods::setActive('cash', false))->toThrow(DomainException::class, 'Cash nu poate fi dezactivat.');
    expect(PaymentMethods::isEnabled('cash'))->toBeTrue();
});

it('adauga metode custom cu cheie stabila, fara duplicate, si le redenumeste fara sa schimbe cheia', function () {
    $this->actingAs(payAdmin(), 'admin');

    $m = PaymentMethods::create('Vouchere partenere');
    expect($m->key)->toBe('c_vouchere_partenere')->and($m->is_builtin)->toBeFalse();

    expect(fn () => PaymentMethods::create('vouchere PARTENERE'))->toThrow(DomainException::class);
    expect(fn () => PaymentMethods::create('card'))->toThrow(DomainException::class);
    expect(fn () => PaymentMethods::create('   '))->toThrow(DomainException::class);
    expect(PaymentMethods::create('Vouchere-partenere!')->key)->toBe('c_vouchere_partenere_2');

    PaymentMethods::rename($m->id, 'Tichete');
    expect($m->fresh()->key)->toBe('c_vouchere_partenere')->and(PaymentMethods::label('c_vouchere_partenere'))->toBe('Tichete');
    expect(fn () => PaymentMethods::rename(PaymentMethods::find('card')->id, 'Altceva'))->toThrow(DomainException::class);
});

it('sterge o metoda custom doar daca nu e folosita; predefinitele nu se sterg', function () {
    $this->actingAs(payAdmin(), 'admin');

    $free = PaymentMethods::create('Nefolosită');
    $used = PaymentMethods::create('Folosită');
    payParty([$used->key]);

    expect(fn () => PaymentMethods::delete($used->id))->toThrow(DomainException::class, 'Dezactiveaz-o');
    expect(fn () => PaymentMethods::delete(PaymentMethods::find('revolut')->id))->toThrow(DomainException::class);

    PaymentMethods::delete($free->id);
    expect(PaymentMethods::find($free->key))->toBeNull();

    // O metoda folosita intr-o plata din vanzari nu se sterge nici ea.
    $sold = PaymentMethods::create('Doar vândută');
    $sale = Sale::create(['status' => 'completed', 'total' => 10, 'sold_at' => now(), 'source' => 'manual']);
    SalePayment::create(['sale_id' => $sale->id, 'method' => $sold->key, 'amount' => 10]);
    expect(PaymentMethods::isUsed($sold->key))->toBeTrue();
});

it('blocheaza dezactivarea creditelor cat un gard raporteaza solduri, si opreste cumpararea odata cu ele', function () {
    $this->actingAs(payAdmin(), 'admin');

    PaymentMethods::setActive('credit', true);
    PaymentMethods::setCreditsPurchasable(true);
    expect(PaymentMethods::creditsPurchasable())->toBeTrue();

    // Cat exista solduri, nu se poate dezactiva.
    $balance = 120.0;
    PaymentMethods::registerGuard('credit', function () use (&$balance) {
        return $balance > 0 ? 'Există 120 lei în portofelele participanților.' : null;
    });
    expect(fn () => PaymentMethods::setActive('credit', false))->toThrow(DomainException::class, '120 lei');
    expect(PaymentMethods::isEnabled('credit'))->toBeTrue()->and(PaymentMethods::creditsPurchasable())->toBeTrue();

    // Fara solduri, se poate; cumpararea se opreste automat.
    $balance = 0.0;
    PaymentMethods::setActive('credit', false);
    expect(PaymentMethods::isEnabled('credit'))->toBeFalse()->and(PaymentMethods::creditsPurchasable())->toBeFalse()
        ->and((bool) Settings::get('credits_purchasable'))->toBeFalse();
});

it('nu permite cumpararea de credite cat metoda credit e dezactivata, dar permite oprirea ei oricand', function () {
    $this->actingAs(payAdmin(), 'admin');

    expect(fn () => PaymentMethods::setCreditsPurchasable(true))->toThrow(DomainException::class, 'Acceptă credite');

    PaymentMethods::setActive('credit', true);
    PaymentMethods::setCreditsPurchasable(true);
    PaymentMethods::registerGuard('credit', fn () => 'Există solduri.');

    PaymentMethods::setCreditsPurchasable(false); // oprirea vanzarii nu e blocata de gard
    expect(PaymentMethods::creditsPurchasable())->toBeFalse()->and(PaymentMethods::isEnabled('credit'))->toBeTrue();
});

it('are trei stari pentru tokeni si tine uses_tokens derivat', function () {
    $this->actingAs(payAdmin(), 'admin');

    expect(PaymentMethods::tokensSellable())->toBeTrue();

    PaymentMethods::setTokenMode('collect_only');
    expect(PaymentMethods::tokenMode())->toBe('collect_only')
        ->and((bool) Settings::get('uses_tokens'))->toBeTrue()
        ->and(PaymentMethods::isEnabled('token'))->toBeTrue()
        ->and(PaymentMethods::tokensSellable())->toBeFalse();

    PaymentMethods::setTokenMode('off');
    expect((bool) Settings::get('uses_tokens'))->toBeFalse()
        ->and(PaymentMethods::isEnabled('token'))->toBeFalse()
        ->and(array_keys(PaymentMethods::forBar(null)))->not->toContain('token');

    PaymentMethods::setTokenMode('active');
    expect((bool) Settings::get('uses_tokens'))->toBeTrue()->and(PaymentMethods::isEnabled('token'))->toBeTrue();

    expect(fn () => PaymentMethods::setTokenMode('altceva'))->toThrow(DomainException::class);
});

it('cere curs valid pentru tokeni si respecta gardul la oprire', function () {
    $this->actingAs(payAdmin(), 'admin');

    expect(fn () => PaymentMethods::setTokenRate(0))->toThrow(DomainException::class);

    PaymentMethods::setTokenRate(6);
    expect(PaymentMethods::tokenRate())->toBe(6.0);

    PaymentMethods::setTokenMode('off');
    Settings::set('token_rate', 0);
    expect(fn () => PaymentMethods::setTokenMode('active'))->toThrow(DomainException::class, 'curs');

    PaymentMethods::setTokenRate(5);
    PaymentMethods::setTokenMode('active');
    PaymentMethods::registerGuard('token', fn () => 'Mai sunt 40 de tokeni în circulație.');
    expect(fn () => PaymentMethods::setTokenMode('off'))->toThrow(DomainException::class, '40 de tokeni');
    PaymentMethods::setTokenMode('collect_only'); // retragerea treptata ramane permisa
    expect(PaymentMethods::tokenMode())->toBe('collect_only');
});

it('calculeaza metodele unei petreceri: subset din cele active, tokeni doar la bar, cash mereu la bar (la intrare doar daca e bifat)', function () {
    $this->actingAs(payAdmin(), 'admin');
    PaymentMethods::setActive('credit', true);

    $party = payParty(['card', 'token', 'credit']);

    expect(array_keys(PaymentMethods::forBar($party)))->toBe(['cash', 'card', 'token', 'credit'])
        ->and(array_keys(PaymentMethods::forEntry($party)))->toBe(['card', 'credit']); // fara tokeni; cash nu e fortat la intrare

    // Lista goala / fara petrecere = toate cele active.
    expect(array_keys(PaymentMethods::forBar(payParty([]))))->toBe(array_keys(PaymentMethods::enabled()))
        ->and(array_keys(PaymentMethods::forBar(null)))->toBe(array_keys(PaymentMethods::enabled()));

    // O metoda dezactivata dispare din petrecere fara sa fie stearsa din lista ei.
    PaymentMethods::setActive('card', false);
    expect(array_keys(PaymentMethods::forBar($party)))->toBe(['cash', 'token', 'credit'])
        ->and($party->fresh()->payment_methods)->toContain('card')
        ->and(PaymentMethods::describe($party->payment_methods))->toContain('Card (dezactivată)');
});

it('inregistreaza la bar orice metoda activa, respinge cele inactive sau neacceptate la petrecere, si permite mereu beneficiul', function () {
    $admin = payAdmin();
    $this->actingAs($admin, 'admin');
    $cocktail = payCocktail($admin);

    $party = payParty(['card', 'transfer']);
    $group = SalesGroup::openFor($party->id, $admin->id);

    // Card e activ si acceptat la petrecere.
    $sale = SaleRecorder::record($group, payLine($cocktail), [['method' => 'card', 'amount' => 30]], adminId: $admin->id);
    expect($sale->payments->pluck('method')->all())->toBe(['card']);

    // Cash e mereu acceptat; beneficiul (voucher) la fel; randurile goale nu conteaza.
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'cash', 'amount' => 20], ['method' => 'benefit', 'amount' => 10], ['method' => 'token', 'tokens' => '']], adminId: $admin->id);

    // Tokenii nu sunt in lista petrecerii; Revolut e activ dar nealeasa; creditele sunt dezactivate global.
    expect(fn () => SaleRecorder::record($group, payLine($cocktail), [['method' => 'token', 'tokens' => 6]]))->toThrow(DomainException::class, 'nu este acceptată');
    expect(fn () => SaleRecorder::record($group, payLine($cocktail), [['method' => 'revolut', 'amount' => 30]]))->toThrow(DomainException::class, 'la această petrecere');
    expect(fn () => SaleRecorder::record($group, payLine($cocktail), [['method' => 'credit', 'amount' => 30]]))->toThrow(DomainException::class);
    expect(fn () => SaleRecorder::record($group, payLine($cocktail), [['method' => 'inexistent', 'amount' => 30]]))->toThrow(DomainException::class, 'necunoscută');

    // Fara sesiune (vanzare simpla): toate metodele active.
    SaleRecorder::record(null, payLine($cocktail), [['method' => 'revolut', 'amount' => 30]], adminId: $admin->id);

    // Bypass explicit (seedere/importuri): metoda trebuie totusi sa existe.
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'credit', 'amount' => 30]], adminId: $admin->id, enforceMethods: false);
    expect(fn () => SaleRecorder::record($group, payLine($cocktail), [['method' => 'inexistent', 'amount' => 30]], enforceMethods: false))->toThrow(DomainException::class);
});

it('nu accepta tokeni la bar cand sunt opriti', function () {
    $admin = payAdmin();
    $this->actingAs($admin, 'admin');
    $cocktail = payCocktail($admin);

    PaymentMethods::setTokenMode('off');

    expect(fn () => SaleRecorder::record(null, payLine($cocktail), [['method' => 'token', 'tokens' => 6]]))->toThrow(DomainException::class, 'Tokeni');
});

it('formularul de vanzare din admin ofera doar metodele acceptate de petrecerea sesiunii', function () {
    $admin = payAdmin();
    $this->actingAs($admin, 'admin');
    PaymentMethods::setActive('credit', true);

    $party = payParty(['transfer', 'credit']);
    SalesGroup::openFor($party->id, $admin->id);

    // Sesiunea e deja deschisă: formularul o alege automat (implicit), fără să mai fie nevoie s-o cauți.
    $component = Livewire::test(\App\Livewire\Admin\Sales\Form::class);
    $component->assertSet('party_id', (string) $party->id);
    expect(array_keys($component->instance()->methods()))->toBe(['cash', 'transfer', 'credit', 'benefit']);

    // "Fără petrecere": toate metodele.
    $component->set('party_id', '')->set('payments.0.method', 'card');
    expect(array_keys($component->instance()->methods()))->toBe(['cash', 'card', 'transfer', 'revolut', 'token', 'credit', 'benefit']);

    // Revii la petrecere: randul cu o metoda neacceptata acolo (card) revine pe cash.
    $component->set('party_id', (string) $party->id)->assertSet('payments.0.method', 'cash');
    expect(array_keys($component->instance()->methods()))->toBe(['cash', 'transfer', 'credit', 'benefit']);
});

it('precompleteaza o petrecere noua cu metodele active si salveaza cheile alese', function () {
    $this->actingAs(payAdmin(), 'admin');

    $form = Livewire::test(PartyForm::class)
        ->assertSet('payment_methods', ['cash', 'card', 'transfer', 'revolut', 'token']);

    $form->set('name', 'Petrecere de test')
        ->set('start_date', '2026-10-10')
        ->set('start_time', '21:00')
        ->set('end_time', '03:00')
        ->set('payment_methods', ['transfer', 'cash', 'credit', 'c_inventat']) // credit dezactivat + cheie inexistenta
        ->call('save')
        ->assertHasNoErrors();

    $party = Party::where('name', 'Petrecere de test')->firstOrFail();
    expect($party->payment_methods)->toBe(['cash', 'transfer']); // ordinea din Setari; inactivele/necunoscutele nu se salveaza
});

it('pastreaza o metoda deja aleasa pe petrecere chiar daca intre timp a fost dezactivata, pana o debifezi', function () {
    $this->actingAs(payAdmin(), 'admin');

    $party = payParty(['cash', 'card', 'revolut']);
    PaymentMethods::setActive('revolut', false);

    Livewire::test(PartyForm::class, ['party' => $party])
        ->assertSet('payment_methods', ['cash', 'card', 'revolut'])
        ->assertSee('Revolut')
        ->assertSee('(dezactivată)')
        ->call('save')
        ->assertHasNoErrors();
    expect($party->fresh()->payment_methods)->toBe(['cash', 'card', 'revolut']);

    Livewire::test(PartyForm::class, ['party' => $party])
        ->set('payment_methods', ['cash', 'card'])
        ->call('save')
        ->assertHasNoErrors();
    expect($party->fresh()->payment_methods)->toBe(['cash', 'card']);
});

it('afiseaza metodele petrecerii cu eticheta din Setari in pagina ei', function () {
    $this->actingAs(payAdmin(), 'admin');
    $method = PaymentMethods::create('Vouchere partenere');
    $party = payParty(['cash', $method->key]);

    $this->get(route('admin.parties.show', $party))
        ->assertOk()
        ->assertSee('Vouchere partenere');
});

it('panoul din Setari: activeaza metode, adauga custom, arata erorile gardurilor si nu sterge metodele folosite', function () {
    $this->actingAs(payAdmin(), 'admin');

    Livewire::test(PaymentMethodsPanel::class)
        ->assertSee('Acceptă credite la plată')
        ->call('toggle', 'card')
        ->assertSet('error', null)
        ->set('newLabel', 'Vouchere partenere')
        ->call('add')
        ->assertSet('message', 'Metoda a fost adăugată.')
        ->assertSet('newLabel', '')
        ->assertSee('Vouchere partenere');
    expect(PaymentMethods::isEnabled('card'))->toBeFalse();

    $custom = PaymentMethods::find('c_vouchere_partenere');
    payParty([$custom->key]);

    Livewire::test(PaymentMethodsPanel::class)
        ->call('delete', $custom->id)
        ->assertSet('error', fn ($e) => str_contains($e, 'Dezactiveaz-o'));
    expect(PaymentMethods::find($custom->key))->not->toBeNull();

    // Creditele: activare, apoi blocare de gard.
    Livewire::test(PaymentMethodsPanel::class)
        ->call('toggle', 'credit')
        ->call('toggleCreditsPurchasable')
        ->assertSee('Participanții pot cumpăra credite');
    expect(PaymentMethods::creditsPurchasable())->toBeTrue();

    PaymentMethods::registerGuard('credit', fn () => 'Există 50 lei în portofele.');
    Livewire::test(PaymentMethodsPanel::class)
        ->call('toggle', 'credit')
        ->assertSet('error', 'Există 50 lei în portofele.');
    expect(PaymentMethods::isEnabled('credit'))->toBeTrue();
});

it('panoul din Setari: stari de tokeni si curs cu validare', function () {
    $this->actingAs(payAdmin(), 'admin');

    Livewire::test(PaymentMethodsPanel::class)
        ->assertSet('rate', '5')
        ->call('setTokenMode', 'collect_only')
        ->set('rate', '6,5')
        ->call('saveRate')
        ->assertSet('message', 'Cursul a fost salvat.')
        ->set('rate', 'abc')
        ->call('saveRate')
        ->assertSet('error', 'Cursul token → lei trebuie să fie un număr.')
        ->call('setTokenMode', 'off');

    expect(PaymentMethods::tokenRate())->toBe(6.5)->and(PaymentMethods::tokenMode())->toBe('off');
});

it('salvarea formularului de Setari nu suprascrie starea tokenilor/creditelor schimbata intre timp in panou', function () {
    $this->actingAs(payAdmin(), 'admin');

    $settings = Livewire::test(SettingsIndex::class); // formularul e montat cu valorile vechi

    PaymentMethods::setTokenRate(7);
    PaymentMethods::setTokenMode('collect_only');
    PaymentMethods::setActive('credit', true);

    $settings->call('save')->assertHasNoErrors();

    expect(PaymentMethods::tokenRate())->toBe(7.0)
        ->and(PaymentMethods::tokenMode())->toBe('collect_only')
        ->and((bool) Settings::get('uses_tokens'))->toBeTrue()
        ->and(PaymentMethods::isEnabled('credit'))->toBeTrue();
});

it('logheaza schimbarile din Setari', function () {
    $this->actingAs(payAdmin(), 'admin');

    PaymentMethods::setActive('card', false);
    PaymentMethods::create('Tichete');
    PaymentMethods::setTokenRate(6);
    PaymentMethods::setTokenMode('collect_only');
    PaymentMethods::setActive('credit', true);
    PaymentMethods::setCreditsPurchasable(true);

    expect(\App\Models\AdminActivityLog::pluck('action')->all())->toBe([
        'settings.payment_method_toggled',
        'settings.payment_method_created',
        'settings.token_rate_changed',
        'settings.token_mode_changed',
        'settings.payment_method_toggled',
        'settings.credits_purchase_toggled',
    ]);
});

it('migrarea muta petrecerile pe chei: credite -> credit, etichete custom -> metode custom, tokeni pastrati', function () {
    $migration = require base_path('database/migrations/2024_07_04_000001_create_payment_methods_table.php');
    $migration->down();

    // Stare "veche": tokeni opriti, o petrecere cu liste scrise liber, una fara lista, una doar cu card.
    DB::table('settings')->updateOrInsert(['key' => 'uses_tokens'], ['value' => '0']);
    payParty(['cash', 'credite', 'Revolut', 'Vouchere partenere', 'vouchere partenere', 'Plată în rate'], 'A');
    payParty(null, 'B');
    payParty(['card'], 'C');

    $migration->up();

    $a = Party::where('name', 'A')->first()->payment_methods;
    expect($a)->toBe(['cash', 'credit', 'revolut', 'c_vouchere_partenere', 'c_plata_in_rate'])
        ->and(Party::where('name', 'B')->first()->payment_methods)->toBeNull()
        ->and(Party::where('name', 'C')->first()->payment_methods)->toBe(['card']);

    expect(PaymentMethods::tokenMode())->toBe('off')
        ->and(PaymentMethods::isEnabled('credit'))->toBeTrue() // a aparut deja pe o petrecere
        ->and(PaymentMethods::creditsPurchasable())->toBeFalse()
        ->and(PaymentMethods::label('c_plata_in_rate'))->toBe('Plată în rate')
        ->and(PaymentMethods::isEnabled('c_vouchere_partenere'))->toBeTrue();
});

it('migrarea adauga tokenii pe petrecerile cu lista completata, cand tokenii erau activi', function () {
    $migration = require base_path('database/migrations/2024_07_04_000001_create_payment_methods_table.php');
    $migration->down();
    payParty(['cash', 'card'], 'D');

    $migration->up();

    expect(PaymentMethods::tokenMode())->toBe('active')
        ->and(Party::where('name', 'D')->first()->payment_methods)->toBe(['cash', 'card', 'token'])
        ->and(PaymentMethods::isEnabled('credit'))->toBeFalse();
});

it('in inventarul de final, incasarile care nu sunt cash sau tokeni apar pe metoda, fara sa intre in cash-ul asteptat', function () {
    $admin = payAdmin();
    $this->actingAs($admin, 'admin');
    $cocktail = payCocktail($admin);
    PaymentMethods::setActive('credit', true);
    $custom = PaymentMethods::create('Vouchere partenere');

    $group = SalesGroup::openFor(null, $admin->id);
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'card', 'amount' => 30]], adminId: $admin->id);
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'credit', 'amount' => 30]], adminId: $admin->id);
    SaleRecorder::record($group, payLine($cocktail), [['method' => $custom->key, 'amount' => 30]], adminId: $admin->id);
    SaleRecorder::record($group, payLine($cocktail), [['method' => 'benefit', 'amount' => 30]], adminId: $admin->id);

    Livewire::test(\App\Livewire\Admin\StockReports\Form::class)
        ->set('sales_group_id', (string) $group->id)
        ->set('opening_float', '50')
        ->set('counted_cash', '80')
        ->assertSee('Nu se numără fizic')
        ->assertSee('Card 30,00 lei')
        ->assertSee('Credite 30,00 lei')
        ->assertSee('Vouchere partenere 30,00 lei')
        ->assertSee('Beneficiu 30,00 lei')
        ->assertSee('fond 50,00 + încasat în vânzări 30,00') // doar cash-ul intra in cash-ul asteptat
        ->assertSee('corect'); // 50 + 30 = 80 numarat
});

it('paginile de vanzari si de statistici afiseaza metodele custom cu eticheta lor', function () {
    $admin = payAdmin();
    $this->actingAs($admin, 'admin');
    $cocktail = payCocktail($admin);
    $custom = PaymentMethods::create('Vouchere partenere');

    SaleRecorder::record(null, payLine($cocktail), [['method' => $custom->key, 'amount' => 30]], adminId: $admin->id);

    $this->get(route('admin.sales.index'))->assertOk()->assertSee('Vouchere partenere');
    $this->get(route('admin.sales.create'))->assertOk()->assertSee('Vouchere partenere');
});

it('pagina Setari se randeaza cu panoul Metode de plata (si fara vechea sectiune Tokeni)', function () {
    $this->actingAs(payAdmin(), 'admin');

    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Metode de plată')
        ->assertSee('Acceptă credite la plată')
        ->assertSee('Curs token → lei')
        ->assertSee('Doar încasare')
        ->assertDontSee('Folosește tokeni');
});

it('rularea inapoi si din nou a migrarii nu strica petrecerile', function () {
    $migration = require base_path('database/migrations/2024_07_04_000001_create_payment_methods_table.php');
    $migration->down();
    payParty(['cash', 'credite', 'Vouchere partenere'], 'E');
    $migration->up();

    $migration->down();
    expect(Party::where('name', 'E')->first()->payment_methods)->toBe(['cash', 'credite', 'Vouchere partenere']);

    $migration->up();
    expect(Party::where('name', 'E')->first()->payment_methods)->toBe(['cash', 'credit', 'c_vouchere_partenere', 'token'])
        ->and(PaymentMethods::label('c_vouchere_partenere'))->toBe('Vouchere partenere');
});
