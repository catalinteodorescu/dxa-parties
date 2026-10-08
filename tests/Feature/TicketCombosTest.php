<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\Announcements\Form;
use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Livewire\Participant\PartyShow;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\PartyPublic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 26). Combo-uri de bilete „N+M gratis”, per tip de bilet. */
function tcParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere combo', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [
            ['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [], 'combos' => [['buy' => 3, 'free' => 1]]],
            ['name' => 'VIP', 'price' => 80, 'discounts' => [], 'qty_tiers' => []],
        ],
    ], $o));
}

function tcUser(): Participant
{
    $p = ParticipantRegistry::create('Ana Combo', '0722111222');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

it('combo 3+1: 4 bilete reale, se plătesc 3, al 4-lea e gratuit, fără nume cerute', function () {
    $party = tcParty();
    $ana = tcUser();

    $order = TicketOrders::place($ana, $party, 'Bilet', 4, null, null, '3+1');

    expect((float) $order->total)->toBe(150.0)->and($order->tickets_count)->toBe(4);
    $free = $order->tickets->where('combo_free', true);
    expect($free)->toHaveCount(1)
        ->and((float) $free->first()->price)->toBe(0.0)->and($free->first()->valid_until)->toBeNull()->and($free->first()->status)->toBe('valid')
        ->and($free->first()->holder_participant_id)->toBeNull()->and($free->first()->owner_participant_id)->toBe($ana->id)
        ->and($order->tickets->pluck('combo_label')->unique()->all())->toBe(['3+1']);
});

it('două seturi = 8 bilete, 6 plătite; cantitatea trebuie să fie multiplu al setului', function () {
    $party = tcParty();
    $ana = tcUser();

    expect(fn () => TicketOrders::quote($party, 'Bilet', 5, null, [], null, '3+1'))->toThrow(DomainException::class, 'seturi de 4')
        ->and(fn () => TicketOrders::quote($party, 'Bilet', 4, null, [], null, '9+9'))->toThrow(DomainException::class, 'nu mai este disponibil')
        ->and(fn () => TicketOrders::quote($party, 'VIP', 4, null, [], null, '3+1'))->toThrow(DomainException::class, 'nu mai este disponibil');

    $order = TicketOrders::place($ana, $party, 'Bilet', 8, null, null, '3+1');
    expect((float) $order->total)->toBe(300.0)->and($order->tickets->where('combo_free', true))->toHaveCount(2);
});

it('biletul oferit nu primește cod și nu consumă locuri din treptele „primele N”; ocupă însă stoc', function () {
    $party = tcParty([
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'limit' => 5, 'discounts' => [], 'combos' => [['buy' => 3, 'free' => 1]],
            'qty_tiers' => [['first' => 3, 'price' => 30, 'label' => null]]]],
    ]);
    $ana = tcUser();

    $q = TicketOrders::quote($party, 'Bilet', 4, null, [], null, '3+1');
    expect(collect($q->lines)->pluck('price')->all())->toBe([30.0, 30.0, 30.0, 0.0]);

    TicketOrders::place($ana, $party, 'Bilet', 4, null, null, '3+1');
    expect(TicketOrders::tierSoldCount($party, 'Bilet'))->toBe(3)->and(TicketOrders::soldCount($party, 'Bilet'))->toBe(4)
        ->and(TicketOrders::available($party, 'Bilet'))->toBe(1);
    expect(fn () => TicketOrders::quote($party, 'Bilet', 4, null, [], null, '3+1'))->toThrow(DomainException::class, 'Mai sunt doar 1');
});

it('codul de reducere se aplică doar biletelor plătite din combo', function () {
    $party = tcParty();
    $party->discountCodes()->create(['code' => 'PROMO10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

    $q = TicketOrders::quote($party, 'Bilet', 4, 'PROMO10', [], null, '3+1');

    expect(collect($q->lines)->pluck('price')->all())->toBe([45.0, 45.0, 45.0, 0.0])->and($q->total)->toBe(135.0)->and($q->discount)->toBe(15.0);
});

it('în aplicație: alege combo, seturile se înmulțesc, cumpără fără telefoane', function () {
    $party = tcParty();
    $ana = tcUser();
    $this->actingAs($ana, 'participant');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Combo 3+1')->assertSee('Plătești 3, primești 4 bilete');

    Livewire::test(PartyShow::class, ['party' => $party])
        ->call('selectCombo', '3+1')->assertSet('combo', '3+1')->assertSee('Număr de seturi')->assertSee('gratis')
        ->call('inc')->assertSet('qty', 2)
        ->call('selectTicket', 'VIP')->assertSet('combo', '')
        ->call('selectTicket', 'Bilet')->call('selectCombo', '3+1')
        ->call('buy')->assertRedirect(route('app.tickets'));

    expect(Ticket::count())->toBe(4)->and(Ticket::where('combo_free', true)->count())->toBe(1);
});

it('combo-ul care nu încape în limita pe comandă nu se oferă', function () {
    $party = tcParty(['max_tickets_per_order' => 3]);
    $this->actingAs(tcUser(), 'participant');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertDontSee('Alege tipul de bilet');
});

it('informațiile publice listează combo-urile doar cu vânzare online', function () {
    $party = tcParty();
    $t = collect(PartyPublic::tickets($party));
    expect($t->firstWhere('name', 'Bilet')['combos'][0]['key'])->toBe('3+1')->and($t->firstWhere('name', 'VIP')['combos'])->toBe([]);

    $party->update(['online_sales' => false]);
    expect(collect(PartyPublic::tickets($party->fresh()))->firstWhere('name', 'Bilet')['combos'])->toBe([]);
});

it('formularul admin salvează combo-uri per tip, elimină dublurile și le poate copia pe toate tipurile', function () {
    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700123456', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');

    $c = Livewire::test(PartyForm::class)->set('name', 'Petrecere combo admin')
        ->set('ticket_types', [
            ['name' => 'Bilet', 'price' => '50', 'discounts' => [], 'qty_tiers' => [], 'combos' => [['buy' => '3', 'free' => '1'], ['buy' => '3', 'free' => '1']]],
            ['name' => 'VIP', 'price' => '80', 'discounts' => [], 'qty_tiers' => [], 'combos' => []],
        ])->call('save')->assertHasErrors('ticket_types.0.combos');

    $c->set('ticket_types.0.combos', [['buy' => '3', 'free' => '1'], ['buy' => '5', 'free' => '2'], ['buy' => '', 'free' => '']])
        ->call('copyCombosToAll', 0)
        ->call('save')->assertHasNoErrors();

    $p = Party::query()->where('name', 'Petrecere combo admin')->firstOrFail();
    expect($p->ticket_types[0]['combos'])->toBe([['buy' => 3, 'free' => 1], ['buy' => 5, 'free' => 2]])
        ->and($p->ticket_types[1]['combos'])->toBe([['buy' => 3, 'free' => 1], ['buy' => 5, 'free' => 2]]);

    Livewire::test(PartyForm::class, ['party' => $p])->assertSet('ticket_types.1.combos.1.buy', 5)->assertSee('Adaugă combo');
});

it('combo 5+2 cu „primele 4 la 20 lei”: se poate cumpăra, cu mențiune câte bilete mai sunt la preț special', function () {
    $party = tcParty(['ticket_types' => [['name' => 'Bilet', 'price' => 30, 'discounts' => [], 'combos' => [['buy' => 5, 'free' => 2]],
        'qty_tiers' => [['first' => 4, 'price' => 20, 'label' => null]]]]]);
    $ana = tcUser();
    $this->actingAs($ana, 'participant');

    $q = TicketOrders::quote($party, 'Bilet', 7, null, [], null, '5+2');
    expect(collect($q->lines)->pluck('price')->all())->toBe([20.0, 20.0, 20.0, 20.0, 30.0, 0.0, 0.0])->and($q->total)->toBe(110.0);

    $note = TicketOrders::comboNote($party, $party->ticket_types[0], 'Bilet', ['buy' => 5, 'free' => 2]);
    expect($note)->toContain('au mai rămas 4 bilete')->toContain('restul se plătesc la prețul curent');
    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('au mai rămas 4 bilete');

    Livewire::test(PartyShow::class, ['party' => $party])->call('selectCombo', '5+2')->assertSee('au mai rămas 4 bilete')->call('buy')->assertRedirect(route('app.tickets'));

    // După ce treapta s-a epuizat, nu mai există mențiune.
    expect(TicketOrders::comboNote($party, $party->ticket_types[0], 'Bilet', ['buy' => 5, 'free' => 2]))->toBeNull();
});

it('cod valabil pentru 10 bilete: la 11 bilete primele 10 au reducere, ultimul preț întreg', function () {
    $party = tcParty();
    $ana = tcUser();
    $party->discountCodes()->create(['code' => 'LIM10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'max_uses' => 10]);

    $q = TicketOrders::quote($party, 'VIP', 11, 'LIM10');
    expect($q->code_applied)->toBe(10)->and(collect($q->lines)->pluck('price')->all())->toBe(array_merge(array_fill(0, 10, 72.0), [80.0]));

    $order = TicketOrders::place($ana, $party, 'VIP', 11, 'LIM10');
    expect($order->tickets->where('discount_code_id', '!=', null))->toHaveCount(10);

    // Limita s-a epuizat: codul nu mai poate fi folosit.
    expect(fn () => TicketOrders::quote($party, 'VIP', 1, 'LIM10'))->toThrow(DomainException::class, 'numărul maxim');
});

it('formular nou: ștampile, carusel și vânzare online bifate implicit; descrierea e doar în română, iar cu bifa „engleză” are 2 limbi (RO și EN) cu steaguri și disclaimer', function () {
    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700987654', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');

    $c = Livewire::test(PartyForm::class)
        ->assertSet('loyalty_eligible', true)->assertSet('in_carousel', true)->assertSet('online_sales', true)
        ->assertSet('add_disclaimer', true)->assertSet('other_langs', false)
        ->set('name', 'Seara test')->set('start_date', '2026-10-03')->set('start_time', '21:00')->set('end_time', '03:00')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '50', 'discounts' => [], 'qty_tiers' => []]])
        ->call('generateDescription');

    // Implicit: doar română, fără marcaj de limbă, cu disclaimerul în română.
    $ro = $c->get('description');
    expect($ro)->toStartWith('Seara test')->toContain('sâmbătă, 03.10.2026')->toContain('INFORMAȚII IMPORTANTE')
        ->not->toContain('[RO]')->not->toContain('IMPORTANT INFORMATION')->not->toContain('Saturday');

    $c->set('other_langs', true)->call('generateDescription');
    $d = $c->get('description');
    expect($d)->toContain('🇷🇴 [RO] Seara test')->toContain('🇬🇧 [EN] Seara test')->not->toContain('[ES]')->not->toContain('🇪🇸')
        ->toContain('sâmbătă, 03.10.2026')->toContain('Saturday, 03.10.2026')->not->toContain('sábado')
        ->toContain('Bilet 50 lei')->toContain('Bilet 50 RON')
        ->toContain('INFORMAȚII IMPORTANTE')->toContain('IMPORTANT INFORMATION')->not->toContain('INFORMACIÓN IMPORTANTE')
        ->toContain('* Organizatorul și locația își rezervă dreptul de a refuza accesul sau de a solicita părăsirea locației.')
        ->toContain('* The organizer and the venue reserve the right to refuse entry or ask guests to leave the venue.')
        ->toContain('not responsible for any loss, disappearance, or damage to personal belongings.')
        ->and(substr_count($d, 'reserve the right to refuse entry'))->toBe(1);
    expect(mb_strlen($d))->toBeLessThan(5000);

    $c->set('add_disclaimer', false)->call('generateDescription');
    expect($c->get('description'))->not->toContain('IMPORTANT INFORMATION')->toContain('🇬🇧 [EN] Seara test');
});

it('aplicația: descrierea e restrânsă la 10 rânduri cu buton „Arată toată descrierea”', function () {
    $party = tcParty(['description' => implode("\n", array_map(fn ($i) => 'Rând '.$i, range(1, 30)))]);

    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('pa-clamp10', false)->assertSee('Arată toată descrierea')->assertSee('Rând 30');
});

it('biletele „valabile” ale unei petreceri încheiate ies din carusel și apar ca expirate', function () {
    $ana = tcUser();
    $old = tcParty(['name' => 'Seara veche', 'start_date' => '2026-09-20']);
    $old->update(['ends_at' => '2026-09-21 03:00:00']);
    $new = tcParty(['name' => 'Seara viitoare']);
    TicketOrders::place($ana, $new, 'VIP', 1);
    TicketOrders::place($ana, $new, 'Bilet', 1)->tickets->first()->update(['party_id' => $old->id]);   // bilet rămas „valabil” la o petrecere încheiată

    $html = $this->actingAs($ana, 'participant')->get('/bilete')->assertOk()->assertSee('Seara viitoare')->assertSee('expirat')->getContent();
    expect(substr_count($html, 'data-ticket-qr='))->toBe(1);   // doar biletul petrecerii viitoare are QR în carusel
});

it('prețul din carusel/liste: „de la” cel mai mic preț cumpărabil acum (cu treapta „primele N”), sărind tipurile epuizate', function () {
    $party = tcParty(['ticket_types' => [
        ['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [['first' => 5, 'price' => 20, 'label' => null]]],
        ['name' => 'VIP', 'price' => 80, 'limit' => 1, 'discounts' => [], 'qty_tiers' => []],
    ]]);
    expect(PartyPublic::priceLabel($party))->toBe('de la 20 lei');

    $ana = tcUser();
    TicketOrders::place($ana, $party, 'Bilet', 5);   // treapta de 20 lei s-a epuizat
    expect(PartyPublic::priceLabel($party))->toBe('de la 50 lei');

    TicketOrders::place($ana, $party, 'VIP', 1);
    $single = tcParty(['ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]]]);
    expect(PartyPublic::priceLabel($single))->toBe('50 lei');   // un singur tip: fără „de la”
});

it('după comandă, mesajul spune că toate biletele sunt la tine și că cele libere se pot trimite', function () {
    $party = tcParty();
    $ana = tcUser();
    $bob = ParticipantRegistry::create('Bob Prieten', '0733222333');
    $bob->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    $this->actingAs($ana, 'participant');

    Livewire::test(PartyShow::class, ['party' => $party])
        ->set('qty', 3)->call('buy')->assertRedirect(route('app.tickets'));

    expect(session('status'))->toContain('Cele 3 bilete')->toContain('le poți trimite altcuiva');
});

it('biletul trimis altui cont iese din carusel și rămâne la cumpărător doar ca istoric („Trimise de mine”), fără QR', function () {
    $party = tcParty();
    $ana = tcUser();
    $bob = ParticipantRegistry::create('Bob Prieten', '0733222333');
    $bob->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    $order = TicketOrders::place($ana, $party, 'Bilet', 2);
    TicketTransfers::send($order->tickets[1]->id, $ana, '0733222333', new class implements SmsSender
    {
        public function send(string $phone, string $message): void {}
    });

    $html = $this->actingAs($ana, 'participant')->get('/bilete')->assertOk()->assertSee('Trimise de mine')->assertSee('trimis lui Bob Prieten')->assertSee('+40733222333')->getContent();
    expect(substr_count($html, 'data-ticket-qr='))->toBe(1);

    $htmlBob = $this->actingAs($bob, 'participant')->get('/bilete')->assertOk()->getContent();
    expect(substr_count($htmlBob, 'data-ticket-qr='))->toBe(1)->and($htmlBob)->not->toContain('Trimise de mine')->and($htmlBob)->toContain('data-send-ticket');
});

it('anunț: pagina separată arată textul întreg; lista și caruselul arată data și o previzualizare; 404 când nu e vizibil', function () {
    $long = str_repeat('Text lung de anunț. ', 80);
    $a = Announcement::create(['title' => 'Anunț mare', 'body' => $long, 'url' => 'https://exemplu.ro', 'url_label' => 'Detalii', 'audience' => 'all', 'in_list' => true, 'in_carousel' => true, 'is_active' => true, 'status' => 'published', 'starts_at' => '2026-10-02 18:30:00']);
    $draft = Announcement::create(['title' => 'Ciornă', 'audience' => 'all', 'in_list' => true, 'is_active' => true, 'status' => 'draft']);
    $auth = Announcement::create(['title' => 'Doar cu cont', 'audience' => 'auth', 'in_list' => true, 'is_active' => true, 'status' => 'published']);

    $this->get('/anunturi')->assertOk()->assertSee('Anunț mare')->assertSee('2 oct. 2026, 18:30')->assertSee('/anunturi/'.$a->id, false);
    $this->get('/anunturi/'.$a->id)->assertOk()->assertSee('Anunț mare')->assertSee('2 oct. 2026, 18:30')->assertSee(trim($long))->assertSee('Detalii')->assertSee('https://exemplu.ro', false);
    $this->get('/anunturi/'.$draft->id)->assertNotFound();
    $this->get('/anunturi/'.$auth->id)->assertNotFound();
    $this->actingAs(tcUser(), 'participant')->get('/anunturi/'.$auth->id)->assertOk();
});

it('admin anunț: textul poate avea mii de caractere (limita 15.000) și arată contorul', function () {
    $this->actingAs(Admin::create(['name' => 'Admin', 'phone' => '+40700555666', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');
    $ok = str_repeat('ă', 12000);

    Livewire::test(Form::class)->set('title', 'Lung')->set('body', $ok)->call('save')->assertHasNoErrors();
    expect(Announcement::where('title', 'Lung')->first()->body)->toBe($ok);

    Livewire::test(Form::class)->set('title', 'Prea lung')->set('body', str_repeat('x', 15001))->call('save')->assertHasErrors('body');
    Livewire::test(Form::class)->assertSee('/ 15.000 caractere');
});

it('contul meu: Telefonul stă imediat sub cardul de fidelitate; „de la” apare și când un tip nu are acum preț valabil', function () {
    $ana = tcUser();
    $this->actingAs($ana, 'participant')->get('/cont')->assertOk()->assertSeeInOrder(['Telefon', 'Intrările mele']);

    $party = tcParty(['ticket_types' => [
        ['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []],
        ['name' => 'Early', 'price' => null, 'discounts' => [['label' => 'Early', 'price' => 30, 'until' => '2026-09-01T23:59']], 'qty_tiers' => []],
    ]]);
    expect(PartyPublic::priceLabel($party))->toBe('de la 50 lei');
});
