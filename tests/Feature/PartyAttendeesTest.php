<?php

use App\Livewire\Admin\Parties\Attendees;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Ticket;
use App\Services\ParticipantRegistry;
use App\Services\PartyAttendees;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 43). Evidența participanților la o petrecere (admin). */
function paParty2(string $name = 'Petrecere evidență'): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create([
        'name' => $name, 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function paUser(string $name, string $phone, bool $account = true): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    if ($account) {
        $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    }

    return $p;
}

/** Fără $holder și fără $phone, deținătorul e cumpărătorul (ca la biletul 1 dintr-o comandă); $unnamed = bilet fără deținător identificat. */
function paTicket(Party $party, Participant $buyer, ?Participant $holder = null, ?string $phone = null, string $status = 'valid', bool $unnamed = false): Ticket
{
    $holder ??= ($phone !== null || $unnamed) ? null : $buyer;
    $order = Order::create(['uuid' => (string) Str::uuid(), 'participant_id' => $buyer->id, 'party_id' => $party->id, 'tickets_count' => 1, 'subtotal' => 50, 'total' => 50]);

    return Ticket::create([
        'order_id' => $order->id, 'party_id' => $party->id, 'ticket_type' => 'Bilet', 'owner_participant_id' => ($holder ?? $buyer)->id,
        'holder_participant_id' => $holder?->id, 'holder_phone' => $phone, 'list_price' => 50, 'price' => 50, 'status' => $status,
    ]);
}

function paEntry(Party $party, ?Participant $p = null, bool $cancelled = false): PartyEntry
{
    return PartyEntry::create([
        'party_id' => $party->id, 'batch' => (string) Str::uuid(), 'ticket_type' => 'Bilet', 'list_price' => 50, 'price_paid' => 50,
        'entered_at' => now(), 'participant_id' => $p?->id, 'cancelled_at' => $cancelled ? now() : null,
    ]);
}

function paAdmin(): Admin
{
    return Admin::create(['name' => 'Admin evidență', 'phone' => '+40700'.random_int(100000, 999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']);
}

function paRows($c): array
{
    return collect($c->viewData('people')->items())->keyBy(fn ($r) => $r->participant_id ?? 'tel-'.$r->phone)->all();
}

it('grupează biletele după deținător, intrările după participant și deduce situația', function () {
    $party = paParty2();
    $ana = paUser('Ana Pop', '0722000001');
    $bogdan = paUser('Bogdan Ion', '0722000002');
    $cora = paUser('Cora Ene', '0722000003');

    paTicket($party, $ana);                                       // Ana: bilet valabil, neintrată
    paTicket($party, $bogdan, null, '+40733000009');              // bilet cumpărat de Bogdan, deținător doar telefon
    paTicket($party, $cora, null, null, 'used');                  // Cora: bilet folosit
    paEntry($party, $cora);
    paEntry($party, $bogdan);                                     // Bogdan a intrat fără bilet (la casă, identificat)

    $data = PartyAttendees::for($party);
    $by = $data->rows->keyBy(fn ($r) => $r->participant_id ?? 'tel-'.$r->phone);

    expect($by[$ana->id]->status)->toBe(PartyAttendees::WAITING)->and($by[$ana->id]->tickets)->toBe(1)->and($by[$ana->id]->valid)->toBe(1)
        ->and($by[$cora->id]->status)->toBe(PartyAttendees::ENTERED)->and($by[$cora->id]->used)->toBe(1)->and($by[$cora->id]->entries)->toBe(1)
        ->and($by[$bogdan->id]->status)->toBe(PartyAttendees::ENTERED)->and($by[$bogdan->id]->tickets)->toBe(0)
        ->and($by['tel-+40733000009']->status)->toBe(PartyAttendees::PHONE)->and($by['tel-+40733000009']->name)->toBe('Fără cont')
        ->and($by[$ana->id]->has_account)->toBeTrue();
});

it('sumarul: intrări identificate/anonime, persoane, bilete valabile, bilete fără deținător; anulatele nu se numără', function () {
    $party = paParty2();
    $ana = paUser('Ana Pop', '0722000001');
    $dan = paUser('Dan Rus', '0722000004');

    paTicket($party, $ana);
    paTicket($party, $ana, null, null, 'valid', true);           // al doilea bilet al Anei, fără deținător (ex: pentru un prieten fără nume)
    paTicket($party, $ana, null, null, 'valid', true);
    paTicket($party, $dan, $dan, null, 'void');                  // anulat: ignorat
    paEntry($party, $ana);
    paEntry($party, null);                                       // anonimă
    paEntry($party, null);
    paEntry($party, $dan, true);                                 // anulată: ignorată

    $s = PartyAttendees::for($party)->summary;
    expect($s->entries)->toBe(3)->and($s->identified_entries)->toBe(1)->and($s->anonymous_entries)->toBe(2)
        ->and($s->people_entered)->toBe(1)->and($s->people_waiting)->toBe(0)
        ->and($s->tickets_valid)->toBe(3)->and($s->unnamed_tickets)->toBe(2);

    // Dan nu apare deloc (bilet anulat + intrare anulată)
    expect(PartyAttendees::for($party)->rows->pluck('participant_id')->all())->toBe([$ana->id]);
});

it('nu amestecă petrecerile', function () {
    $a = paParty2('A');
    $b = paParty2('B');
    $ana = paUser('Ana Pop', '0722000001');
    paTicket($a, $ana);
    paEntry($b, $ana);

    expect(PartyAttendees::for($a)->rows)->toHaveCount(1)->and(PartyAttendees::for($a)->summary->entries)->toBe(0)
        ->and(PartyAttendees::for($b)->rows->first()->status)->toBe(PartyAttendees::ENTERED);
});

it('pagina: căutare după nume (fără diacritice) și telefon, filtru de situație, resetare, paginare', function () {
    $party = paParty2();
    $ana = paUser('Ștefan Țurcanu', '0722000001');
    $bob = paUser('Bob Marin', '0722000002');
    paTicket($party, $ana);
    paEntry($party, $bob);

    $t = Livewire::actingAs(paAdmin(), 'admin')->test(Attendees::class, ['party' => $party]);
    expect(array_keys(paRows($t)))->toHaveCount(2);

    $t->set('search', 'stefan turcanu');
    expect(array_keys(paRows($t)))->toBe([$ana->id]);
    $t->set('search', '722000002');
    expect(array_keys(paRows($t)))->toBe([$bob->id]);
    $t->set('search', '');
    $t->set('status', PartyAttendees::ENTERED);
    expect(array_keys(paRows($t)))->toBe([$bob->id]);
    $t->set('status', PartyAttendees::WAITING);
    expect(array_keys(paRows($t)))->toBe([$ana->id]);
    $t->call('clearFilters');
    expect(array_keys(paRows($t)))->toHaveCount(2);

    // paginare: 30 de persoane => 25 + 5
    foreach (range(1, 28) as $i) {
        paEntry($party, paUser('Persoană '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '07230000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
    }
    $t = Livewire::actingAs(paAdmin(), 'admin')->test(Attendees::class, ['party' => $party]);
    expect($t->viewData('people')->items())->toHaveCount(25)->and($t->viewData('people')->total())->toBe(30);
    $t->set('paginators.page', 2);
    expect($t->viewData('people')->items())->toHaveCount(5);
});

it('pagina se deschide din Statistici petrecere › Participanți (nu din pagina petrecerii) și cere admin logat', function () {
    $party = paParty2();
    paEntry($party, paUser('Ana Test', '0721000001'));
    // Pagina petrecerii nu mai are butonul „Participanți”; linkul e lângă titlul secțiunii Participanți din statistici.
    $this->actingAs(paAdmin(), 'admin')->get(route('admin.parties.show', $party))->assertOk()->assertDontSee(route('admin.parties.attendees', $party), false);
    $this->actingAs(paAdmin(), 'admin')->get(route('admin.parties.stats', $party))->assertOk()
        ->assertSee('Vezi lista participanți')->assertSee(route('admin.parties.attendees', $party), false);
    $this->actingAs(paAdmin(), 'admin')->get(route('admin.parties.attendees', $party))->assertOk()->assertSee('Participanți');
    auth('admin')->logout();
    $this->get(route('admin.parties.attendees', $party))->assertRedirect();
});
