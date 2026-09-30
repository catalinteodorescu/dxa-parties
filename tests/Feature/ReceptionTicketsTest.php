<?php

use App\Livewire\Admin\Parties\Form;
use App\Livewire\Participant\Tickets;
use App\Livewire\Reception\Entry;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Ticket;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Support\PartyPublic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (runda 15). Preț cu două limite (cumperi până / intri până), termen pe bilet, Recepție: scanare bilet / QR personal,
 * expirat cu diferență, bilete marcate folosite, anularea intrării redeschide biletul.
 * Petrecerea: bază 30 lei; 0 lei dacă intri până la 22:00; 20 lei dacă intri până la 23:00.
 */
function rtParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere ore', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'card'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 30, 'qty_tiers' => [], 'discounts' => [
            ['label' => 'Gratis', 'price' => 0, 'until' => null, 'enter_until' => '2026-10-03T22:00'],
            ['label' => 'Redus', 'price' => 20, 'until' => null, 'enter_until' => '2026-10-03T23:00'],
        ]]],
    ], $o));
}

function rtUser(string $name = 'Ana Cumpărător', string $phone = '0722111222'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function rtAdmin(): Admin
{
    return Admin::create(['name' => 'Recepție', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'is_active' => true, 'access_admin' => false, 'access_reception' => true, 'password' => 'secret-pass']);
}

/** Cumpără un bilet la ora $at (setează și „acum" la acea oră) și revine la „acum" = $back. */
function rtBuy(Participant $buyer, Party $party, string $at, int $count = 1, array $phones = []): Order
{
    $prev = now();
    Carbon::setTestNow(Carbon::parse($at));
    $order = TicketOrders::place($buyer, $party, 'Bilet', $count, $phones, null, Carbon::parse($at));
    Carbon::setTestNow($prev);

    return $order;
}

it('preț cu două limite: la fiecare oră câștigă cel mai mic rând încă valabil, iar biletul primește termenul lui', function () {
    $party = rtParty();
    $type = $party->ticket_types[0];

    $at = fn (string $t) => $party->priceRowAt($type, Carbon::parse("2026-10-03 $t"));
    expect($at('21:30'))->toBe(['price' => 0.0, 'enter_until' => '2026-10-03T22:00'])
        ->and($at('22:30'))->toBe(['price' => 20.0, 'enter_until' => '2026-10-03T23:00'])
        ->and($at('23:30'))->toBe(['price' => 30.0, 'enter_until' => null]);

    // Limita de cumpărare e independentă: „cumperi până la 22:00" nu mai dă prețul după.
    $early = ['price' => 30, 'discounts' => [['price' => 10, 'until' => '2026-10-03T22:00', 'enter_until' => null]]];
    expect($party->priceForTypeAt($early, Carbon::parse('2026-10-03 21:59')))->toBe(10.0)
        ->and($party->priceForTypeAt($early, Carbon::parse('2026-10-03 22:01')))->toBe(30.0);
});

it('bilet cumpărat online: prețul din rând, termenul „intri până la" pe bilet; baza nu expiră', function () {
    $party = rtParty();
    $ana = rtUser();

    $free = rtBuy($ana, $party, '2026-10-03 21:30')->tickets->first();
    expect((float) $free->price)->toBe(0.0)->and($free->valid_until->format('Y-m-d H:i'))->toBe('2026-10-03 22:00');

    $mid = rtBuy(rtUser('Bob', '0733222111'), $party, '2026-10-03 22:30')->tickets->first();
    expect((float) $mid->price)->toBe(20.0)->and($mid->valid_until->format('H:i'))->toBe('23:00');

    $late = rtBuy(rtUser('Cip', '0744333222'), $party, '2026-10-03 23:30')->tickets->first();
    expect((float) $late->price)->toBe(30.0)->and($late->valid_until)->toBeNull();
});

it('formularul petrecerii salvează ambele limite pe rândul de reducere', function () {
    $this->actingAs(Admin::create(['name' => 'Sa', 'phone' => '0700000001', 'role' => 'superadmin', 'is_active' => true, 'password' => 'secret-pass']), 'admin');
    $party = rtParty();

    $c = Livewire::test(Form::class, ['party' => $party])
        ->assertSee('Se poate cumpăra cu acest preț până la')->assertSee('Se poate intra cu acest preț până la');
    $c->set('ticket_types.0.discounts.1.until', '2026-10-03T20:00')->call('save')->assertHasNoErrors();

    $d = $party->fresh()->ticket_types[0]['discounts'][1];
    expect($d['until'])->toBe('2026-10-03T20:00')->and($d['enter_until'])->toBe('2026-10-03T23:00');
});

it('ticketDue: valabil = prețul (încă de plătit la intrare); expirat = diferența față de prețul curent', function () {
    $party = rtParty();
    $ana = rtUser();

    $free = rtBuy($ana, $party, '2026-10-03 21:30')->tickets->first();          // 0 lei, până 22:00
    $mid = rtBuy(rtUser('Bob', '0733222111'), $party, '2026-10-03 22:30')->tickets->first(); // 20 lei, până 23:00

    // Valabile acum (21:30 nu se schimbă): gratis 0; 20 nu era încă cumpărabil, dar biletul există.
    expect(EntryRecorder::ticketDue($free, Carbon::parse('2026-10-03 21:45')))->toMatchArray(['due' => 0, 'expired' => false]);

    // Bilet gratuit, ajunge 22:30: prețul curent 20 => 20 lei.
    $d = EntryRecorder::ticketDue($free, Carbon::parse('2026-10-03 22:30'));
    expect($d['expired'])->toBeTrue()->and($d['due'])->toBe(2000);

    // Bilet de 20 (nu s-a plătit online încă), ajunge 23:30: prețul curent 30 => 30 (20 + 10).
    expect(EntryRecorder::ticketDue($mid, Carbon::parse('2026-10-03 23:30'))['due'])->toBe(3000);

    // Același bilet, dar plătit online (Stripe, mai târziu): doar diferența de 10 lei.
    $mid->order->update(['payment_status' => 'paid']);
    expect(EntryRecorder::ticketDue($mid->fresh(['order', 'party']), Carbon::parse('2026-10-03 23:30'))['due'])->toBe(1000);

    // Toleranța din Setări (implicit 0 => fără). În fereastră: nu e expirat.
    expect(EntryRecorder::ticketDue($mid->fresh(['order', 'party']), Carbon::parse('2026-10-03 22:59'))['expired'])->toBeFalse();
});

it('intrare cu bilete online: se încasează suma, biletele devin folosite, persoanele în plus plătesc prețul curent', function () {
    $party = rtParty();
    $ana = rtUser();
    $order = rtBuy($ana, $party, '2026-10-03 21:30', 2, ['0733222111']);   // 2 bilete gratis, până 22:00
    $ids = $order->tickets->pluck('id')->all();

    Carbon::setTestNow(Carbon::parse('2026-10-03 22:30:00'));    // au întârziat: prețul curent 20
    $rows = EntryRecorder::record($party, 'Bilet', 3, [['method' => 'cash', 'amount' => 60]], adminId: rtAdmin()->id, participants: [$ana->id], ticketIds: $ids);

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('price_paid')->map(fn ($v) => (float) $v)->all())->toBe([20.0, 20.0, 20.0])
        ->and(Ticket::query()->whereIn('id', $ids)->pluck('status')->unique()->all())->toBe(['used'])
        ->and(Ticket::find($ids[0])->party_entry_id)->toBe($rows[0]->id)
        ->and($rows[0]->participant_id)->toBe($ana->id);

    // Același bilet nu se poate folosi de două ori.
    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [], adminId: null, ticketIds: [$ids[0]]))->toThrow(DomainException::class);
});

it('bilet valabil, gratuit, în fereastră: intră fără încasare; plata greșită e refuzată', function () {
    $party = rtParty();
    $ana = rtUser();
    $t = rtBuy($ana, $party, '2026-10-03 21:30')->tickets->first();

    Carbon::setTestNow(Carbon::parse('2026-10-03 21:50:00'));
    $rows = EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: [$t->id], participants: [$ana->id]);
    expect((float) $rows->first()->price_paid)->toBe(0.0)->and($t->fresh()->status)->toBe('used');

    $t2 = rtBuy(rtUser('Bob', '0733222111'), $party, '2026-10-03 21:31')->tickets->first();
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:40:00'));   // expirat: 20 lei
    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 5]], ticketIds: [$t2->id]))->toThrow(DomainException::class)
        ->and($t2->fresh()->status)->toBe('valid');
});

it('bilet de alt party sau anulat nu se acceptă; mai puține persoane decât bilete e eroare', function () {
    $party = rtParty();
    $other = rtParty(['name' => 'Alta']);
    $ana = rtUser();
    $mine = rtBuy($ana, $party, '2026-10-03 21:30', 2, [])->tickets;
    $foreign = rtBuy(rtUser('Bob', '0733222111'), $other, '2026-10-03 21:30')->tickets->first();

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: [$foreign->id]))->toThrow(DomainException::class)
        ->and(fn () => EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: $mine->pluck('id')->all()))->toThrow(DomainException::class);

    $mine[0]->update(['status' => 'void']);
    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: [$mine[0]->id]))->toThrow(DomainException::class);
});

it('anularea intrării redeschide biletele', function () {
    $party = rtParty();
    $ana = rtUser();
    $t = rtBuy($ana, $party, '2026-10-03 21:30')->tickets->first();

    Carbon::setTestNow(Carbon::parse('2026-10-03 21:40:00'));
    $rows = EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: [$t->id], participants: [$ana->id]);
    expect($t->fresh()->status)->toBe('used');

    EntryRecorder::cancelBatch($rows->first()->batch, 'greșeală');
    expect($t->fresh()->status)->toBe('valid')->and($t->fresh()->party_entry_id)->toBeNull()->and($t->fresh()->used_at)->toBeNull();
    // Se poate scana din nou.
    EntryRecorder::record($party, 'Bilet', 1, [], ticketIds: [$t->id], participants: [$ana->id]);
    expect(PartyEntry::query()->active()->count())->toBe(1);
});

it('scanare QR personal: precompletează participanții și toate biletele din contul lui; QR-ul altuia adaugă biletul lui', function () {
    $party = rtParty();
    $ana = rtUser();
    $bob = rtUser('Bob Prieten', '0733222111');
    $order = rtBuy($ana, $party, '2026-10-03 21:30', 2, ['0755000111']);        // Ana + un bilet fără nume (telefon fără cont)
    $bobTicket = rtBuy($bob, $party, '2026-10-03 21:30')->tickets->first();

    $this->actingAs(rtAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);

    $c = Livewire::test(Entry::class);
    $c->call('scanParticipant', $ana->qrPayload())->assertSet('count', 2)->assertSet('ticketIds', $order->tickets->pluck('id')->all());
    expect($c->get('participantIds'))->toBe([$ana->id])
        ->and((float) $c->viewData('total'))->toBe(0.0);
    $c->assertSee('Bilete online (2)')->assertSee('Intră gratuit')->assertSee('Fără nume');

    // QR-ul biletului lui Bob: al treilea bilet, Bob devine participant.
    expect($c->instance()->scanParticipant($bobTicket->qrPayload()))->toMatchArray(['ok' => true, 'message' => 'Bob Prieten']);
    $c->call('scanParticipant', $bobTicket->qrPayload())->assertSet('count', 3);
    expect($c->get('participantIds'))->toEqualCanonicalizing([$ana->id, $bob->id]);

    // Scoate un bilet: numărul de persoane rămâne (persoana plătește prețul curent la intrare).
    $c->call('removeTicket', $order->tickets[1]->id)->assertSet('count', 3);
});

it('scanare bilet: alt party / folosit / necunoscut sunt refuzate; expirat apare cu diferența și se poate înregistra', function () {
    $party = rtParty();
    $ana = rtUser();
    $t = rtBuy($ana, $party, '2026-10-03 21:30')->tickets->first();
    $foreign = rtBuy(rtUser('Bob', '0733222111'), rtParty(['name' => 'Alta']), '2026-10-03 21:30')->tickets->first();

    $this->actingAs(rtAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);
    $c = Livewire::test(Entry::class);

    expect($c->instance()->scanParticipant('DXA:T:00000000-0000-4000-8000-000000000000')['ok'])->toBeFalse()
        ->and($c->instance()->scanParticipant($foreign->qrPayload())['ok'])->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-03 22:30:00'));   // expirat: 20 lei
    $c->call('scanParticipant', $t->qrPayload())->assertSet('ticketIds', [$t->id]);
    $c->assertSee('EXPIRAT')->assertSee('de încasat 20,00 lei');

    $c->set('payments', [['method' => 'cash', 'amount' => '20']])->call('save')->assertSet('error', null)->assertSet('ticketIds', []);
    expect($t->fresh()->status)->toBe('used')
        ->and((float) PartyEntry::query()->sum('price_paid'))->toBe(20.0);

    // A doua scanare a aceluiași bilet: refuzat.
    expect($c->instance()->scanParticipant($t->qrPayload()))->toMatchArray(['ok' => false]);
});

it('pagina petrecerii arată la fiecare preț „cumperi până" / „intri până"', function () {
    $party = rtParty();

    $rows = collect(PartyPublic::tickets($party)[0]['rows']);
    expect($rows->pluck('note')->first())->toContain('intri până')
        ->and($rows->firstWhere('price', 20.0)['note'])->toContain('intri până')
        ->and($rows->firstWhere('label', 'Preț normal')['price'])->toBe(30.0);
});

it('contul participantului arată termenul biletului', function () {
    $party = rtParty();
    $ana = rtUser();
    rtBuy($ana, $party, '2026-10-03 21:30');

    $this->actingAs($ana, 'participant');
    Livewire::test(Tickets::class)->assertSee('Valabil la acest preț până la 03.10.2026 22:00');
});
