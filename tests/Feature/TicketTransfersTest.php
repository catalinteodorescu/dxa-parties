<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\Participants\Show;
use App\Livewire\Participant\Register;
use App\Livewire\Participant\Tickets;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\Party;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantRegistry;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\ParticipantAppSettings;
use App\Support\Permissions;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 39). „Trimite biletul”: transfer către cont existent, către telefon fără cont (SMS + link), reguli, istoric. */
class FakeTransferSms implements SmsSender
{
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = [$phone, $message];
    }
}

function ttParty(): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create([
        'name' => 'Petrecere transfer', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
}

function ttUser(string $name, string $phone): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

/** Runda 53: cumpără 2 bilete și întoarce al doilea (liber, fără nume): doar biletele libere se pot trimite. */
function ttTicket(Participant $buyer, ?Party $party = null, int $count = 2): Ticket
{
    $order = TicketOrders::place($buyer, $party ?? ttParty(), 'Bilet', $count);

    return $order->tickets[1];
}

beforeEach(function () {
    $this->sms = new FakeTransferSms;
    app()->instance(SmsSender::class, $this->sms);
});

it('transferă biletul către un cont existent: dispare din contul expeditorului și apare în al destinatarului', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $ticket = ttTicket($ana);

    $r = TicketTransfers::send($ticket->id, $ana, '0733 111 222', $this->sms);

    expect($r['has_account'])->toBeTrue()->and($r['sms'])->toBeFalse()->and($this->sms->sent)->toBeEmpty();
    $ticket->refresh();
    expect($ticket->owner_participant_id)->toBe($bob->id)
        ->and($ticket->holder_participant_id)->toBe($bob->id)
        ->and($ticket->holder_phone)->toBeNull()
        ->and((float) $ticket->price)->toBe(50.0)
        ->and($ticket->status)->toBe(Ticket::VALID)
        ->and(TicketTransfer::count())->toBe(1);

    $this->actingAs($bob, 'participant');
    Livewire::test(Tickets::class)->assertSee('Petrecere transfer')->assertSee('data-send-ticket="'.$ticket->id.'"', false);   // runda 55: biletul primit se poate retrimite
});

it('către un telefon fără cont: creează participantul, trimite SMS cu link semnat către bilet', function () {
    $ana = ttUser('Ana', '0722111222');
    $ticket = ttTicket($ana);

    $r = TicketTransfers::send($ticket->id, $ana, '0744555666', $this->sms);

    expect($r['has_account'])->toBeFalse()->and($r['sms'])->toBeTrue();
    $to = Participant::where('phone', Participant::where('phone', '!=', $ana->phone)->value('phone'))->first();
    $ticket->refresh();
    expect($ticket->holder_participant_id)->toBe($to->id)->and($ticket->owner_participant_id)->toBe($to->id)
        ->and($ticket->holder_phone)->toBe($to->phone)->and($to->hasAccount())->toBeFalse();

    expect($this->sms->sent)->toHaveCount(1);
    [$phone, $text] = $this->sms->sent[0];
    expect($phone)->toBe($to->phone)->and($text)->toContain('Ana')->toContain('Petrecere transfer')->toContain('/b/'.TicketTransfers::shortCode($ticket->fresh()));
    expect(TicketTransfer::first()->sms_sent)->toBeTrue();
});

it('refolosește participantul fără cont creat la Recepție', function () {
    $ana = ttUser('Ana', '0722111222');
    $recep = ParticipantRegistry::create('Costel Recepție', '0755000111');
    $ticket = ttTicket($ana);

    TicketTransfers::send($ticket->id, $ana, '0755000111', $this->sms);

    expect($ticket->fresh()->holder_participant_id)->toBe($recep->id)->and(Participant::count())->toBe(2);
});

it('pagina din link arată biletul și îndrumă spre cont; linkul se stinge la o nouă schimbare și cere semnătură', function () {
    $ana = ttUser('Ana', '0722111222');
    $ticket = ttTicket($ana);
    TicketTransfers::send($ticket->id, $ana, '0744555666', $this->sms);
    $ticket->refresh();
    $url = TicketTransfers::linkFor($ticket);   // runda 52d: link scurt

    $this->get($url)->assertOk()->assertSee('Ai primit un bilet')->assertSee($ticket->qrPayload(), false)->assertSee('Creează cont');
    $this->get(route('app.ticket-link', ['ticket' => $ticket->uuid, 'v' => 'x']))->assertForbidden();   // fără semnătură

    // stamp greșit, dar semnătură bună → pagina „link nevalabil”
    $bad = URL::signedRoute('app.ticket-link', ['ticket' => $ticket->uuid, 'v' => 'gresit']);
    $this->get($bad)->assertOk()->assertSee('Linkul nu mai este valabil');

    // biletul folosit → link nevalabil
    $ticket->forceFill(['status' => Ticket::USED])->save();
    $this->get($url)->assertOk()->assertSee('Linkul nu mai este valabil');
});

it('după ce destinatarul își face cont cu telefonul, biletul e deja în contul lui', function () {
    $ana = ttUser('Ana', '0722111222');
    $ticket = ttTicket($ana);
    TicketTransfers::send($ticket->id, $ana, '0744555666', $this->sms);
    $url = TicketTransfers::linkFor($ticket->fresh());

    $phone = ParticipantAccounts::startRegistration('Dan Nou', '0744555666', 'parola-sigura', $this->sms);
    $code = null;
    preg_match('/\d{6}/', end($this->sms->sent)[1], $m);
    $dan = ParticipantAccounts::verifyRegistration($phone, $m[0]);

    expect($ticket->fresh()->owner_participant_id)->toBe($dan->id);
    $this->get($url)->assertOk()->assertSee('Biletul e în contul tău');

    $this->actingAs($dan, 'participant');
    Livewire::test(Tickets::class)->assertSee('Petrecere transfer');
});

it('formularul de cont nou se completează cu telefonul din link', function () {
    Livewire::withQueryParams(['telefon' => '+40744555666'])->test(Register::class)->assertSet('phone', '+40744555666');
});

it('cel care primește biletul îl poate trimite mai departe, iar cel vechi nu mai poate', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $cip = ttUser('Cip', '0766111222');
    $ticket = ttTicket($ana);

    TicketTransfers::send($ticket->id, $ana, $bob->phone, $this->sms);
    expect(fn () => TicketTransfers::send($ticket->id, $ana, $cip->phone, $this->sms))->toThrow(DomainException::class);

    // Runda 55: biletul primit se poate trimite mai departe; cumpărătorul îl vede în istoric.
    TicketTransfers::send($ticket->id, $bob, $cip->phone, $this->sms);
    expect($ticket->fresh()->owner_participant_id)->toBe($cip->id)->and(TicketTransfer::count())->toBe(2)
        ->and(fn () => TicketTransfers::send($ticket->id, $bob, $ana->phone, $this->sms))->toThrow(DomainException::class);
    $this->actingAs($bob, 'participant');
    Livewire::test(Tickets::class)->assertSee('Trimise de mine')->assertSee('trimis lui Cip');
});

it('refuză: bilet folosit sau anulat, petrecere încheiată, propriul număr, număr invalid, bilet trimis de altcineva', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $ticket = ttTicket($ana);

    expect(fn () => TicketTransfers::send($ticket->id, $ana, $ana->phone, $this->sms))->toThrow(DomainException::class, 'numărul tău');
    expect(fn () => TicketTransfers::send($ticket->id, $ana, '123', $this->sms))->toThrow(DomainException::class, 'nu pare valid');
    expect(fn () => TicketTransfers::send($ticket->id, $bob, '0766111222', $this->sms))->toThrow(DomainException::class);

    foreach ([Ticket::USED, Ticket::VOID] as $status) {
        $ticket->forceFill(['status' => $status])->save();
        expect(fn () => TicketTransfers::send($ticket->id, $ana, $bob->phone, $this->sms))->toThrow(DomainException::class);
    }

    $ticket->forceFill(['status' => Ticket::VALID])->save();
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    expect(fn () => TicketTransfers::send($ticket->id, $ana, $bob->phone, $this->sms))->toThrow(DomainException::class);
    expect(TicketTransfer::count())->toBe(0)->and($ticket->fresh()->owner_participant_id)->toBe($ana->id);
});

it('păstrează prețul, reducerea și combo-ul: nu recalculează nimic', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $party = ttParty();
    $party->update(['ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [], 'combos' => [['buy' => 1, 'free' => 1]]]]]);
    $order = TicketOrders::place($ana, $party, 'Bilet', 2, null, null, '1+1');
    $free = $order->tickets->firstWhere('combo_free', true);

    TicketTransfers::send($free->id, $ana, $bob->phone, $this->sms);

    $free->refresh();
    expect((float) $free->price)->toBe(0.0)->and($free->combo_free)->toBeTrue()->and($free->combo_label)->toBe('1+1')->and($free->order_id)->toBe($order->id);
    expect(TicketOrders::soldCount($party, 'Bilet'))->toBe(2);
});

it('cumpărătorul vede în continuare biletul trimis, fără butonul de trimitere', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $ticket = ttTicket($ana);
    TicketTransfers::send($ticket->id, $ana, $bob->phone, $this->sms);

    $this->actingAs($ana, 'participant');
    Livewire::test(Tickets::class)->assertSee('Trimise de mine')->assertSee('trimis lui Bob')->assertDontSee('data-send-ticket="'.$ticket->id.'"', false);
});

it('limitează la 10 trimiteri pe oră', function () {
    $ana = ttUser('Ana', '0722111222');
    RateLimiter::clear('ticket-transfer:'.$ana->id);
    $ticket = ttTicket($ana);
    for ($i = 0; $i < TicketTransfers::MAX_PER_HOUR; $i++) {
        RateLimiter::hit('ticket-transfer:'.$ana->id, 3600);
    }

    expect(fn () => TicketTransfers::send($ticket->id, $ana, '0766111222', $this->sms))->toThrow(DomainException::class, 'prea multe');
});

it('dialogul din aplicație: telefon → confirmare → trimis', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $ticket = ttTicket($ana);
    $this->actingAs($ana, 'participant');

    Livewire::test(Tickets::class)
        ->call('startSend', $ticket->id)->assertSee('Trimite biletul')->assertSet('sendStep', 'form')
        ->set('sendPhone', '0722111222')->call('checkSend')->assertSet('sendStep', 'form')->assertSee('numărul tău')
        ->set('sendPhone', '0733111222')->call('checkSend')->assertSet('sendStep', 'confirm')->assertSee('are cont în aplicație')->assertDontSee('Bob')->assertSee('nu se poate anula')
        ->call('confirmSend')->assertSet('sendId', null);

    expect($ticket->fresh()->owner_participant_id)->toBe($bob->id);
});

it('dialogul către un număr fără cont anunță SMS-ul', function () {
    $ana = ttUser('Ana', '0722111222');
    $ticket = ttTicket($ana);
    $this->actingAs($ana, 'participant');

    Livewire::test(Tickets::class)->call('startSend', $ticket->id)
        ->set('sendPhone', '0744555666')->call('checkSend')->assertSee('nu are cont')->assertSee('SMS')
        ->call('confirmSend');

    expect($this->sms->sent)->toHaveCount(1);
});

it('istoricul apare în pagina participantului din admin', function () {
    $ana = ttUser('Ana', '0722111222');
    $bob = ttUser('Bob', '0733111222');
    $ticket = ttTicket($ana);
    TicketTransfers::send($ticket->id, $ana, $bob->phone, $this->sms);

    $admin = Admin::create(['name' => 'Admin T', 'phone' => '+40700'.random_int(100000, 999999), 'role' => 'admin', 'permissions' => Permissions::legacyAdmin(), 'is_active' => true, 'password' => 'secret-pass']);
    $this->actingAs($admin, 'admin');

    Livewire::test(Show::class, ['participant' => $ana])->assertSee('Bilete trimise / primite')->assertSee('trimis către Bob');
    Livewire::test(Show::class, ['participant' => $bob])->assertSee('primit de la Ana');
});

it('SMS-ul are șablon editabil cu {link} obligatoriu', function () {
    expect(ParticipantAppSettings::smsTicket('https://x.ro/b', 'Ana', 'Party'))->toContain('https://x.ro/b')->toContain('Ana')->toContain('Party');
    Settings::set('app_sms_ticket', 'Fără link aici');
    expect(ParticipantAppSettings::smsTicket('https://x.ro/b', 'Ana', 'Party'))->toContain('https://x.ro/b');
    Settings::set('app_sms_ticket', 'Bilet de la {nume}: {link}');
    expect(ParticipantAppSettings::smsTicket('https://x.ro/b', 'Ana', 'Party'))->toBe('Bilet de la Ana: https://x.ro/b');
});
