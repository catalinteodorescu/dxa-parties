<?php

use App\Contracts\SmsSender;
use App\Livewire\Participant\DeleteAccount;
use App\Models\AdminActivityLog;
use App\Models\CreditTopup;
use App\Models\CreditTransaction;
use App\Models\LoyaltyCard;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\PartyInterest;
use App\Models\Ticket;
use App\Services\AccountDeletion;
use App\Services\AccountExport;
use App\Services\CreditLedger;
use App\Services\CreditTopups;
use App\Services\ParticipantRegistry;
use App\Services\Sms\AsciiSmsSender;
use App\Services\TicketOrders;
use App\Services\TicketTransfers;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 52). Ștergerea contului de către participant, și exportul datelor. */
function adlParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);

    return Party::create(array_merge([
        'name' => 'Petrecere ștergere', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash', 'credit'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => [], 'combos' => []]],
    ], $o));
}

function adlUser(float $credits = 0.0, string $name = 'Ana Ștearsă', string $phone = '0722123123'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();
    if ($credits > 0) {
        CreditLedger::load($p, $credits, CreditTransaction::SOURCE_MANUAL, null, 'test');
    }

    return $p->fresh();
}

function adlEntry(Participant $p, Party $party, string $at = '2026-09-20 22:00:00'): PartyEntry
{
    return PartyEntry::create([
        'party_id' => $party->id, 'batch' => (string) Str::uuid(), 'ticket_type' => 'Bilet', 'list_price' => 50,
        'price_paid' => 50, 'entered_at' => $at, 'participant_id' => $p->id,
    ]);
}

function adlSms(): object
{
    return new class implements SmsSender
    {
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = [$phone, $message];
        }
    };
}

// ---- Istoric ------------------------------------------------------------------

it('istoric: încărcarea din aplicație arată doar „Încărcare”, fără sursa; la recepție rămâne sursa', function () {
    $ana = adlUser();
    $app = CreditLedger::load($ana, 50, CreditTransaction::SOURCE_APP, null, 'Încărcare cu cardul');
    $rec = CreditLedger::load($ana, 20, CreditTransaction::SOURCE_RECEPTION);

    $html = view('livewire.participant._credit-row', ['t' => $app])->render();
    expect($html)->toContain('Încărcare')->and($html)->not->toContain('Aplicație participant')->and($html)->toContain('Încărcare cu cardul');
    expect(view('livewire.participant._credit-row', ['t' => $rec])->render())->toContain('Recepție');
});

// ---- Credite pierdute ----------------------------------------------------------

it('forfeit: scoate tot soldul printr-o ajustare de sistem, jurnalizat; fără sold nu scrie nimic', function () {
    $ana = adlUser(80);

    expect(CreditLedger::forfeit($ana, 'cont șters'))->toBe(80.0);

    $tx = CreditTransaction::query()->where('type', CreditTransaction::ADJUSTMENT)->first();
    expect((float) $tx->amount)->toBe(-80.0)->and($tx->source)->toBe(CreditTransaction::SOURCE_SYSTEM)->and($tx->sourceLabel())->toBe('Sistem')
        ->and((float) $ana->fresh()->credit_balance)->toBe(0.0)
        ->and(AdminActivityLog::where('action', 'credits.forfeited')->exists())->toBeTrue();

    expect(CreditLedger::forfeit($ana->fresh(), 'iar'))->toBe(0.0)->and(CreditTransaction::query()->where('type', CreditTransaction::ADJUSTMENT)->count())->toBe(1);
});

// ---- Rezumat -------------------------------------------------------------------

it('rezumat: soldul, biletele valabile la petreceri neîncheiate și cardul de fidelitate', function () {
    $party = adlParty();
    $ana = adlUser(35.5);
    TicketOrders::place($ana, $party, 'Bilet', 2);
    LoyaltyCard::create(['participant_id' => $ana->id, 'stamps_required' => 10, 'status' => LoyaltyCard::ACTIVE]);

    $s = AccountDeletion::summary($ana->fresh());
    expect($s['balance'])->toBe(35.5)->and($s['tickets'])->toBe(2)->and($s['loyalty_stamps'])->toBe(0)->and($s['loyalty_required'])->toBe(10);

    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));   // petrecerea s-a încheiat
    expect(AccountDeletion::summary($ana->fresh())['tickets'])->toBe(0);
});

// ---- Ștergerea -----------------------------------------------------------------

it('parolă greșită: nu se șterge nimic; după 5 încercări greșite se blochează, chiar și cu parola corectă', function () {
    $ana = adlUser(10);
    RateLimiter::clear('delete-account:'.$ana->id);

    foreach (range(1, 5) as $i) {
        expect(fn () => AccountDeletion::delete($ana->fresh(), 'gresita'))->toThrow(DomainException::class, 'Parola nu este corectă.');
    }
    expect(fn () => AccountDeletion::delete($ana->fresh(), 'parola-sigura'))->toThrow(DomainException::class, 'Prea multe încercări');

    $ana->refresh();
    expect($ana->isAnonymized())->toBeFalse()->and((float) $ana->credit_balance)->toBe(10.0);
});

it('ștergere: anonimizare, credite pierdute, telefon liber, încărcări în așteptare anulate, bilete valabile rămân fără deținător, intrările rămân', function () {
    $party = adlParty();
    PaymentMethods::setCreditsPurchasable(true);
    $ana = adlUser(60);
    $order = TicketOrders::place($ana, $party, 'Bilet', 1);
    $entry = adlEntry($ana, $party);
    PartyInterest::create(['participant_id' => $ana->id, 'party_id' => $party->id]);
    $topup = CreditTopups::start($ana, 40);
    $sms = adlSms();

    AccountDeletion::delete($ana->fresh(), 'parola-sigura', $sms);

    $ana->refresh();
    expect($ana->isAnonymized())->toBeTrue()->and($ana->name)->toBe(Participant::ANONYMIZED_NAME)->and($ana->phone)->toBeNull()
        ->and($ana->password)->toBeNull()->and((float) $ana->credit_balance)->toBe(0.0);
    expect(CreditTransaction::query()->where('participant_id', $ana->id)->where('source', CreditTransaction::SOURCE_SYSTEM)->sum('amount'))->toEqual(-60);
    expect($topup->fresh()->status)->toBe(CreditTopup::CANCELLED);
    expect(PartyInterest::where('participant_id', $ana->id)->exists())->toBeFalse();

    $ticket = $order->tickets()->first();
    expect($ticket->status)->toBe(Ticket::VALID)->and($ticket->holder_participant_id)->toBeNull()->and($ticket->holder_phone)->toBeNull()
        ->and(Participant::query()->where('phone', '+40722123123')->exists())->toBeFalse();
    expect($sms->sent)->toHaveCount(1)->and($sms->sent[0][0])->toBe('+40722123123')->and($sms->sent[0][1])->toContain('rămâne valabil')
        ->and($sms->sent[0][1])->toContain('/c/'.AccountDeletion::ticketsCode($ana->fresh()));
    $this->get(route('app.deleted-tickets', ['code' => AccountDeletion::ticketsCode($ana->fresh())]))->assertOk()->assertSee($ticket->qrPayload(), false);

    $log = AdminActivityLog::where('action', 'participants.self_deleted')->first();
    expect($log)->not->toBeNull()->and($log->actor_label)->toBe('Sistem');

    // telefonul e liber: cont nou, fără bilete
    expect(ParticipantRegistry::create('Ana Nouă', '0722123123')->phone)->toBe('+40722123123');
});

it('ștergere fără bilete valabile: telefonul se eliberează și nu pleacă niciun SMS', function () {
    $ana = adlUser(0, 'Fără bilete', '0722777888');
    $sms = adlSms();

    AccountDeletion::delete($ana->fresh(), 'parola-sigura', $sms);

    expect($sms->sent)->toBe([])->and(Participant::query()->where('phone', '+40722777888')->exists())->toBeFalse()
        ->and(ParticipantRegistry::create('Nou', '0722777888')->phone)->toBe('+40722777888');
});

it('anonimizarea din admin detașează și ea deținătorul biletelor valabile (nu mai blochează intrarea)', function () {
    $party = adlParty();
    $ana = adlUser();
    $t = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets()->first();
    expect($t->holder_participant_id)->toBe($ana->id);

    ParticipantRegistry::anonymize($ana);

    expect($t->fresh()->holder_participant_id)->toBeNull()->and(AdminActivityLog::where('action', 'participants.anonymized')->exists())->toBeTrue();
});

// ---- Pagina --------------------------------------------------------------------

it('pagina „Șterge contul” arată cifrele reale și avertismentele', function () {
    $party = adlParty();
    $ana = adlUser(42);
    TicketOrders::place($ana, $party, 'Bilet', 1);

    $this->actingAs($ana, 'participant');
    Livewire::test(DeleteAccount::class)
        ->assertSeeHtml('data-delete-credit-warning')->assertSeeHtml('data-delete-ticket-warning')
        ->assertSee('42 lei')->assertSeeHtml('data-delete-confirm')->assertSeeHtml('data-delete-cancel');
});

it('pagina: fără bifă sau cu parola greșită nu șterge; cu ambele șterge, deconectează și întoarce Acasă', function () {
    $ana = adlUser(5);
    $this->actingAs($ana, 'participant');

    Livewire::test(DeleteAccount::class)->set('password', 'parola-sigura')->call('delete')->assertSee('Bifează că ai înțeles')->assertNoRedirect();
    expect($ana->fresh()->isAnonymized())->toBeFalse();

    Livewire::test(DeleteAccount::class)->set('understood', true)->set('password', 'gresita')->call('delete')->assertSee('Parola nu este corectă.')->assertNoRedirect();
    expect($ana->fresh()->isAnonymized())->toBeFalse();

    Livewire::test(DeleteAccount::class)->set('understood', true)->set('password', 'parola-sigura')->call('delete')->assertRedirect(route('app.home'));
    expect($ana->fresh()->isAnonymized())->toBeTrue();
    $this->assertGuest('participant');
});

it('pagina cere cont; Contul are linkurile „Descarcă datele” și „Șterge contul”', function () {
    $this->get(route('app.account.delete'))->assertRedirect(route('app.login'));

    $this->actingAs(adlUser(), 'participant')->get(route('app.account'))
        ->assertSee('data-account-export', false)->assertSee('data-account-delete', false);
});

it('o sesiune rămasă deschisă pe alt dispozitiv nu mai funcționează după ștergere', function () {
    $ana = adlUser();
    $this->actingAs($ana, 'participant');
    ParticipantRegistry::anonymize($ana);

    $this->get(route('app.account'))->assertRedirect(route('app.home'));
    $this->assertGuest('participant');
});

// ---- Export --------------------------------------------------------------------

it('export: JSON cu datele proprii, fără datele altor persoane', function () {
    $party = adlParty();
    $ana = adlUser(30);
    $bob = adlUser(0, 'Bob Prieten', '0733111222');
    TicketOrders::place($ana, $party, 'Bilet', 2);
    adlEntry($ana, $party);

    $res = $this->actingAs($ana, 'participant')->get(route('app.account.export'));
    $res->assertOk()->assertHeader('content-disposition');
    $data = json_decode($res->streamedContent(), true);

    expect($data['profile']['name'])->toBe('Ana Ștearsă')->and($data['profile']['phone'])->toBe('+40722123123')
        ->and($data['credit_balance'])->toEqual(30)
        ->and($data['entries'])->toHaveCount(1)->and($data['orders'])->toHaveCount(1)->and($data['tickets'])->toHaveCount(2)
        ->and($data['credit_transactions'])->toHaveCount(1);
    expect(json_encode($data))->not->toContain('Bob Prieten')->and(json_encode($data))->not->toContain('0733111222');
    expect(AccountExport::build($bob)['orders'])->toBe([]);
});

it('export: cere cont', function () {
    $this->get(route('app.account.export'))->assertRedirect(route('app.login'));
});

// ---- Export + teste diverse ----

it('butonul „Șterge contul” e dezactivat până se bifează „am înțeles”', function () {
    $this->actingAs(adlUser(), 'participant');

    Livewire::test(DeleteAccount::class)
        ->assertSeeHtmlInOrder(['disabled', 'data-delete-confirm'])
        ->set('understood', true)
        ->assertDontSeeHtml('disabled wire:loading');
});

it('SMS-urile pleacă fără diacritice', function () {
    $inner = adlSms();
    (new AsciiSmsSender($inner))->send('+40700', 'Ți-am șters contul „Ăla” – în țară, știi?');

    expect($inner->sent[0][1])->toBe('Ti-am sters contul "Ala" - in tara, stii?');
});

it('linkul scurt: cod de 16 caractere, se stinge când biletul își schimbă deținătorul, cod greșit = 404', function () {
    $party = adlParty();
    $ana = adlUser();
    $ticket = TicketOrders::place($ana, $party, 'Bilet', 1)->tickets()->first();
    $code = TicketTransfers::shortCode($ticket);

    expect($code)->toHaveLength(16)->and(TicketTransfers::findByShortCode($code)?->id)->toBe($ticket->id);
    expect(strlen(TicketTransfers::linkFor($ticket)))->toBeLessThan(45);
    $this->get(route('app.ticket-short', ['code' => '0000000000000000']))->assertNotFound();

    $ticket->forceFill(['holder_participant_id' => null])->save();
    expect(TicketTransfers::findByShortCode($code))->toBeNull();
});

it('biletul trimis deja altcuiva nu mai e al meu: nu e numărat și nu intră în SMS-ul de la ștergerea contului', function () {
    $party = adlParty();
    $ana = adlUser(0, 'Ana Cumpără', '0722999000');
    $order = TicketOrders::place($ana, $party, 'Bilet', 3);
    TicketTransfers::send($order->tickets[2]->id, $ana, '0744000111', adlSms());   // către un număr fără cont

    expect(AccountDeletion::summary($ana->fresh())['tickets'])->toBe(2);
    AccountDeletion::delete($ana->fresh(), 'parola-sigura', $sms = adlSms());

    expect($sms->sent)->toHaveCount(1)->and($sms->sent[0][1])->toContain('cele 2 bilete');
    $code = AccountDeletion::ticketsCode($ana->fresh());
    $html = $this->get(route('app.deleted-tickets', ['code' => $code]))->assertOk()->getContent();
    expect(substr_count($html, 'data-ticket-qr='))->toBe(2)->and($html)->not->toContain($order->tickets[2]->qrPayload());
});

it('pagina /c/<cod>: 404 pentru cod greșit sau cont nesters; fără bilete valabile arată mesaj', function () {
    $ana = adlUser();
    $this->get(route('app.deleted-tickets', ['code' => AccountDeletion::ticketsCode($ana)]))->assertNotFound();   // contul nu e șters
    $this->get(route('app.deleted-tickets', ['code' => '0000000000000000']))->assertNotFound();

    AccountDeletion::delete($ana->fresh(), 'parola-sigura', adlSms());
    $this->get(route('app.deleted-tickets', ['code' => AccountDeletion::ticketsCode($ana->fresh())]))->assertOk()->assertSee('Nu mai sunt bilete valabile');
});

it('limita codului per participant: o comandă = o folosire, oricâte bilete; a doua comandă nu mai primește reducere', function () {
    $party = adlParty();
    $code = PartyDiscountCode::create(['party_id' => $party->id, 'code' => 'UNA', 'type' => 'percent', 'value' => 50, 'max_uses_per_participant' => 1, 'is_active' => true]);
    $ana = adlUser();

    $order = TicketOrders::place($ana, $party, 'Bilet', 4, 'UNA');
    expect($order->tickets)->toHaveCount(4)->and((float) $order->discount_total)->toBe(100.0)->and($code->usesBy($ana->id))->toBe(1);

    expect(fn () => TicketOrders::place($ana, $party, 'Bilet', 1, 'UNA'))->toThrow(DomainException::class, 'Ai folosit deja acest cod');
});
