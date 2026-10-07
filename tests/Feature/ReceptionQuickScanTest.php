<?php

use App\Livewire\Participant\Tickets as ParticipantTickets;
use App\Livewire\Reception\Entry;
use App\Livewire\Reception\Home;
use App\Livewire\Reception\QuickScan;
use App\Livewire\Reception\TokenSale;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\Ticket;
use App\Services\ParticipantRegistry;
use App\Services\ReceptionScan;
use App\Services\TicketOrders;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (runda 47). Scanner pe ecranul principal din Recepție: intrare directă la bilet fără sumă de încasat,
 * trimitere la formular când e ceva de încasat, QR personal (0 / 1 / mai multe bilete), refuzuri, pre-completarea formularului.
 * Petrecerea: 30 lei; 0 lei dacă intri până la 22:00; 20 lei dacă intri până la 23:00 (biletul „gratis” are 0 de încasat până la 22:00).
 */
function qscParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere scan', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'card'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 30, 'qty_tiers' => [], 'discounts' => [
            ['label' => 'Gratis', 'price' => 0, 'until' => null, 'enter_until' => '2026-10-03T22:00'],
            ['label' => 'Redus', 'price' => 20, 'until' => null, 'enter_until' => '2026-10-03T23:00'],
        ]]],
    ], $o));
}

function qscUser(string $name = 'Ana Cumpărător', string $phone = '0722111222'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

function qscAdmin(): Admin
{
    return Admin::create(['name' => 'Recepție', 'phone' => '07'.random_int(10000000, 99999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'access_admin' => false, 'access_reception' => true, 'password' => 'secret-pass']);
}

/** Cumpără biletele la ora $at și revine la „acum” = 21:30. */
function qscBuy(Participant $buyer, Party $party, string $at = '2026-10-03 21:30', int $count = 1): Collection
{
    Carbon::setTestNow(Carbon::parse($at));
    $order = TicketOrders::place($buyer, $party, 'Bilet', $count, null, Carbon::parse($at));
    Carbon::setTestNow(Carbon::parse('2026-10-03 21:30:00'));

    return $order->tickets;
}

it('bilet valid fără nimic de încasat: intrare directă, biletul devine folosit', function () {
    $party = qscParty();
    $ana = qscUser();
    $ticket = qscBuy($ana, $party)->first();
    $admin = qscAdmin();

    $r = ReceptionScan::resolve($party, $ticket->qrPayload(), $admin->id);

    expect($r['status'])->toBe(ReceptionScan::ENTERED)
        ->and($r['message'])->toBe('Intrare înregistrată')->not->toContain('Ana')
        ->and($ticket->fresh()->status)->toBe(Ticket::USED)
        ->and(PartyEntry::query()->active()->count())->toBe(1)
        ->and(PartyEntry::query()->first()->participant_id)->toBe($ana->id);
});

it('bilet cu sumă de încasat (termen depășit): nu se înregistrează, formularul primește biletul', function () {
    $party = qscParty();
    $ticket = qscBuy(qscUser(), $party)->first();
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:30:00'));   // biletul gratis a expirat → 20 lei

    $r = ReceptionScan::resolve($party, $ticket->qrPayload(), qscAdmin()->id);

    expect($r['status'])->toBe(ReceptionScan::OPEN)
        ->and($r['query']['bilete'])->toBe((string) $ticket->id)
        ->and($ticket->fresh()->status)->toBe(Ticket::VALID)
        ->and(PartyEntry::query()->count())->toBe(0);
});

it('bilet cumpărat la 20 lei (comandă „la intrare”): se încasează la formular, nu intră direct', function () {
    $party = qscParty();
    $ticket = qscBuy(qscUser(), $party, '2026-10-03 22:30')->first();
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:40:00'));

    expect(ReceptionScan::resolve($party, $ticket->qrPayload(), qscAdmin()->id)['status'])->toBe(ReceptionScan::OPEN)
        ->and(PartyEntry::query()->count())->toBe(0);
});

it('refuzuri: folosit (cu ora), anulat, altă petrecere, bilet și cod necunoscute', function () {
    $party = qscParty();
    $admin = qscAdmin();
    $used = qscBuy(qscUser(), $party)->first();
    $void = qscBuy(qscUser('Bob', '0733222111'), $party)->first();
    $void->forceFill(['status' => Ticket::VOID])->save();
    $foreign = qscBuy(qscUser('Cip', '0744333222'), qscParty(['name' => 'Alta']))->first();

    expect(ReceptionScan::resolve($party, $used->qrPayload(), $admin->id)['status'])->toBe(ReceptionScan::ENTERED);

    $again = ReceptionScan::resolve($party, $used->qrPayload(), $admin->id);
    expect($again['status'])->toBe(ReceptionScan::ERROR)->and($again['message'])->toContain('deja folosit')->toContain('21:30');

    expect(ReceptionScan::resolve($party, $void->qrPayload(), $admin->id)['message'])->toContain('anulat')
        ->and(ReceptionScan::resolve($party, $foreign->qrPayload(), $admin->id)['message'])->toContain('altă petrecere')
        ->and(ReceptionScan::resolve($party, 'DXA:T:00000000-0000-4000-8000-000000000000', $admin->id)['status'])->toBe(ReceptionScan::ERROR)
        ->and(ReceptionScan::resolve($party, 'ceva-oarecare', $admin->id)['status'])->toBe(ReceptionScan::ERROR)
        ->and(PartyEntry::query()->count())->toBe(1);
});

it('QR personal: fără bilete → formularul cu persoana aleasă; un bilet → intră direct', function () {
    $party = qscParty();
    $admin = qscAdmin();
    $ana = qscUser();
    $bob = qscUser('Bob', '0733222111');
    qscBuy($bob, $party);

    $none = ReceptionScan::resolve($party, $ana->qrPayload(), $admin->id);
    expect($none['status'])->toBe(ReceptionScan::OPEN)->and($none['query'])->toBe(['participant' => (string) $ana->id]);

    $one = ReceptionScan::resolve($party, $bob->qrPayload(), $admin->id);
    expect($one['status'])->toBe(ReceptionScan::ENTERED)->and(PartyEntry::query()->count())->toBe(1);
});

it('QR personal cu mai multe bilete: alegere; intră doar cele bifate', function () {
    $party = qscParty();
    $ana = qscUser();
    $tickets = qscBuy($ana, $party, '2026-10-03 21:30', 3);

    $this->actingAs(qscAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);

    $c = Livewire::test(QuickScan::class);
    $r = $c->instance()->scan($ana->qrPayload());
    expect($r['status'])->toBe(ReceptionScan::CHOOSE)->and(PartyEntry::query()->count())->toBe(0)
        ->and(array_column($r['tickets'], 'id'))->toBe($tickets->pluck('id')->all())
        ->and($r['participant'])->toBe((string) $ana->id)
        ->and(array_column($r['tickets'], 'label'))->toBe(['Bilet · nr. 1', 'Bilet · nr. 2', 'Bilet · nr. 3']);   // fără nume

    // Bifele vin de la client (id-uri): intră doar cele trimise; lista goală nu înregistrează nimic.
    expect($c->instance()->enterSelected([], $r['participant'])['status'])->toBe(ReceptionScan::ERROR)
        ->and(PartyEntry::query()->count())->toBe(0)
        ->and($c->instance()->enterSelected([$tickets[0]->id, $tickets[2]->id], $r['participant'])['status'])->toBe(ReceptionScan::ENTERED);

    expect(PartyEntry::query()->count())->toBe(2)
        ->and($tickets[0]->fresh()->status)->toBe(Ticket::USED)
        ->and($tickets[1]->fresh()->status)->toBe(Ticket::VALID)
        ->and($tickets[2]->fresh()->status)->toBe(Ticket::USED);
});

it('alegerea nu primește bilete străine sau deja folosite (valorile vin de la client)', function () {
    $party = qscParty();
    $ana = qscUser();
    $mine = qscBuy($ana, $party)->first();
    $foreign = qscBuy(qscUser('Bob', '0733222111'), qscParty(['name' => 'Alta']))->first();
    $admin = qscAdmin();

    $r = ReceptionScan::enterTickets($party, [$mine->id, $foreign->id], $admin->id);
    expect($r['status'])->toBe(ReceptionScan::ERROR)->and(PartyEntry::query()->count())->toBe(0)
        ->and(ReceptionScan::enterTickets($party, [], $admin->id)['status'])->toBe(ReceptionScan::ERROR);
});

it('ecranul principal are butonul Scanează, iar formularul Intrare se pre-completează din adresă', function () {
    $party = qscParty();
    $ana = qscUser();
    $ticket = qscBuy($ana, $party)->first();

    $this->actingAs(qscAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);

    Livewire::test(Home::class)->assertSee('Scanează')->assertSeeHtml('data-quick-scan');

    // Popup-ul: ecran plin, cu buton „Închide” sub mesaj (runda 54), cu cerculețul care șterge rezultatul și checkbox custom la alegere.
    $html = Livewire::test(QuickScan::class)->html();
    expect($html)->toContain('data-scan-dismiss')->toContain('fixed inset-0')->toContain('peer sr-only')->toContain('data-scan-close')->toContain('>Am înțeles<')->toContain('data-scan-progress');

    Livewire::withQueryParams(['bilete' => (string) $ticket->id, 'participant' => (string) $ana->id])->test(Entry::class)
        ->assertSet('ticketIds', [$ticket->id])->assertSet('participantIds', [$ana->id])->assertSet('count', 1);

    // Un id de bilet străin sau inexistent în adresă nu se încarcă.
    Livewire::withQueryParams(['bilete' => '99999'])->test(Entry::class)->assertSet('ticketIds', []);
});

it('QuickScan răspunde cu adresa formularului când e ceva de încasat', function () {
    $party = qscParty();
    $ticket = qscBuy(qscUser(), $party, '2026-10-03 22:30')->first();
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:40:00'));

    $this->actingAs(qscAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);

    $r = Livewire::test(QuickScan::class)->instance()->scan($ticket->qrPayload());
    expect($r['status'])->toBe(ReceptionScan::OPEN)->and($r['url'])->toContain('/receptie/intrare')->toContain('bilete='.$ticket->id);
});

it('cu DXA_TICKETS_AUTO_PAID biletele cumpărate sunt achitate: intră direct chiar dacă au preț', function () {
    config(['app.dxa_tickets_auto_paid' => true]);
    $party = qscParty();
    $ticket = qscBuy(qscUser(), $party, '2026-10-03 22:30')->first();   // 20 lei
    Carbon::setTestNow(Carbon::parse('2026-10-03 22:40:00'));            // în termen (până la 23:00)

    expect((float) $ticket->price)->toBe(20.0)
        ->and($ticket->order->payment_status)->toBe('paid')
        ->and(ReceptionScan::resolve($party, $ticket->qrPayload(), qscAdmin()->id)['status'])->toBe(ReceptionScan::ENTERED)
        ->and($ticket->fresh()->status)->toBe(Ticket::USED);

    config(['app.dxa_tickets_auto_paid' => false]);
    expect(qscBuy(qscUser('Bob', '0733222111'), $party, '2026-10-03 22:30')->first()->order->payment_status)->toBe('at_entry');
});

it('aplicația participanților: în carusel, biletele ultimei comenzi sunt primele', function () {
    $party = qscParty();
    $ana = qscUser();
    $first = qscBuy($ana, $party, '2026-10-03 21:30', 2);
    $second = qscBuy($ana, $party, '2026-10-03 21:31');

    $ids = Livewire::actingAs($ana, 'participant')->test(ParticipantTickets::class)->viewData('valid')->pluck('id')->all();

    // Ultima comandă prima; în cadrul unei comenzi, ordinea lor firească.
    expect($ids)->toBe([$second[0]->id, $first[0]->id, $first[1]->id]);
});

it('scanare în formularul Intrare: același cod a doua oară e refuzat (nu mai spune „adăugat”)', function () {
    $party = qscParty();
    $ana = qscUser();
    $bob = qscUser('Bob', '0733222111');
    $ticket = qscBuy($bob, $party)->first();

    $this->actingAs(qscAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);
    $c = Livewire::test(Entry::class);
    $scan = fn (string $code) => $c->instance()->scanParticipant($code);

    // Participant fără bilete: prima dată ok, a doua oară refuzat.
    expect($scan($ana->qrPayload())['ok'])->toBeTrue();
    expect($scan($ana->qrPayload()))->toBe(['ok' => false, 'message' => 'Participantul e deja adăugat.']);

    // Bilet: la fel; QR-ul personal al deținătorului, după ce biletul e deja încărcat, tot refuzat.
    expect($scan($ticket->qrPayload())['ok'])->toBeTrue();
    expect($scan($ticket->qrPayload()))->toBe(['ok' => false, 'message' => 'Biletul e deja adăugat.'])
        ->and($scan($bob->qrPayload())['ok'])->toBeFalse();

    expect($c->get('participantIds'))->toEqualCanonicalizing([$ana->id, $bob->id])->and($c->get('ticketIds'))->toBe([$ticket->id]);
});

it('scanare în Vânzare tokeni: același cod a doua oară e refuzat', function () {
    $party = qscParty();
    $ana = qscUser();
    $this->actingAs(qscAdmin(), 'admin');
    session(['receptie.party_id' => $party->id]);

    $c = Livewire::test(TokenSale::class);
    expect($c->instance()->scanParticipant($ana->qrPayload())['ok'])->toBeTrue()
        ->and($c->instance()->scanParticipant($ana->qrPayload()))->toBe(['ok' => false, 'message' => 'Participantul e deja adăugat.']);
});
