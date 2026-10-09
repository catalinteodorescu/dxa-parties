<?php

use App\Livewire\Admin\Tickets\Index;
use App\Livewire\Admin\Tickets\Show;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 66). Pagina „Bilete” din admin: listă, căutare, filtre, detaliu, permisiuni. Doar citire. */
function r66Admin(array $levels = ['tickets' => 1], string $role = 'admin'): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => []],
    ]);
}

function r66Party(string $name = 'Petrecere bilete admin'): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create([
        'name' => $name, 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r66User(string $name, string $phone): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p->fresh();
}

it('pagina cere permisiunea „Bilete”; fără ea → 403, cu ea se vede lista', function () {
    $party = r66Party();
    TicketOrders::place(r66User('Ana Listă', '0722600111'), $party, 'Bilet', 1);

    test()->actingAs(r66Admin(['participants' => 3]), 'admin')->get(route('admin.tickets.index'))->assertForbidden();
    test()->actingAs(r66Admin(['tickets' => 1]), 'admin')->get(route('admin.tickets.index'))->assertOk()->assertSee('Ana Listă')->assertSee('Bilete');
    test()->actingAs(r66Admin([], 'superadmin'), 'admin')->get(route('admin.tickets.index'))->assertOk();
});

it('meniul arată „Bilete” doar cui are voie', function () {
    test()->actingAs(r66Admin(['tickets' => 1]), 'admin')->get(route('admin.tickets.index'))->assertSee(route('admin.tickets.index'), false);
    test()->actingAs(r66Admin(['participants' => 1]), 'admin')->get(route('admin.participants.index'))->assertDontSee(route('admin.tickets.index'), false);
});

it('căutare: după nume, telefon, cod de bilet și #comandă', function () {
    $party = r66Party();
    $ana = r66User('Ana Căutare', '0722600222');
    $bob = r66User('Bob Altul', '0733600333');
    $o1 = TicketOrders::place($ana, $party, 'Bilet', 2);
    $o2 = TicketOrders::place($bob, $party, 'Bilet', 1);
    $admin = r66Admin();

    $names = fn ($c) => $c->viewData('tickets')->pluck('order_id')->unique()->sort()->values()->all();
    $c = Livewire::actingAs($admin, 'admin')->test(Index::class);

    expect($names($c))->toBe([$o1->id, $o2->id]);
    expect($names($c->set('search', 'Căutare')))->toBe([$o1->id]);
    expect($names($c->set('search', '0733 600 333')))->toBe([$o2->id]);
    expect($names($c->set('search', substr($o2->tickets->first()->uuid, 0, 8))))->toBe([$o2->id]);
    expect($names($c->set('search', '#'.$o1->id)))->toBe([$o1->id]);
    expect($names($c->set('search', 'nimeni-nu-se-cheamă-așa')))->toBe([]);
    $c->assertSee('Niciun bilet');
});

it('filtre: petrecere, status, plată (la intrare / card / gratuit) și perioadă', function () {
    $a = r66Party('Petrecere A');
    $b = r66Party('Petrecere B');
    $ana = r66User('Ana Filtre', '0722600444');
    TicketOrders::place($ana, $a, 'Bilet', 1);
    $card = TicketOrders::place($ana, $b, 'Bilet', 1, null, null, null, false, true);   // rezervat cu cardul
    $card->tickets->first()->update(['status' => Ticket::PENDING]);
    $c = Livewire::actingAs(r66Admin(), 'admin')->test(Index::class);
    $parties = fn ($c) => $c->viewData('tickets')->pluck('party_id')->unique()->values()->all();

    expect($parties($c->set('filterParty', (string) $b->id)))->toBe([$b->id]);
    $c->set('filterParty', '');
    expect($parties($c->set('filterPayment', 'card')))->toBe([$b->id]);
    expect($parties($c->set('filterPayment', 'at_entry')))->toBe([$a->id]);
    $c->set('filterPayment', '');
    expect($parties($c->set('filterStatus', 'pending')))->toBe([$b->id]);
    $c->set('filterStatus', '');
    expect($c->set('dateFrom', '2030-01-01')->viewData('tickets')->total())->toBe(0);
    expect($c->call('clearFilters')->viewData('tickets')->total())->toBe(2);
});

it('detaliu: bilet, comandă, celelalte bilete și istoric (trimitere + folosit)', function () {
    $party = r66Party();
    $ana = r66User('Ana Detaliu', '0722600555');
    $bob = r66User('Bob Primește', '0733600666');
    $order = TicketOrders::place($ana, $party, 'Bilet', 2);
    $t = $order->tickets->first();
    $other = $order->tickets->last();
    TicketTransfer::create(['ticket_id' => $other->id, 'from_participant_id' => $ana->id, 'to_participant_id' => $bob->id, 'to_phone' => '+40733600666', 'to_had_account' => true, 'sms_sent' => false, 'created_at' => now()]);
    $t->forceFill(['status' => Ticket::USED, 'used_at' => now()])->save();
    $order->update(['payment_provider' => 'stripe', 'payment_ref' => 'pi_abc123']);

    Livewire::actingAs(r66Admin(), 'admin')->test(Show::class, ['ticket' => $t])
        ->assertSee('Ana Detaliu')->assertSee('Comanda #'.$order->id)->assertSee('100,00 lei')
        ->assertSee('pi_abc123')->assertSee('folosit la intrare')->assertSee(strtoupper(substr($t->uuid, 0, 8)));

    Livewire::actingAs(r66Admin(), 'admin')->test(Show::class, ['ticket' => $other])
        ->assertSee('trimis de Ana Detaliu către Bob Primește');
});

it('biletul rezervat cu cardul apare ca atare, cu starea comenzii', function () {
    $party = r66Party();
    $order = TicketOrders::place(r66User('Ana Rezervă', '0722600777'), $party, 'Bilet', 1, null, null, null, false, true);

    Livewire::actingAs(r66Admin(), 'admin')->test(Index::class)->assertSee('Rezervat (așteaptă plata)')->assertSee('Card · așteaptă plata');
    Livewire::actingAs(r66Admin(), 'admin')->test(Show::class, ['ticket' => $order->tickets->first()])->assertSee('rezervat, așteaptă plata cu cardul')->assertSee('Rezervată până la');
});

it('migrarea acordă „Bilete” adminilor care vedeau Participanți, fără să schimbe alți admini', function () {
    $with = r66Admin(['participants' => 2]);
    $without = r66Admin(['parties' => 3]);
    $already = r66Admin(['participants' => 3, 'tickets' => 0]);

    (require base_path('database/migrations/2024_08_08_000001_grant_tickets_view_to_participants_admins.php'))->up();

    expect($with->fresh()->permissionLevel('tickets'))->toBe(1)
        ->and($without->fresh()->permissionLevel('tickets'))->toBe(0)
        ->and($already->fresh()->permissionLevel('tickets'))->toBe(0);   // nivelul setat explicit rămâne
});
