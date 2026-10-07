<?php

use App\Contracts\SmsSender;
use App\Livewire\Participant\PartyShow;
use App\Livewire\Participant\Tickets;
use App\Livewire\Reception\Recent;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Ticket;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 54). Mesaje de limită, „Încarcă mai multe", trimiterea biletului propriu, intrare cu 2 bilete pe același nume, preț în Tranzacții, carusel. */
function r54Party(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere R54', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function r54User(string $name = 'Ana', string $phone = '0722111222'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function r54Admin(): Admin
{
    return Admin::create(['name' => 'Recepție', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => false, 'access_reception' => true, 'password' => 'secret-pass']);
}

it('biletul de pe numele cumpărătorului se poate trimite, la fel și cel primit', function () {
    $party = r54Party();
    $ana = r54User();
    $bob = r54User('Bob', '0733111222');
    $order = TicketOrders::place($ana, $party, 'Bilet', 2);
    $own = $order->tickets[0];

    expect($own->holder_participant_id)->toBe($ana->id)
        ->and(TicketTransfers::canSend($own->fresh(), $ana))->toBeTrue();

    TicketTransfers::send($own->id, $ana, $bob->phone, app(SmsSender::class));

    expect($own->fresh()->owner_participant_id)->toBe($bob->id)
        ->and($own->fresh()->holder_participant_id)->toBe($bob->id)
        ->and(TicketTransfers::canSend($own->fresh(), $ana))->toBeFalse()
        ->and(TicketTransfers::canSend($own->fresh(), $bob))->toBeTrue();   // runda 55: primit, se poate retrimite

    // La Ana rămâne în istoric, fără cod.
    $v = Livewire::actingAs($ana, 'participant')->test(Tickets::class)->viewData('sent');
    expect($v->pluck('id')->all())->toContain($own->id);
});

it('intră cu 2 bilete pe același nume (comenzi diferite)', function () {
    $party = r54Party();
    $ana = r54User();
    $first = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets[0];
    $second = TicketOrders::place($ana, $party, 'Bilet', 2)->tickets[0];   // al doilea tot pe numele ei (celălalt ar fi trimis)

    expect($first->holder_participant_id)->toBe($ana->id)->and($second->holder_participant_id)->toBe($ana->id);

    $rows = EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 100]], adminId: r54Admin()->id, ticketIds: [$first->id, $second->id]);

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('participant_id')->all())->toBe([$ana->id, null])
        ->and(Ticket::query()->whereIn('id', [$first->id, $second->id])->pluck('status')->unique()->all())->toBe(['used']);
});

it('Tranzacții: intrarea cu bilet plătit online arată suma plătită, nu „gratuit"', function () {
    $party = r54Party();
    $ana = r54User();
    $t = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets[0];
    $order = $t->order;
    $order->forceFill(['payment_status' => Order::PAY_PAID])->save();

    $admin = r54Admin();
    EntryRecorder::record($party, 'Bilet', 1, [], adminId: $admin->id, ticketIds: [$t->id]);

    $this->actingAs($admin, 'admin');
    session(['receptie.party_id' => $party->id]);

    Livewire::test(Recent::class)->assertSee('plătiți online')->assertSee('50,00')->assertDontSee('gratuit');
});

it('Bilete: biletul folosit rămâne în carusel (INTRAT, fără cod) și „Încarcă mai multe" afișează câte 5', function () {
    $party = r54Party();
    $ana = r54User();
    $tickets = TicketOrders::place($ana, $party, 'Bilet', 2)->tickets;
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 50]], adminId: null, ticketIds: [$tickets[0]->id]);

    $c = Livewire::actingAs($ana, 'participant')->test(Tickets::class);
    expect($c->viewData('carousel')->pluck('id')->all())->toBe([$tickets[1]->id, $tickets[0]->id]);
    $c->assertSee('INTRAT');
    expect($c->viewData('recent'))->toHaveCount(0);

    // 12 bilete anulate dintr-o petrecere încheiată: prima tranșă = 10, apoi încă 2.
    $old = Party::create(['name' => 'Veche', 'kind' => 'basic', 'start_date' => '2026-09-01', 'start_time' => '21:00', 'end_time' => '03:00', 'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published', 'payment_methods' => ['cash'], 'online_sales' => true, 'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]]]);
    foreach (range(1, 12) as $i) {
        Ticket::query()->create(['uuid' => (string) Str::uuid(), 'order_id' => $tickets[0]->order_id, 'party_id' => $old->id, 'ticket_type' => 'Bilet', 'owner_participant_id' => $ana->id, 'list_price' => 50, 'price' => 50, 'status' => Ticket::VOID]);
    }
    $c = Livewire::actingAs($ana, 'participant')->test(Tickets::class);
    expect($c->viewData('recent'))->toHaveCount(10);
    $c->call('more');
    expect($c->viewData('recent'))->toHaveCount(12);
});

it('vânzare: mesaj când nu se pot adăuga bilete (limită per comandă / stoc) și explicația prețurilor pe trepte', function () {
    $party = r54Party(['max_tickets_per_order' => 2]);
    $ana = r54User();

    Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $party])
        ->call('inc')->assertSee('numărul maxim de bilete pe o comandă (2)');

    $stock = r54Party(['max_tickets_per_order' => null, 'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'limit' => 2, 'discounts' => [], 'qty_tiers' => []]]]);
    Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $stock])
        ->call('inc')->assertSee('au mai rămas disponibile doar 2');

    $tier = r54Party(['ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [['first' => 2, 'price' => 30]]]]]);
    Livewire::actingAs($ana, 'participant')->test(PartyShow::class, ['party' => $tier])
        ->call('inc')->call('inc')->assertSee('primele 2 bilete sunt')->assertSee('iar următoarele 1 bilet costă');
});

it('al doilea bilet al aceluiași titular intră la o scanare separată, fără mesaj de dublură', function () {
    $party = r54Party();
    $ana = r54User();
    $a = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets[0];
    $b = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets[0];
    $admin = r54Admin();

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 50]], adminId: $admin->id, ticketIds: [$a->id]);
    $rows = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 50]], adminId: $admin->id, ticketIds: [$b->id]);

    expect($rows)->toHaveCount(1)->and($rows[0]->participant_id)->toBeNull()
        ->and(PartyEntry::query()->count())->toBe(2)
        ->and($b->fresh()->status)->toBe(Ticket::USED);
});

it('QR personal: al doilea bilet (liber) intră chiar dacă titularul ales a intrat deja, fără eroare', function () {
    $party = r54Party();
    $ana = r54User();
    $order = TicketOrders::place($ana, $party, 'Bilet', 2);
    $admin = r54Admin();

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 50]], adminId: $admin->id, ticketIds: [$order->tickets[0]->id], participants: [$ana->id]);
    $rows = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 50]], adminId: $admin->id, ticketIds: [$order->tickets[1]->id], participants: [$ana->id]);

    expect($rows)->toHaveCount(1)->and($rows[0]->participant_id)->toBeNull()->and($order->tickets[1]->fresh()->status)->toBe(Ticket::USED);
});
