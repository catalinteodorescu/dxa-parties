<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Promoters\Index as PromotersPage;
use App\Livewire\Admin\Reception\Index as ReceptionIndex;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Promoter;
use App\Models\Ticket;
use App\Services\DiscountCodeStats;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (Coduri de reducere - statistici, promotori). Scenariu: petrecerea veche A (19.09, fără coduri) și B (3.10, cu coduri).
 * Promotori Ana (ANA10) și Bob (BOB5). Ionel a fost la A => „revenit" la B; Maria și Dan sunt „noi".
 */
function dsAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Stat',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function dsParty(string $name, string $date): Party
{
    return Party::create([
        'name' => $name,
        'kind' => 'basic',
        'start_date' => $date,
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => ['cash', 'card'],
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => []]],
    ]);
}

function dsEnter(Party $party, ?string $code, string $at, array $participants = [], int $count = 1)
{
    return EntryRecorder::record(
        $party, 'Bilet', $count, [['method' => 'cash', 'amount' => 50 * $count]],
        at: Carbon::parse($at), enforceState: false, participants: $participants,
    );
}

/** Bilet(e) online: cumpărător cu cont; $holderId = deținătorul cu nume al primului bilet (null = biletele rămân anonime). */
function dsBuy(Party $party, ?string $code, string $at, ?int $holderId = null, int $count = 1): Ticket
{
    static $n = 0;
    $n++;
    $buyer = $holderId ? Participant::find($holderId) : ParticipantRegistry::create('Cumpărător '.$n, '07300'.str_pad((string) $n, 5, '0', STR_PAD_LEFT));
    $buyer->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    $order = TicketOrders::place($buyer, $party, 'Bilet', $count, $code, Carbon::parse($at));
    $order->tickets()->update(['created_at' => Carbon::parse($at)]);
    if (! $holderId) {
        $order->tickets()->update(['holder_participant_id' => null]);
    }

    return $order->tickets->first();
}

/** @return array{a: Party, b: Party, ana: Promoter, bob: Promoter, ionel: mixed, maria: mixed, dan: mixed} */
function dsScenario(): array
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $admin = dsAdmin();
    $a = dsParty('Petrecere A', '2026-09-19');
    $b = dsParty('Petrecere B', '2026-10-03');
    $b->update(['online_sales' => true]);

    $ana = Promoter::create(['name' => 'Ana']);
    $bob = Promoter::create(['name' => 'Bob']);
    PartyDiscountCode::create(['party_id' => $b->id, 'code' => 'ANA10', 'promoter_id' => $ana->id, 'type' => 'percent', 'value' => 10, 'max_uses' => 20, 'max_uses_per_participant' => null]);
    PartyDiscountCode::create(['party_id' => $b->id, 'code' => 'BOB5', 'promoter_id' => $bob->id, 'type' => 'amount', 'value' => 5, 'max_uses_per_participant' => null]);
    PartyDiscountCode::create(['party_id' => $b->id, 'code' => 'NEFOLOSIT', 'type' => 'percent', 'value' => 50, 'max_uses_per_participant' => null]);

    $ionel = ParticipantRegistry::create('Ionel Vechi', '0722000001', $admin->id);
    $maria = ParticipantRegistry::create('Maria Nouă', '0722000002', $admin->id);
    $dan = ParticipantRegistry::create('Dan Nou', '0722000003', $admin->id);

    dsEnter($a, null, '2026-09-19 22:00', [$ionel->id]);                 // Ionel a mai fost (fără cod)

    dsBuy($b, 'ANA10', '2026-09-28 10:00', $ionel->id);                  // Ana: revenit, 45
    dsBuy($b, 'ANA10', '2026-09-28 11:00', $maria->id);                  // Ana: nou, 45
    dsBuy($b, 'ANA10', '2026-09-29 09:00', null, 2);                     // Ana: 2 bilete anonime, 2 x 45
    dsBuy($b, 'BOB5', '2026-09-29 10:00', $dan->id);                     // Bob: nou, 45
    dsBuy($b, null, '2026-09-30 10:00');                                 // online fara cod (conteaza doar la total)
    dsEnter($b, null, '2026-09-30 11:00');                               // intrare la Recepție (nu intră în stat)

    return compact('a', 'b', 'ana', 'bob', 'ionel', 'maria', 'dan');
}

it('calculeaza statisticile pe petrecere: totaluri, pe promotor, pe cod, noi vs revenit, anonime', function () {
    ['b' => $b, 'ana' => $ana, 'bob' => $bob] = dsScenario();

    $s = DiscountCodeStats::forParty($b);
    $t = $s->totals;

    expect($s->has_data)->toBeTrue()
        ->and($t->tickets)->toBe(5)                 // 1+1+2+1
        ->and($t->participants)->toBe(3)            // Ionel, Maria, Dan
        ->and($t->new)->toBe(2)                     // Maria, Dan
        ->and($t->returning)->toBe(1)               // Ionel
        ->and($t->anonymous)->toBe(2)
        ->and($t->discount)->toBe(25.0)
        ->and($t->revenue)->toBe(225.0)
        ->and($t->party_entries)->toBe(6)           // 5 cu cod + 1 online fara cod (intrarile de la Receptie nu conteaza)
        ->and($t->share_pct)->toBe(round(5 / 6 * 100, 1));

    // Ana: 4 bilete x 5 lei reducere (10% din 50) = 20; Bob: 1 x 5.
    $anaRow = $s->promoters->firstWhere('promoter_id', $ana->id);
    $bobRow = $s->promoters->firstWhere('promoter_id', $bob->id);
    expect($anaRow->tickets)->toBe(4)->and($anaRow->participants)->toBe(2)->and($anaRow->new)->toBe(1)->and($anaRow->returning)->toBe(1)
        ->and($anaRow->anonymous)->toBe(2)->and($anaRow->discount)->toBe(20.0)->and($anaRow->revenue)->toBe(180.0)
        ->and($bobRow->tickets)->toBe(1)->and($bobRow->participants)->toBe(1)->and($bobRow->new)->toBe(1)->and($bobRow->discount)->toBe(5.0)
        ->and($s->promoters->first()->promoter)->toBe('Ana');   // cei mai multi participanti

    // Pe cod: include si codul nefolosit (0), sortat dupa bilete.
    expect($s->codes->pluck('code')->all())->toBe(['ANA10', 'BOB5', 'NEFOLOSIT'])
        ->and($s->codes->firstWhere('code', 'ANA10')->max_uses)->toBe(20)
        ->and($s->codes->firstWhere('code', 'NEFOLOSIT')->tickets)->toBe(0);

    // Pe zile.
    expect($s->daily->pluck('day')->all())->toBe(['2026-09-28', '2026-09-29'])
        ->and($s->daily->pluck('tickets')->all())->toBe([2, 3]);
});

it('un bilet anulat (void) iese din statistici; intrarile de la Receptie nu conteaza', function () {
    ['b' => $b, 'maria' => $maria] = dsScenario();

    Ticket::query()->where('holder_participant_id', $maria->id)->where('party_id', $b->id)->update(['status' => 'void']);

    $t = DiscountCodeStats::forParty($b)->totals;
    expect($t->tickets)->toBe(4)->and($t->participants)->toBe(2)->and($t->new)->toBe(1);
});

it('clasamentul intre petreceri aduna codurile aceluiasi promotor si tine cont de perioada', function () {
    ['a' => $a, 'b' => $b, 'ana' => $ana, 'bob' => $bob] = dsScenario();

    // Ana are cod si la A (mai veche): participant nou la A.
    $admin = Admin::first();
    $a->update(['online_sales' => true]);
    $codeA = PartyDiscountCode::create(['party_id' => $a->id, 'code' => 'ANAA', 'promoter_id' => $ana->id, 'type' => 'amount', 'value' => 10, 'max_uses_per_participant' => null]);
    $eva = ParticipantRegistry::create('Eva Veche', '0722000009', $admin->id);
    Carbon::setTestNow(Carbon::parse('2026-09-19 12:00:00'));   // A încă nu s-a încheiat la cumpărare
    dsBuy($a, 'ANAA', '2026-09-19 23:00', $eva->id);
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));   // (dupa Ionel, aceeasi sesiune dar alt participant)

    $all = DiscountCodeStats::overall();
    $anaRow = $all->promoters->firstWhere('promoter_id', $ana->id);
    expect($all->parties)->toBe(2)
        ->and($anaRow->parties)->toBe(2)
        ->and($anaRow->tickets)->toBe(5)
        ->and($anaRow->participants)->toBe(3)            // Ionel, Maria, Eva
        ->and($anaRow->new)->toBe(2)                     // Maria (la B) + Eva (la A); Ionel e revenit la B
        ->and($all->codes->first()->code)->toBe('ANA10');

    // Doar petrecerea B: fara Eva.
    $onlyB = DiscountCodeStats::overall([$b->id]);
    expect($onlyB->promoters->firstWhere('promoter_id', $ana->id)->tickets)->toBe(4)
        ->and($onlyB->promoters->pluck('promoter')->all())->toBe(['Ana', 'Bob']);
});

it('coduri fara promotor apar in grupul „Fara promotor"', function () {
    ['b' => $b] = dsScenario();
    $code = PartyDiscountCode::create(['party_id' => $b->id, 'code' => 'FARA', 'type' => 'amount', 'value' => 5, 'max_uses_per_participant' => null]);
    dsBuy($b, 'FARA', '2026-09-30 11:00');

    $none = DiscountCodeStats::forParty($b)->promoters->firstWhere('promoter_id', null);
    expect($none->promoter)->toBe('Fără promotor')->and($none->tickets)->toBe(1)->and($none->codes)->toBe(['FARA']);
});

it('pagina de statistici a petrecerii arata sectiunea „Coduri de reducere"', function () {
    $this->actingAs(dsAdmin(), 'admin');
    ['b' => $b, 'a' => $a] = dsScenario();

    Livewire::test(PartyStatsPage::class, ['party' => $b])
        ->assertSee('Coduri de reducere')->assertSee('Pe promotor')->assertSee('Pe cod')
        ->assertSeeInOrder(['Ana', 'ANA10', '2 participanți', 'Bob'])
        ->assertSee('NEFOLOSIT')->assertSee('Utilizări pe zile')->assertSee('2 noi · 1 reveniți');

    // Petrecere fara coduri: sectiunea lipseste.
    Livewire::test(PartyStatsPage::class, ['party' => $a])->assertDontSee('Coduri de reducere');
});

it('formularul petrecerii: popup „+" adauga promotorul si il alege in rand; numele existent se reutilizeaza', function () {
    $this->actingAs(dsAdmin(), 'admin');
    $existing = Promoter::create(['name' => 'Ana Popescu']);

    $c = Livewire::test(PartyForm::class)->call('addDiscountCode')->call('addDiscountCode')
        ->assertSet('promoterModalRow', null)->assertDontSee('Promotor nou</h3>', false);

    // Nume gol: eroare, popup ramane deschis.
    $c->call('openPromoterModal', 1)->assertSet('promoterModalRow', 1)->assertSee('Promotor nou')
        ->call('savePromoter')->assertHasErrors(['newPromoterName'])->assertSet('promoterModalRow', 1);

    // Promotor nou: creat, ales in randul 1, popup inchis.
    $c->set('newPromoterName', 'Vlad Ionescu')->set('newPromoterPhone', '0722 111 222')->call('savePromoter')
        ->assertHasNoErrors()->assertSet('promoterModalRow', null);
    $vlad = Promoter::query()->where('name', 'Vlad Ionescu')->firstOrFail();
    expect($vlad->phone)->toBe('0722 111 222')->and($vlad->is_active)->toBeTrue();
    $c->assertSet('discount_codes.1.promoter_id', (string) $vlad->id)->assertSet('discount_codes.0.promoter_id', '')
        ->assertSee('Vlad Ionescu');

    // Acelasi nume (litere diferite): nu se dubleaza, se alege cel existent, chiar daca era inactiv.
    $existing->update(['is_active' => false]);
    $c->call('openPromoterModal', 0)->set('newPromoterName', '  ana POPESCU ')->call('savePromoter');
    expect(Promoter::query()->count())->toBe(2)->and($existing->fresh()->is_active)->toBeTrue();
    $c->assertSet('discount_codes.0.promoter_id', (string) $existing->id);
});

it('promotor inactiv ramane afisat in randul care il foloseste', function () {
    $this->actingAs(dsAdmin(), 'admin');
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $party = dsParty('Cu inactiv', '2026-10-03');
    $off = Promoter::create(['name' => 'Fost Promotor', 'is_active' => false]);
    PartyDiscountCode::create(['party_id' => $party->id, 'code' => 'FOST', 'promoter_id' => $off->id, 'type' => 'percent', 'value' => 10]);

    Livewire::test(PartyForm::class, ['party' => $party])->assertSee('Fost Promotor (inactiv)');
});

it('pagina Promotori: clasament, adaugare / editare / dezactivare / stergere doar fara coduri', function () {
    $this->actingAs(dsAdmin(), 'admin');
    ['ana' => $ana] = dsScenario();

    $c = Livewire::test(PromotersPage::class)
        ->assertSee('Clasament promotori')->assertSee('Ana')->assertSee('Bob')->assertSee('2 noi')
        ->assertSee('Cele mai folosite coduri')->assertSee('ANA10');
    $this->get(route('admin.promoters.index'))->assertOk()->assertSee('Promotori');

    // Adaugare + duplicat.
    $c->call('openForm', 0)->assertSet('editing', 0)->set('name', 'Carla')->call('save')->assertHasNoErrors()->assertSet('editing', null);
    expect(Promoter::query()->where('name', 'Carla')->exists())->toBeTrue();
    $c->call('openForm', 0)->set('name', 'carla')->call('save')->assertHasErrors(['name']);

    // Editare (aceeasi persoana, alt nume).
    $carla = Promoter::query()->where('name', 'Carla')->first();
    $c->call('openForm', $carla->id)->assertSet('name', 'Carla')->set('name', 'Carla M.')->set('note', 'liceu')->call('save')->assertHasNoErrors();
    expect($carla->fresh()->name)->toBe('Carla M.')->and($carla->fresh()->note)->toBe('liceu');

    // Dezactivare / activare.
    $c->call('toggleActive', $carla->id);
    expect($carla->fresh()->is_active)->toBeFalse();
    $c->call('toggleActive', $carla->id);
    expect($carla->fresh()->is_active)->toBeTrue();

    // Stergere: promotorul cu coduri nu se sterge; cel fara coduri da (dupa confirmare).
    $c->call('askDelete', $ana->id)->assertSet('deleting', $ana->id)->call('delete')->assertSet('deleting', null);
    expect(Promoter::query()->whereKey($ana->id)->exists())->toBeTrue();
    $c->call('askDelete', $carla->id)->call('delete');
    expect(Promoter::query()->whereKey($carla->id)->exists())->toBeFalse();

    // Perioada: ultimele 5 petreceri cu coduri.
    $c->set('period', 'last5')->assertSee('Clasament promotori');
});

it('coloana „Cod" apare in Tranzactii (admin) pentru intrarile cu cod', function () {
    $this->actingAs(dsAdmin(), 'admin');
    ['b' => $b] = dsScenario();
    $code = PartyDiscountCode::create(['party_id' => $b->id, 'code' => 'RECEPT', 'type' => 'amount', 'value' => 5, 'max_uses_per_participant' => null]);
    EntryRecorder::record($b, 'Bilet', 1, [['method' => 'cash', 'amount' => 45]], at: Carbon::parse('2026-09-30 12:00'), enforceState: false, discountCode: 'RECEPT');

    Livewire::test(ReceptionIndex::class)->set('party', (string) $b->id)->assertSee('RECEPT');
});

it('dashboard: reduceri pe 30 de zile, petrecerea curentă, top promotori, noi vs reveniți', function () {
    ['b' => $b] = dsScenario();

    $d = DiscountCodeStats::dashboard();

    expect($d->period->tickets)->toBe(5)
        ->and($d->period->discount)->toBe(25.0)
        ->and($d->period->new)->toBe(2)->and($d->period->returning)->toBe(1)->and($d->period->anonymous)->toBe(2)
        ->and($d->period_entries)->toBe(6)                       // 5 cu cod + 1 online fără cod (doar bilete online)
        ->and($d->share_pct)->toBe(round(5 / 6 * 100, 1))
        ->and($d->party->id)->toBe($b->id)                       // A s-a încheiat; B e următoarea
        ->and($d->top_code->code)->toBe('ANA10')
        ->and($d->promoters->pluck('promoter')->all())->toBe(['Ana', 'Bob']);

    // Fereastra mai scurtă (doar ultima zi): nicio intrare cu cod, dar top promotorii rămân „în total”.
    $short = DiscountCodeStats::dashboard(null, 1);
    expect($short->period->tickets)->toBe(0)->and($short->share_pct)->toBeNull()->and($short->promoters)->toHaveCount(2);
});

it('dashboard fără date: nicio petrecere, niciun promotor, fără erori', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    $d = DiscountCodeStats::dashboard();
    expect($d->party)->toBeNull()->and($d->party_stats)->toBeNull()->and($d->top_code)->toBeNull()
        ->and($d->period->tickets)->toBe(0)->and($d->promoters)->toBeEmpty();

    $this->actingAs(dsAdmin(), 'admin');
    Livewire::test(Dashboard::class)->assertSee('Coduri de reducere')->assertSee('niciun bilet cu cod');
});

it('dashboard: cardurile „Coduri de reducere” arată cifrele din scenariu', function () {
    dsScenario();
    $this->actingAs(dsAdmin(), 'admin');

    Livewire::test(Dashboard::class)
        ->assertSee('Coduri de reducere')
        ->assertSee('25,00 lei')            // reduceri acordate (30 zile)
        ->assertSee('83,3% din biletele online')
        ->assertSee('Bilete cu cod · Petrecere B')
        ->assertSee('top: ANA10 (4)')
        ->assertSee('Top promotor')->assertSee('Ana')->assertSee('2. Bob')
        ->assertSee('2 noi')->assertSee('1 reveniți · 2 anonime');
});
