<?php

use App\Livewire\Admin\Loyalty\Cards as LoyaltyCardsPage;
use App\Livewire\Admin\Participants\Index as ParticipantsIndex;
use App\Livewire\Admin\Participants\Show as ParticipantsShow;
use App\Models\Admin;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyStamp;
use App\Models\Party;
use App\Models\ReceptionSession;
use App\Services\EntryRecorder;
use App\Services\LoyaltyLedger;
use App\Services\ParticipantRegistry;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function loyAdmin(): Admin
{
    return Admin::create([
        'name' => 'Loyalty Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Setează fidelitatea globală activă, cu X ștampile (implicit 3, ca testele să fie scurte). */
function loySettings(int $required = 3): void
{
    Settings::set('loyalty_enabled', true);
    Settings::set('loyalty_stamps_required', $required);
}

/** Petrecere 3.10.2026, publicată, cu bilet de 30 lei; $eligible controlează loyalty_eligible. */
function loyParty(bool $eligible = true, array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere fidelitate',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'ticket_types' => [['name' => 'Bilet', 'price' => 30]],
        'loyalty_eligible' => $eligible,
    ], $overrides));
}

// ---- LoyaltyLedger: înrolare ------------------------------------------------

it('inroleaza un participant, cu card activ si X-ul curent din setari', function () {
    loySettings(5);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Ion Fidel', '0722 111 000');

    expect($p->isLoyaltyEnrolled())->toBeFalse();

    $card = LoyaltyLedger::enroll($p, $admin->id);

    expect($card->stamps_required)->toBe(5)
        ->and($card->status)->toBe(LoyaltyCard::ACTIVE)
        ->and($p->fresh()->isLoyaltyEnrolled())->toBeTrue()
        ->and(LoyaltyLedger::activeCard($p->fresh())->id)->toBe($card->id);
});

it('nu permite reinrolarea unui participant deja inrolat', function () {
    loySettings();
    $p = ParticipantRegistry::create('Ana Fidel', '0722 111 001');
    LoyaltyLedger::enroll($p, loyAdmin()->id);

    expect(fn () => LoyaltyLedger::enroll($p->fresh(), loyAdmin()->id))
        ->toThrow(DomainException::class, 'deja înrolat');
});

it('la inrolare, preia stampile de pe un card fizic (0 pana la X-1)', function () {
    loySettings(4);
    $p = ParticipantRegistry::create('Mara Fidel', '0722 111 002');

    $card = LoyaltyLedger::enroll($p, loyAdmin()->id, 3);

    expect($card->activeStampsCount())->toBe(3)
        ->and(LoyaltyStamp::where('loyalty_card_id', $card->id)->first()->source)->toBe(LoyaltyStamp::SOURCE_MANUAL_ADJUST);

    // Nu poate porni deja complet (4 ceruri + 1 gratis = 5; max admis la inrolare e 3).
    $p2 = ParticipantRegistry::create('Alt Fidel', '0722 111 003');
    expect(fn () => LoyaltyLedger::enroll($p2, loyAdmin()->id, 4))
        ->toThrow(DomainException::class, 'între 0 și 3');
});

// ---- LoyaltyLedger: stampilare + completare + ajustare -----------------------

it('completeaza si arhiveaza cardul dupa X+1 stampile, si porneste automat unul nou', function () {
    loySettings(3);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Bogdan Fidel', '0722 111 004');
    LoyaltyLedger::enroll($p, $admin->id);

    LoyaltyLedger::adjustStamps($p->fresh(), 4, 'test', $admin->id); // 3 + 1 gratis = complet

    expect(LoyaltyCard::where('participant_id', $p->id)->count())->toBe(2);

    $completed = LoyaltyCard::where('participant_id', $p->id)->where('status', LoyaltyCard::COMPLETED)->first();
    expect($completed->activeStampsCount())->toBe(4)
        ->and($completed->completed_at)->not->toBeNull();

    $active = LoyaltyLedger::activeCard($p->fresh());
    expect($active->id)->not->toBe($completed->id)
        ->and($active->activeStampsCount())->toBe(0)
        ->and($active->stamps_required)->toBe(3);
});

it('readyForFreeEntry e adevarat doar cand mai lipseste exact ultimul cerc', function () {
    loySettings(3);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Costin Fidel', '0722 111 005');
    LoyaltyLedger::enroll($p, $admin->id);

    expect(LoyaltyLedger::readyForFreeEntry($p->fresh()))->toBeFalse();

    LoyaltyLedger::adjustStamps($p->fresh(), 3, 'test', $admin->id);
    expect(LoyaltyLedger::readyForFreeEntry($p->fresh()))->toBeTrue();

    LoyaltyLedger::adjustStamps($p->fresh(), 1, 'test', $admin->id); // completeaza
    expect(LoyaltyLedger::readyForFreeEntry($p->fresh()))->toBeFalse(); // cardul nou, gol
});

it('ajusteaza stampile cu semn, valideaza motiv si limite', function () {
    loySettings(3);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Dana Fidel', '0722 111 006');

    expect(fn () => LoyaltyLedger::adjustStamps($p, 1, 'motiv'))->toThrow(DomainException::class, 'nu e înrolat');

    LoyaltyLedger::enroll($p, $admin->id);
    $p->refresh();

    expect(fn () => LoyaltyLedger::adjustStamps($p, 0, 'motiv'))->toThrow(DomainException::class);
    expect(fn () => LoyaltyLedger::adjustStamps($p, 2, ''))->toThrow(DomainException::class, 'motiv obligatoriu');

    LoyaltyLedger::adjustStamps($p, 2, 'am vazut cardul fizic', $admin->id);
    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(2);

    // Nu poate scoate mai multe decat sunt pe cardul activ.
    expect(fn () => LoyaltyLedger::adjustStamps($p->fresh(), -5, 'corectie'))
        ->toThrow(DomainException::class, 'doar 2');

    LoyaltyLedger::adjustStamps($p->fresh(), -2, 'corectie', $admin->id);
    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(0);
});

// ---- Integrare cu EntryRecorder ------------------------------------------------

it('stampileaza automat la o intrare identificata pe o petrecere eligibila', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Elena Fidel', '0722 111 007');
    LoyaltyLedger::enroll($p, $admin->id);

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);

    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(1);
});

it('nu stampileaza pe o petrecere nu-eligibila, chiar daca participantul e inrolat', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(false);
    $p = ParticipantRegistry::create('Filip Fidel', '0722 111 008');
    LoyaltyLedger::enroll($p, $admin->id);

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);

    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(0);
});

it('nu stampileaza o intrare anonima (fara participant ales), chiar pe petrecere eligibila', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false);

    expect(LoyaltyStamp::count())->toBe(0);
});

it('metoda Beneficiu nu e acceptata la intrare pe o petrecere care nu acorda fidelitate', function () {
    loySettings(3);
    $party = loyParty(false);

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [['method' => PaymentMethods::BENEFIT, 'amount' => 30]], enforceState: false))
        ->toThrow(DomainException::class, 'nu este acceptată');
});

it('metoda Beneficiu e acceptata la intrare pe o petrecere eligibila, fara sa fie nevoie de participant (card fizic)', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);

    $entries = EntryRecorder::record($party, 'Bilet', 1, [['method' => PaymentMethods::BENEFIT, 'amount' => 30]], adminId: $admin->id, enforceState: false);

    expect($entries->first()->payments()->first()->method)->toBe(PaymentMethods::BENEFIT)
        ->and(LoyaltyStamp::count())->toBe(0); // fara participant identificat, nu se stampileaza
});

it('anularea unei intrari anuleaza si stampila pe care a produs-o', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Gabi Fidel', '0722 111 009');
    LoyaltyLedger::enroll($p, $admin->id);

    $entries = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);
    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(1);

    EntryRecorder::cancelBatch($entries->first()->batch, 'Greșeală la intrare', $admin->id);

    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(0);
    expect(LoyaltyStamp::first()->isVoided())->toBeTrue();
});

it('anularea intrarii care a completat cardul il redeschide, daca e inca ultimul card al participantului', function () {
    loySettings(2); // un card se completeaza cu 3 stampile (2 + 1 gratis)
    $admin = loyAdmin();
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Horia Fidel', '0722 111 010');
    // Preia 1 ștampilă la înrolare (card fizic), ca să fie nevoie de o singură intrare reală până la „gata".
    LoyaltyLedger::enroll($p, $admin->id, 1);

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);
    // Aceeași sesiune de recepție nu permite reintrarea aceluiași participant: închidem casa între cele
    // două intrări, ca la o seară nouă (nu ține de fidelitate, e regula de dedup de la Recepție).
    ReceptionSession::currentFor($party->id)->close();
    $free = EntryRecorder::record($party, 'Bilet', 1, [['method' => PaymentMethods::BENEFIT, 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);

    expect(LoyaltyCard::where('participant_id', $p->id)->where('status', LoyaltyCard::COMPLETED)->count())->toBe(1);
    $newActiveBefore = LoyaltyLedger::activeCard($p->fresh());
    expect($newActiveBefore->activeStampsCount())->toBe(0);

    EntryRecorder::cancelBatch($free->first()->batch, 'Greșeală', $admin->id);

    // Cardul gol creat la completare se șterge, iar cel completat redevine activ (era ultimul; nu primise
    // nicio ștampilă între timp).
    expect(LoyaltyCard::where('id', $newActiveBefore->id)->exists())->toBeFalse();
    expect(LoyaltyCard::where('participant_id', $p->id)->count())->toBe(1);
    expect(LoyaltyCard::where('participant_id', $p->id)->where('status', LoyaltyCard::ACTIVE)->count())->toBe(1);

    $active = LoyaltyLedger::activeCard($p->fresh());
    expect($active->activeStampsCount())->toBe(2); // au ramas cele 2 stampile anterioare, doar cea gratis s-a anulat
});

it('nu acorda stampila la o intrare gratuita (pret 0), chiar pe petrecere eligibila cu participant identificat', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Ioana Fidel', '0722 111 011');
    LoyaltyLedger::enroll($p, $admin->id);

    EntryRecorder::record(
        $party,
        'Bilet',
        1,
        [],
        overridePrice: 0,
        overrideReason: 'intrare gratuită până la ora X',
        adminId: $admin->id,
        enforceState: false,
        participants: [$p->id],
    );

    expect(LoyaltyLedger::activeCard($p->fresh())->activeStampsCount())->toBe(0);
    expect(LoyaltyStamp::count())->toBe(0);
});

// ---- Batch: o ajustare/inrolare cu N stampile = un singur grup in istoric -----------

it('ajustarea manuala pozitiva de mai multe stampile deodata are un singur batch', function () {
    loySettings(10);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Radu Fidel', '0722 111 012');
    LoyaltyLedger::enroll($p, $admin->id);

    LoyaltyLedger::adjustStamps($p->fresh(), 5, 'preluare card fizic', $admin->id);

    $stamps = LoyaltyStamp::where('source', LoyaltyStamp::SOURCE_MANUAL_ADJUST)->get();
    expect($stamps)->toHaveCount(5)
        ->and($stamps->first()->batch)->not->toBeNull()
        ->and($stamps->pluck('batch')->unique())->toHaveCount(1);
});

it('stampilele preluate la inrolare (card fizic) au acelasi batch', function () {
    loySettings(10);
    $admin = loyAdmin();
    $p = ParticipantRegistry::create('Sara Fidel', '0722 111 013');
    $card = LoyaltyLedger::enroll($p, $admin->id, 4);

    $stamps = $card->stamps()->get();
    expect($stamps)->toHaveCount(4)
        ->and($stamps->first()->batch)->not->toBeNull()
        ->and($stamps->pluck('batch')->unique())->toHaveCount(1);
});

it('stampilele automate de la intrari nu au batch (raman randuri separate in istoric)', function () {
    loySettings(3);
    $admin = loyAdmin();
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Teodor Fidel', '0722 111 014');
    LoyaltyLedger::enroll($p, $admin->id);

    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);

    $stamp = LoyaltyStamp::where('source', LoyaltyStamp::SOURCE_ENTRY)->firstOrFail();
    expect($stamp->batch)->toBeNull();
});

it('LoyaltyLedger::partyEligible cere si bifa pe petrecere si setarea globala activa', function () {
    Settings::set('loyalty_enabled', false);
    $party = loyParty(true);
    expect(LoyaltyLedger::partyEligible($party))->toBeFalse();

    Settings::set('loyalty_enabled', true);
    expect(LoyaltyLedger::partyEligible($party))->toBeTrue();

    $party2 = loyParty(false);
    expect(LoyaltyLedger::partyEligible($party2))->toBeFalse();
});

// ---- UI: fisa participantului -------------------------------------------------

it('fisa participantului: istoricul grupeaza ajustarea manuala intr-un singur rand, cu numele petrecerii si numarul cardului la intrari', function () {
    loySettings(3);
    $admin = loyAdmin();
    $this->actingAs($admin, 'admin');
    $party = loyParty(true);
    $p = ParticipantRegistry::create('Vlad Fidel', '0722 111 015');
    LoyaltyLedger::enroll($p, $admin->id);

    // O ajustare de +5 (peste X+1, deci completeaza cardul 1 si trece pe cardul 2) -> tot un singur batch,
    // deci un singur rand de "+5" in istoric, indiferent pe cate carduri "cad" stampilele.
    LoyaltyLedger::adjustStamps($p->fresh(), 2, 'preluare card fizic', $admin->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$p->id]);

    Livewire::test(ParticipantsShow::class, ['participant' => $p->fresh()])
        ->assertOk()
        ->assertSee('Ajustare manuală ×2')
        ->assertSee($party->name)
        ->assertSee('Card #1');
});

it('fisa participantului: popup de card (doar-afisare) din lista participanti', function () {
    loySettings(3);
    $admin = loyAdmin();
    $this->actingAs($admin, 'admin');
    $p = ParticipantRegistry::create('Zoe Fidel', '0722 111 016');
    LoyaltyLedger::enroll($p, $admin->id, 1);

    Livewire::test(ParticipantsIndex::class)
        ->assertSee('Zoe Fidel')
        ->call('showLoyaltyCard', $p->id)
        ->assertSee('Card de fidelitate')
        ->assertSee('#1')
        ->call('closeLoyaltyCard')
        ->assertSet('loyaltyPopupParticipantId', null);
});

// ---- UI: pagina Carduri (sumar global) -----------------------------------------

it('pagina Carduri: sumar global, „aproape gata” si istoric filtrabil', function () {
    loySettings(3);
    $admin = loyAdmin();
    $this->actingAs($admin, 'admin');
    $party = loyParty(true);

    $gata = ParticipantRegistry::create('Radu Aproape', '0722 111 017');
    LoyaltyLedger::enroll($gata, $admin->id, 2); // max admis la înrolare (required - 1)
    LoyaltyLedger::adjustStamps($gata->fresh(), 1, 'ultima ștampilă', $admin->id); // 3 din 3 — mai lipsește doar gratis

    $stampat = ParticipantRegistry::create('Elvira Stampata', '0722 111 018');
    LoyaltyLedger::enroll($stampat, $admin->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id, enforceState: false, participants: [$stampat->id]);

    $c = Livewire::test(LoyaltyCardsPage::class)
        ->assertOk()
        ->assertSee('Radu Aproape') // in lista "aproape gata"
        ->assertSee('Elvira Stampata') // in istoric, cu petrecerea
        ->assertSee($party->name);

    $c->set('filterSource', LoyaltyStamp::SOURCE_MANUAL_ADJUST)
        ->assertDontSee('Elvira Stampata');

    $this->get(route('admin.loyalty.cards'))->assertOk()->assertSee('Card de fidelitate');
});

it('pagina Carduri: link-ul din sidebar apare doar cat fidelitatea e activa global', function () {
    $admin = loyAdmin();
    $this->actingAs($admin, 'admin');

    Settings::set('loyalty_enabled', false);
    $this->get(route('admin.dashboard'))->assertDontSee('Carduri');

    loySettings();
    $this->get(route('admin.dashboard'))->assertSee('Carduri');
});
