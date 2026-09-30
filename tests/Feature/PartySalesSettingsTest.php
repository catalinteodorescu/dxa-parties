<?php

use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Models\Admin;
use App\Models\Party;
use App\Services\EntryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 13). Setările de vânzare bilete și capacitate ale petrecerii. */
function ssAdmin(): Admin
{
    return Admin::create(['name' => 'Admin', 'phone' => '+40700'.random_int(100000, 999999), 'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass']);
}

function ssParty(array $o = []): Party
{
    return Party::create(array_merge([
        'name' => 'Petrecere setări', 'kind' => 'basic', 'start_date' => now()->toDateString(), 'start_time' => '00:01', 'end_time' => '23:59',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
    ], $o));
}

function ssEnter(Party $party, int $n = 1): void
{
    EntryRecorder::record($party, 'Bilet', $n, [['method' => 'cash', 'amount' => 30 * $n]], adminId: ssAdmin()->id);
}

it('o petrecere nouă are vânzarea online oprită și nicio limită (bilete per comandă, la vânzare, participanți)', function () {
    $party = ssParty()->fresh();

    expect($party->online_sales)->toBeFalse()->and($party->max_tickets_per_order)->toBeNull()
        ->and($party->tickets_for_sale)->toBeNull()->and($party->max_participants)->toBeNull()
        ->and($party->capacityStatus())->toBeNull();
});

it('formularul salvează și reîncarcă setările de vânzare, limita și treptele pe tip de bilet', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)
        ->set('name', 'Petrecere cu vânzare')
        ->set('online_sales', true)->set('max_tickets_per_order', '6')->set('tickets_for_sale', '200')->set('max_participants', '250')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '60', 'limit' => '150', 'discounts' => [], 'qty_tiers' => [
            ['label' => 'Primele 100', 'price' => '50', 'first' => '100'],
            ['label' => 'Primele 50', 'price' => '40', 'first' => '50'],
        ]]])
        ->call('save')->assertHasNoErrors();

    $party = Party::query()->where('name', 'Petrecere cu vânzare')->firstOrFail();
    expect($party->online_sales)->toBeTrue()->and($party->max_tickets_per_order)->toBe(6)
        ->and($party->tickets_for_sale)->toBe(200)->and($party->max_participants)->toBe(250)
        ->and($party->ticket_types[0]['limit'])->toBe(150)
        ->and(array_column($party->ticket_types[0]['qty_tiers'], 'first'))->toBe([50, 100]) // ordonate crescător
        ->and($party->ticket_types[0]['qty_tiers'][0]['price'])->toEqual(40);

    Livewire::test(PartyForm::class, ['party' => $party])
        ->assertSet('online_sales', true)->assertSet('max_tickets_per_order', '6')
        ->assertSet('tickets_for_sale', '200')->assertSet('max_participants', '250')
        ->assertSet('ticket_types.0.limit', 150);
});

it('câmpurile goale înseamnă nelimitat / fără prag', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)->set('name', 'Fără praguri')->set('tickets_for_sale', '')->set('max_participants', '')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '30', 'limit' => '', 'discounts' => [], 'qty_tiers' => []]])
        ->call('save')->assertHasNoErrors();

    $party = Party::query()->where('name', 'Fără praguri')->firstOrFail();
    expect($party->tickets_for_sale)->toBeNull()->and($party->max_participants)->toBeNull()->and($party->ticket_types[0]['limit'])->toBeNull();
});

it('valori invalide sunt refuzate', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)->set('name', 'Invalid')->set('max_tickets_per_order', '0')->call('save')->assertHasErrors('max_tickets_per_order');
    Livewire::test(PartyForm::class)->set('name', 'Invalid')->set('max_tickets_per_order', '-1')->call('save')->assertHasErrors('max_tickets_per_order');
    Livewire::test(PartyForm::class)->set('name', 'Invalid')->set('max_participants', '-5')->call('save')->assertHasErrors('max_participants');
    Livewire::test(PartyForm::class)->set('name', 'Invalid')->set('tickets_for_sale', 'abc')->call('save')->assertHasErrors('tickets_for_sale');
    expect(Party::query()->where('name', 'Invalid')->count())->toBe(0);
});

it('două trepte cu același număr de bilete sunt refuzate', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)->set('name', 'Trepte duble')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '60', 'limit' => '', 'discounts' => [], 'qty_tiers' => [
            ['label' => 'A', 'price' => '40', 'first' => '50'], ['label' => 'B', 'price' => '45', 'first' => '50'],
        ]]])
        ->call('save')->assertHasErrors('ticket_types.0.qty_tiers');
    expect(Party::query()->where('name', 'Trepte duble')->count())->toBe(0);
});

it('formularul afișează secțiunile în ordinea Preț → Coduri de reducere → Vânzare bilete → Plată', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)->assertSeeInOrder(['Adaugă tip de bilet', 'Coduri de reducere', 'Vânzare bilete și capacitate', 'Număr maxim de participanți', 'Modalități de plată']);
});

it('capacityStatus: atins la prag, depășit peste, ignoră intrările anulate; nu blochează intrarea', function () {
    $party = ssParty(['max_participants' => 3]);

    ssEnter($party, 2);
    expect($party->capacityStatus()->reached)->toBeFalse()->and($party->capacityStatus()->count)->toBe(2);

    ssEnter($party, 1);
    expect($party->capacityStatus()->reached)->toBeTrue()->and($party->capacityStatus()->over)->toBeFalse();

    ssEnter($party, 1); // peste prag: NU se blochează
    expect($party->capacityStatus()->over)->toBeTrue()->and($party->capacityStatus()->count)->toBe(4);
});

it('atenționarea de capacitate apare la Recepție doar când pragul e atins', function () {
    $admin = ssAdmin();
    $this->actingAs($admin, 'admin');
    $party = ssParty(['max_participants' => 2]);

    ssEnter($party, 1);
    $this->get(route('admin.reception.create'))->assertOk()->assertDontSee('Capacitatea a fost');

    ssEnter($party, 1);
    $this->get(route('admin.reception.create'))->assertOk()->assertSee('Capacitatea a fost atinsă: 2 intrați din maximum 2 participanți.');

    ssEnter($party, 1);
    $this->get(route('admin.reception.create'))->assertSee('Capacitatea a fost depășită');
});

it('petrecerea fără prag nu afișează nicio atenționare', function () {
    $this->actingAs(ssAdmin(), 'admin');
    $party = ssParty();
    ssEnter($party, 5);

    $this->get(route('admin.reception.create'))->assertOk()->assertDontSee('Capacitatea a fost');
});

it('pagina publică a petrecerii spune „se cumpără la intrare” când vânzarea online e oprită', function () {
    $party = ssParty();
    $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Biletele se cumpără la intrare.');

    $party->update(['online_sales' => true]);
    $this->get('/petreceri/'.$party->id)->assertOk()->assertDontSee('Biletele se cumpără la intrare.')->assertSee('Cumpără bilete');
});

it('bilete per comandă: gol = oricâte (null), iar valori peste 20 sunt acceptate', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)->set('name', 'Oricâte')->set('max_tickets_per_order', '')->call('save')->assertHasNoErrors();
    Livewire::test(PartyForm::class)->set('name', 'Multe')->set('max_tickets_per_order', '50')->call('save')->assertHasNoErrors();

    expect(Party::query()->where('name', 'Oricâte')->firstOrFail()->max_tickets_per_order)->toBeNull()
        ->and(Party::query()->where('name', 'Multe')->firstOrFail()->max_tickets_per_order)->toBe(50);

    Livewire::test(PartyForm::class)->assertSet('max_tickets_per_order', '');
});

it('Invitați și Stiluri muzică sunt după Contact, înaintea Linkurilor; textul din secțiune nu are typo', function () {
    $this->actingAs(ssAdmin(), 'admin');

    Livewire::test(PartyForm::class)
        ->assertSeeInOrder(['Modalități de plată', 'Contact', 'Invitați', 'Stiluri muzică', 'Linkuri'])
        ->assertSee('se setează la „Preț”', false)
        ->assertDontSee('se setă la');
});
