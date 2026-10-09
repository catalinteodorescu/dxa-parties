<?php

use App\Livewire\Admin\Tickets\Index;
use App\Livewire\Admin\Tickets\Show;
use App\Livewire\Participant\Tickets as ParticipantTickets;
use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Services\AdminTickets;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 67). Codul biletului vizibil + anularea biletelor din admin, cu returnare în credite. */
function r67Admin(array $actions = ['cancel_tickets'], array $levels = ['tickets' => 1]): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => 'admin', 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => $actions],
    ]);
}

function r67Party(): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);

    return Party::create([
        'name' => 'Petrecere anulări', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'credit'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function r67User(string $name, string $phone): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p->fresh();
}

it('codul biletului apare pe cardul participantului și în rândul din lista admin', function () {
    $party = r67Party();
    $ana = r67User('Ana Cod', '0722670111');
    $t = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();

    Livewire::actingAs($ana, 'participant')->test(ParticipantTickets::class)->assertSee($t->shortCode());
    Livewire::actingAs(r67Admin(), 'admin')->test(Index::class)->assertSeeHtml('data-ticket-row-code')->assertSee($t->shortCode());
    Livewire::actingAs(r67Admin(), 'admin')->test(Index::class)->set('search', strtolower($t->shortCode()))->assertSee('Ana Cod');
});

it('anulare bilet plătit cu credite: locul se eliberează, banii revin în credite o singură dată', function () {
    $party = r67Party();
    $ana = r67User('Ana Credite', '0722670222');
    CreditLedger::load($ana, 200, CreditTransaction::SOURCE_MANUAL, null, 'test');
    $order = TicketOrders::place($ana->fresh(), $party, 'Bilet', 2, null, null, null, true);
    $t = $order->tickets->first();
    expect((float) $ana->fresh()->credit_balance)->toBe(100.0);

    Livewire::actingAs(r67Admin(), 'admin')->test(Show::class, ['ticket' => $t])
        ->assertSeeHtml('data-ticket-cancel-btn')
        ->call('cancel', 'nu mai poate veni', true)
        ->assertSee('50,00 lei au intrat în portofelul cumpărătorului');

    $t->refresh();
    expect($t->status)->toBe(Ticket::VOID)->and((float) $t->refunded_amount)->toBe(50.0)->and($t->cancel_reason)->toBe('nu mai poate veni')
        ->and((float) $ana->fresh()->credit_balance)->toBe(150.0)
        ->and(TicketOrders::soldCount($party, 'Bilet'))->toBe(1);

    expect(fn () => AdminTickets::cancel($t, 'din nou', true))->toThrow(DomainException::class);   // fără dublă rambursare
    expect((float) $ana->fresh()->credit_balance)->toBe(150.0);
});

it('anulare fără rambursare, sau la bilet de plătit la intrare, nu mișcă creditele', function () {
    $party = r67Party();
    $ana = r67User('Ana Intrare', '0722670333');
    $atEntry = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();
    expect(AdminTickets::refundable($atEntry->fresh(['order.participant'])))->toBe(0.0);
    expect(AdminTickets::cancel($atEntry, 'test', true))->toBe(0.0);

    $paid = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();
    $paid->order->update(['payment_status' => Order::PAY_CARD]);
    expect(AdminTickets::refundable($paid->fresh(['order.participant'])))->toBe(50.0);
    expect(AdminTickets::cancel($paid, 'fără rambursare', false))->toBe(0.0);
    expect((float) $ana->fresh()->credit_balance)->toBe(0.0);
});

it('bilet plătit cu cardul: se returnează în credite, iar jurnalul reține anularea', function () {
    $party = r67Party();
    $ana = r67User('Ana Card', '0722670444');
    $t = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();
    $t->order->update(['payment_status' => Order::PAY_CARD]);

    $admin = r67Admin();
    Livewire::actingAs($admin, 'admin')->test(Show::class, ['ticket' => $t])->call('cancel', 'cerere client', true);

    expect((float) $ana->fresh()->credit_balance)->toBe(50.0)
        ->and(CreditTransaction::query()->where('reference_type', Ticket::class)->where('reference_id', $t->id)->value('note'))->toContain('Bilet anulat');
    test()->assertDatabaseHas('admin_activity_logs', ['action' => 'tickets.cancelled', 'actor_id' => $admin->id]);

    Livewire::actingAs($admin, 'admin')->test(Show::class, ['ticket' => $t->fresh()])->assertSee('anulat: cerere client')->assertDontSeeHtml('data-ticket-cancel-btn');
});

it('nu se anulează biletul folosit sau rezervat, și motivul e obligatoriu', function () {
    $party = r67Party();
    $ana = r67User('Ana Reguli', '0722670555');
    $used = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();
    $used->forceFill(['status' => Ticket::USED, 'used_at' => now()])->save();
    $valid = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets->first();

    expect(fn () => AdminTickets::cancel($used, 'test', true))->toThrow(DomainException::class, 'folosit');
    expect(fn () => AdminTickets::cancel($valid, ' ', true))->toThrow(DomainException::class, 'motiv');
    Livewire::actingAs(r67Admin(), 'admin')->test(Show::class, ['ticket' => $used])->assertDontSeeHtml('data-ticket-cancel-btn')->call('cancel', 'x1y', true)->assertSee('deja folosit');
});

it('anularea cere acțiunea „Anulare bilete”: fără ea butonul lipsește și apelul e refuzat', function () {
    $party = r67Party();
    $t = TicketOrders::place(r67User('Ana Permisiuni', '0722670666'), $party, 'Bilet', 1)->tickets->first();
    $noAction = r67Admin([], ['tickets' => 2]);

    Livewire::actingAs($noAction, 'admin')->test(Show::class, ['ticket' => $t])->assertDontSeeHtml('data-ticket-cancel-btn')->call('cancel', 'încerc', true)->assertForbidden();
    expect($t->fresh()->status)->toBe(Ticket::VALID);

    Livewire::actingAs(r67Admin(), 'admin')->test(Show::class, ['ticket' => $t])->assertSeeHtml('data-ticket-cancel-btn')->call('cancel', 'motiv bun', true);
    expect($t->fresh()->status)->toBe(Ticket::VOID);
});
