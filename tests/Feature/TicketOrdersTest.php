<?php

use App\Livewire\Participant\PartyShow;
use App\Livewire\Participant\Tickets;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Ticket;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Support\PartyPublic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 14). Cumpărarea de bilete din aplicație: comandă, prețuri, trepte, limite, coduri, bilete pe telefoane. */
function toParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere bilete', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function toUser(string $name = 'Ana Cumpărător', string $phone = '0722111222'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function toCode(Party $party, array $o = []): PartyDiscountCode
{
    return PartyDiscountCode::create(array_merge([
        'party_id' => $party->id, 'code' => 'PROMO10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'max_uses_per_participant' => null,
    ], $o));
}

it('o comandă de 1 bilet: bilet valabil pe numele cumpărătorului, cu QR propriu, de plătit la intrare', function () {
    $party = toParty();
    $ana = toUser();

    $order = TicketOrders::place($ana, $party, 'Bilet', 1);

    expect($order->tickets_count)->toBe(1)->and((float) $order->total)->toBe(50.0)->and($order->payment_status)->toBe('at_entry');
    $t = $order->tickets->first();
    expect($t->owner_participant_id)->toBe($ana->id)->and($t->holder_participant_id)->toBe($ana->id)
        ->and($t->status)->toBe('valid')->and((float) $t->price)->toBe(50.0)
        ->and($t->qrPayload())->toBe('DXA:T:'.$t->uuid)
        ->and(Ticket::findByQrPayload($t->qrPayload())->id)->toBe($t->id)
        ->and(Ticket::findByQrPayload($t->uuid)->id)->toBe($t->id);
});

it('3 bilete: primul pe numele meu, celelalte în contul meu fără nume; telefonul cu cont mută biletul în contul lui', function () {
    $party = toParty();
    $ana = toUser();
    $bob = toUser('Bob Prieten', '0733222333');
    ParticipantRegistry::create('Fără Cont', '0744333444'); // creat la recepție, fără cont de aplicație

    $order = TicketOrders::place($ana, $party, 'Bilet', 3, ['0733222333', '0744333444']);
    [$t1, $t2, $t3] = $order->tickets->all();

    expect($t1->owner_participant_id)->toBe($ana->id)->and($t1->holder_participant_id)->toBe($ana->id)
        ->and($t2->owner_participant_id)->toBe($bob->id)->and($t2->holder_participant_id)->toBe($bob->id)
        ->and($t3->owner_participant_id)->toBe($ana->id)->and($t3->holder_participant_id)->toBeNull()->and($t3->holder_phone)->toBe('+40744333444');
});

it('telefoane goale = bilete fără nume în contul cumpărătorului; telefon nevalid, propriu sau dublat e refuzat', function () {
    $party = toParty();
    $ana = toUser();

    $order = TicketOrders::place($ana, $party, 'Bilet', 2, ['']);
    expect($order->tickets[1]->owner_participant_id)->toBe($ana->id)->and($order->tickets[1]->holder_participant_id)->toBeNull();

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 2, ['abc']))->toThrow(DomainException::class, 'nu este valid')
        ->and(fn () => TicketOrders::place($ana, $party, 'Bilet', 2, ['0722111222']))->toThrow(DomainException::class, 'deja pe numele tău')
        ->and(fn () => TicketOrders::place($ana, $party, 'Bilet', 3, ['0733000111', '0733000111']))->toThrow(DomainException::class, 'de două ori');
    expect(Order::count())->toBe(1);
});

it('nu se vinde dacă vânzarea online e oprită, petrecerea e ciornă, dezactivată sau încheiată', function () {
    $ana = toUser();

    expect(fn () => TicketOrders::place($ana, toParty(['online_sales' => false]), 'Bilet', 1))->toThrow(DomainException::class, 'se cumpără la intrare')
        ->and(fn () => TicketOrders::place($ana, toParty(['status' => 'draft']), 'Bilet', 1))->toThrow(DomainException::class, 'nu este disponibilă')
        ->and(fn () => TicketOrders::place($ana, toParty(['is_active' => false]), 'Bilet', 1))->toThrow(DomainException::class, 'nu este disponibilă');

    $past = toParty(['start_date' => '2026-09-01']);
    expect(fn () => TicketOrders::place($ana, $past, 'Bilet', 1))->toThrow(DomainException::class, 'încheiat');
    expect(Ticket::count())->toBe(0);
});

it('doar un participant cu cont poate cumpăra', function () {
    $party = toParty();
    $noAccount = ParticipantRegistry::create('Fără cont', '0755000111');

    expect(fn () => TicketOrders::place($noAccount, $party, 'Bilet', 1))->toThrow(DomainException::class, 'cont activ');
});

it('bilete per comandă: limita petrecerii; gol = oricâte (până la plafonul de siguranță)', function () {
    $ana = toUser();
    $limited = toParty(['max_tickets_per_order' => 3]);

    expect(fn () => TicketOrders::place($ana, $limited, 'Bilet', 4))->toThrow(DomainException::class, 'cel mult 3');
    expect(TicketOrders::place($ana, $limited, 'Bilet', 3)->tickets_count)->toBe(3);

    $free = toParty(['max_tickets_per_order' => null, 'name' => 'Fără limită']);
    expect(TicketOrders::maxPerOrder($free, 'Bilet'))->toBe(TicketOrders::SAFETY_MAX)
        ->and(TicketOrders::place($ana, $free, 'Bilet', 25)->tickets_count)->toBe(25);
    expect(fn () => TicketOrders::place($ana, $free, 'Bilet', 101))->toThrow(DomainException::class);
});

it('bilete la vânzare (total) și limita pe tip: epuizare, „mai sunt doar N”', function () {
    $ana = toUser();
    $party = toParty(['tickets_for_sale' => 5, 'ticket_types' => [
        ['name' => 'Full', 'price' => 80, 'limit' => 2, 'discounts' => [], 'qty_tiers' => []],
        ['name' => 'Party', 'price' => 50, 'limit' => null, 'discounts' => [], 'qty_tiers' => []],
    ]]);

    expect(TicketOrders::available($party, 'Full'))->toBe(2)->and(TicketOrders::available($party, 'Party'))->toBe(5);

    TicketOrders::place($ana, $party, 'Full', 2);
    expect(fn () => TicketOrders::place($ana, $party, 'Full', 1))->toThrow(DomainException::class, 'epuizate')
        ->and(TicketOrders::available($party, 'Party'))->toBe(3)
        ->and(fn () => TicketOrders::place($ana, $party, 'Party', 4))->toThrow(DomainException::class, 'Mai sunt doar 3');

    TicketOrders::place($ana, $party, 'Party', 3);
    expect(TicketOrders::available($party, 'Party'))->toBe(0)->and(TicketOrders::maxPerOrder($party, 'Party'))->toBe(0)
        ->and(fn () => TicketOrders::place($ana, $party, 'Party', 1))->toThrow(DomainException::class, 'epuizate');
    expect(Ticket::count())->toBe(5);
});

it('treptele „primele N bilete”: prețul crește după ce se vând; o comandă poate trece peste prag', function () {
    $ana = toUser();
    $party = toParty(['ticket_types' => [['name' => 'Bilet', 'price' => 60, 'discounts' => [], 'qty_tiers' => [
        ['label' => 'Primele 2', 'price' => 40, 'first' => 2],
        ['label' => 'Următoarele 2', 'price' => 50, 'first' => 4],
    ]]]]);
    $type = ['name' => 'Bilet', 'price' => 60, 'qty_tiers' => [['price' => 40, 'first' => 2], ['price' => 50, 'first' => 4]]];

    expect(TicketOrders::tierPrice($type, 0))->toBe(40.0)->and(TicketOrders::tierPrice($type, 2))->toBe(50.0)->and(TicketOrders::tierPrice($type, 4))->toBeNull();

    // 3 bilete: 40 + 40 + 50 (al treilea trece în treapta a doua)
    $order = TicketOrders::place($ana, $party, 'Bilet', 3);
    expect($order->tickets->map(fn ($t) => (float) $t->price)->all())->toBe([40.0, 40.0, 50.0])->and((float) $order->total)->toBe(130.0);

    // următorul: 50 (a doua treaptă), apoi 60 (baza)
    $next = TicketOrders::place($ana, $party, 'Bilet', 2);
    expect($next->tickets->map(fn ($t) => (float) $t->price)->all())->toBe([50.0, 60.0]);
});

it('treapta nu urcă peste prețul curent (reducere de dată mai ieftină)', function () {
    $ana = toUser();
    $party = toParty(['ticket_types' => [['name' => 'Bilet', 'price' => 60, 'discounts' => [['label' => 'Early', 'price' => 30, 'until' => '2026-10-02T00:00']], 'qty_tiers' => [
        ['label' => 'Primele 5', 'price' => 45, 'first' => 5],
    ]]]]);

    expect((float) TicketOrders::place($ana, $party, 'Bilet', 1)->total)->toBe(30.0); // early-bird 30 < treaptă 45
});

it('cod de reducere: se aplică per bilet, fiecare bilet cu reducere consumă o utilizare, respectă limitele', function () {
    $ana = toUser();
    $party = toParty();
    $code = toCode($party, ['max_uses' => 3]);

    $order = TicketOrders::place($ana, $party, 'Bilet', 2, [], 'promo10');
    expect((float) $order->subtotal)->toBe(100.0)->and((float) $order->discount_total)->toBe(10.0)->and((float) $order->total)->toBe(90.0)
        ->and($order->discount_code_id)->toBe($code->id)
        ->and($order->tickets->every(fn ($t) => $t->discount_code_id === $code->id && (float) $t->price === 45.0))->toBeTrue()
        ->and($code->fresh()->usesCount())->toBe(2);

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 2, [], 'PROMO10'))->toThrow(DomainException::class, 'doar pentru 1 bilet');
    expect(TicketOrders::place($ana, $party, 'Bilet', 1, [], 'PROMO10')->tickets_count)->toBe(1);
    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, [], 'PROMO10'))->toThrow(DomainException::class, 'maxim de ori');
});

it('codul cu limită per participant: o singură reducere pe titular cu nume, anonimii doar pe limita totală', function () {
    $ana = toUser();
    $party = toParty();
    toCode($party, ['max_uses_per_participant' => 1]);

    TicketOrders::place($ana, $party, 'Bilet', 1, [], 'PROMO10');
    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, [], 'PROMO10'))->toThrow(DomainException::class, 'deja acest cod');
});

it('cod inexistent, expirat sau care nu aduce reducere e refuzat și nu se creează comanda', function () {
    $ana = toUser();
    $party = toParty();
    toCode($party, ['code' => 'VECHI', 'valid_until' => '2026-09-30 00:00:00']);

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, [], 'NUEXISTA'))->toThrow(DomainException::class, 'nu există')
        ->and(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, [], 'VECHI'))->toThrow(DomainException::class, 'expirat');
    expect(Order::count())->toBe(0);

    $gratis = toParty(['name' => 'Gratis', 'ticket_types' => [['name' => 'Bilet', 'price' => 0, 'discounts' => [], 'qty_tiers' => []]]]);
    toCode($gratis, ['code' => 'PROMO10']);
    expect(fn () => TicketOrders::place($ana, $gratis, 'Bilet', 1, [], 'PROMO10'))->toThrow(DomainException::class, 'gratuit');
});

it('anularea unui bilet eliberează locul și utilizarea codului (bilet void nu se numără)', function () {
    $ana = toUser();
    $party = toParty(['tickets_for_sale' => 1]);
    $code = toCode($party, ['max_uses' => 1]);

    $order = TicketOrders::place($ana, $party, 'Bilet', 1, [], 'PROMO10');
    expect(TicketOrders::available($party, 'Bilet'))->toBe(0)->and($code->fresh()->usesCount())->toBe(1);

    $order->tickets->first()->update(['status' => 'void']);
    expect(TicketOrders::available($party, 'Bilet'))->toBe(1)->and($code->fresh()->usesCount())->toBe(0);
});

it('petrecerea gratuită: bilet de 0 lei', function () {
    $ana = toUser();
    $party = toParty(['is_free' => true, 'ticket_types' => []]);

    $order = TicketOrders::place($ana, $party, 'Intrare gratuită', 2);
    expect((float) $order->total)->toBe(0.0)->and($order->tickets)->toHaveCount(2);
});

it('pagina petrecerii: vizitatorul neconectat vede „Cumpără bilete” și dialogul de logare, nu pe cel de comandă', function () {
    $party = toParty();

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Cumpără bilete')->assertSee('Intră în cont ca să cumperi bilete')->assertDontSee('wire:click="buy"', false);
});

it('vizitatorul care alege Intră în cont e trimis la login și se întoarce la petrecere', function () {
    $party = toParty();

    Livewire::test(PartyShow::class, ['party' => $party])->call('goLogin')->assertRedirect(route('app.login'));
    expect(session('url.intended'))->toBe(route('app.party', $party));

    Livewire::test(PartyShow::class, ['party' => $party])->call('goRegister')->assertRedirect(route('app.register'));
});

it('utilizatorul conectat vede dialogul de comandă și cumpără prin Livewire; apoi biletele apar în cont cu QR', function () {
    $party = toParty(['max_tickets_per_order' => 5]);
    $ana = toUser();
    $this->actingAs($ana, 'participant');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('wire:click="buy"', false)->assertSee('Confirmă')->assertDontSee('Confirmă comanda')->assertDontSee('Intră în cont ca să cumperi bilete');

    Livewire::test(PartyShow::class, ['party' => $party])
        ->call('inc')->call('inc')->assertSet('qty', 3)
        ->set('phones', ['', ''])
        ->call('buy')->assertRedirect(route('app.tickets'));

    expect(Order::count())->toBe(1)->and(Ticket::query()->where('owner_participant_id', $ana->id)->count())->toBe(3);

    $t = Ticket::query()->first();
    $this->get('/bilete')->assertOk()->assertSee('Biletele mele')->assertSee('Petrecere bilete')->assertSee('data-tickets-carousel', false)->assertSee('data-ticket-qr="DXA:T:'.$t->uuid.'"', false)->assertSee('VALABIL');
});

it('cantitatea se limitează la maximul din comandă și cel disponibil; „epuizat” ascunde comanda', function () {
    $party = toParty(['max_tickets_per_order' => 2, 'tickets_for_sale' => 3]);
    $ana = toUser();
    $this->actingAs($ana, 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])->call('inc')->call('inc')->call('inc')->assertSet('qty', 2);

    TicketOrders::place($ana, $party, 'Bilet', 2);
    TicketOrders::place($ana, $party, 'Bilet', 1);
    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Biletele online au fost epuizate.')->assertDontSee('wire:click="buy"', false);
});

it('codul de reducere din dialog: aplicat și afișat în sumar; cod greșit dă eroare', function () {
    $party = toParty();
    toCode($party);
    $this->actingAs(toUser(), 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])
        ->set('code', 'gresit')->call('applyCode')->assertSet('appliedCode', '')->assertSet('codeError', 'Codul de reducere nu există la această petrecere.')
        ->set('code', ' promo10 ')->call('applyCode')->assertSet('appliedCode', 'PROMO10')->assertSet('codeError', '')
        ->assertSee('Reducere (PROMO10)')
        ->call('clearCode')->assertSet('appliedCode', '');
});

it('prețurile pe trepte apar pe pagina petrecerii, cu treapta activă', function () {
    $party = toParty(['ticket_types' => [['name' => 'Bilet', 'price' => 60, 'discounts' => [], 'qty_tiers' => [['label' => 'Primele 50', 'price' => 40, 'first' => 50]]]]]);

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Primele 50')->assertSee('mai sunt 50 bilete la acest preț')->assertSee('Preț normal');
});

it('prețurile reduse arată prețul de bază tăiat (pe pagină și în lista de tipuri de bilet)', function () {
    $party = toParty(['ticket_types' => [
        ['name' => 'Bilet', 'price' => 60, 'discounts' => [['label' => 'Early', 'price' => 45, 'until' => '2026-10-02']], 'qty_tiers' => [['label' => 'Primele 50', 'price' => 40, 'first' => 50]]],
        ['name' => 'VIP', 'price' => 100, 'discounts' => [], 'qty_tiers' => []],
    ]]);

    $rows = collect(PartyPublic::tickets($party))->keyBy('name');
    expect($rows['Bilet']['rows'][0]['was'])->toBe(60.0)             // treapta 40 lei: era 60
        ->and($rows['Bilet']['rows'][1]['was'])->toBe(60.0)          // oferta 45 lei: era 60
        ->and(collect($rows['Bilet']['rows'])->last()['was'])->toBeNull()   // prețul normal nu se taie
        ->and($rows['VIP']['rows'][0]['was'])->toBeNull();

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('<s class="pa-soft pa-was"', false);

    $user = toUser();
    $c = Livewire::actingAs($user, 'participant')->test(PartyShow::class, ['party' => $party]);
    $opts = collect($c->viewData('options'))->keyBy('name');
    expect($opts['Bilet']['price'])->toBe(40.0)->and($opts['Bilet']['was'])->toBe(60.0)->and($opts['VIP']['was'])->toBeNull();
});

it('biletele altui participant nu apar în contul meu', function () {
    $party = toParty();
    $ana = toUser();
    $bob = toUser('Bob Altul', '0733999888');
    TicketOrders::place($bob, $party, 'Bilet', 1);

    $this->actingAs($ana, 'participant')->get('/bilete')->assertOk()->assertSee('Nu ai bilete valabile')->assertDontSee('data-tickets-carousel', false);
    Livewire::actingAs($ana, 'participant')->test(Tickets::class)
        ->assertViewHas('valid', fn ($t) => $t->isEmpty())->assertViewHas('recent', fn ($t) => $t->isEmpty());
});
