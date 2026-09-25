<?php

use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Participants\Index as ParticipantsIndex;
use App\Livewire\Admin\Participants\Show as ParticipantsShow;
use App\Livewire\Admin\Reception\Form as ReceptionForm;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use App\Services\PartyStats;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function partAdmin(): Admin
{
    return Admin::create([
        'name' => 'Recepționer Part',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026, 21:00-03:00, bilet 30 lei; „acum” = 23:00 din prima zi. */
function partParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere participanți',
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
    ], $overrides));
}

function partEnter(Party $party, int $count = 1, array $participants = [], ?Carbon $at = null, bool $enforce = true): \Illuminate\Support\Collection
{
    return EntryRecorder::record($party, 'Bilet', $count, [['method' => 'cash', 'amount' => 30 * $count]], adminId: null, at: $at, enforceState: $enforce, participants: $participants);
}

it('normalizeaza telefoanele romanesti si internationale', function () {
    expect(Phone::normalize('0722 123 456'))->toBe('+40722123456')
        ->and(Phone::normalize('0722-123-456'))->toBe('+40722123456')
        ->and(Phone::normalize('+40 722 123 456'))->toBe('+40722123456')
        ->and(Phone::normalize('0040722123456'))->toBe('+40722123456')
        ->and(Phone::normalize('40722123456'))->toBe('+40722123456')
        ->and(Phone::normalize('722123456'))->toBe('+40722123456')
        ->and(Phone::normalize('+44 7700 900123'))->toBe('+447700900123')
        ->and(Phone::normalize('abc'))->toBeNull()
        ->and(Phone::normalize('123'))->toBeNull()
        ->and(Phone::normalize(''))->toBeNull()
        ->and(Phone::normalize(null))->toBeNull();
});

it('creeaza participanti cu telefon normalizat si uuid, fara dubluri dupa telefon', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');

    $p = ParticipantRegistry::create('  Ion   Popescu ', '0722 123 456', $admin->id);
    expect($p->name)->toBe('Ion Popescu')->and($p->phone)->toBe('+40722123456')->and($p->uuid)->toHaveLength(36)
        ->and($p->source)->toBe('reception')->and($p->created_by)->toBe($admin->id);

    expect(fn () => ParticipantRegistry::create('Alt Nume', '+40 722-123-456'))->toThrow(DomainException::class, 'Ion Popescu');
    expect(fn () => ParticipantRegistry::create('A', '0722 111 222'))->toThrow(DomainException::class, 'minim 2');
    expect(fn () => ParticipantRegistry::create('Ana', '12'))->toThrow(DomainException::class, 'Telefonul');
    expect(Participant::count())->toBe(1);
    expect(AdminActivityLog::pluck('action')->all())->toContain('participants.created');
});

it('modifica un participant fara sa creeze conflicte de telefon', function () {
    $this->actingAs(partAdmin(), 'admin');
    $a = ParticipantRegistry::create('Ana Pop', '0722 111 111');
    $b = ParticipantRegistry::create('Bogdan Ilie', '0722 222 222');

    ParticipantRegistry::update($a, 'Ana Maria Pop', '0733 333 333');
    expect($a->fresh()->name)->toBe('Ana Maria Pop')->and($a->fresh()->phone)->toBe('+40733333333');

    expect(fn () => ParticipantRegistry::update($a, 'Ana', '0722 222 222'))->toThrow(DomainException::class, 'Bogdan Ilie');
    ParticipantRegistry::update($b, 'Bogdan Ilie', '0722 222 222'); // acelasi telefon, al lui: OK
    expect(AdminActivityLog::pluck('action')->all())->toContain('participants.updated');
});

it('cauta dupa nume sau telefon in orice forma, fara anonimizati si fara cei exclusi', function () {
    $this->actingAs(partAdmin(), 'admin');
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');
    $gone = ParticipantRegistry::create('Ion Șters', '0744 000 111');
    ParticipantRegistry::anonymize($gone);

    $names = fn (string $q, array $ex = []) => ParticipantRegistry::search($q, 6, $ex)->pluck('name')->all();

    expect($names('ion'))->toBe(['Ana Ionescu', 'Ion Popescu'])     // in nume, ordonat
        ->and($names('0722 123'))->toBe(['Ion Popescu'])
        ->and($names('722123'))->toBe(['Ion Popescu'])
        ->and($names('+40733'))->toBe(['Ana Ionescu'])
        ->and($names('987654'))->toBe(['Ana Ionescu'])
        ->and($names('ion', [$ion->id]))->toBe(['Ana Ionescu'])
        ->and($names('i'))->toBe([])                                  // minim 2 caractere
        ->and($names('100%'))->toBe([])                               // wildcard-urile se escapeaza
        ->and($names('Șters'))->toBe([]);                             // anonimizatii nu apar
});

it('sterge doar participanti fara intrari; altfel ii anonimizeaza si pastreaza numaratoarea', function () {
    $this->actingAs(partAdmin(), 'admin');
    $party = partParty();

    $free = ParticipantRegistry::create('Fără Intrări', '0722 000 001');
    $used = ParticipantRegistry::create('Cu Intrări', '0722 000 002');
    partEnter($party, 1, [$used->id]);

    expect(fn () => ParticipantRegistry::delete($used))->toThrow(DomainException::class, 'anonimizezi');
    ParticipantRegistry::delete($free);
    expect(Participant::find($free->id))->toBeNull();

    ParticipantRegistry::anonymize($used);
    $used->refresh();
    expect($used->name)->toBe('Participant șters')->and($used->phone)->toBeNull()->and($used->isAnonymized())->toBeTrue()
        ->and(PartyEntry::where('participant_id', $used->id)->count())->toBe(1);     // istoricul ramane ca numar
    expect(fn () => ParticipantRegistry::anonymize($used))->toThrow(DomainException::class, 'deja');
    expect(fn () => ParticipantRegistry::update($used, 'Nume', '0722 999 999'))->toThrow(DomainException::class, 'anonimizat');
    expect(AdminActivityLog::pluck('action')->all())->toContain('participants.deleted', 'participants.anonymized');
});

it('leaga participantii de intrari si numara intrarile lor', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion', '0722 111 111');
    $ana = ParticipantRegistry::create('Ana', '0722 222 222');

    $entries = partEnter($party, 3, [$ion->id, $ana->id]);   // al treilea ramane anonim
    expect($entries->pluck('participant_id')->all())->toBe([$ion->id, $ana->id, null]);

    expect(ParticipantRegistry::visitCounts([$ion->id, $ana->id]))->toBe([$ion->id => 1, $ana->id => 1]);
    expect(AdminActivityLog::where('action', 'entries.recorded')->first()->description)->toContain('2 identificați');
});

it('valideaza participantii la intrare: numar, dubluri, existenta, anonimizati', function () {
    $this->actingAs(partAdmin(), 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion', '0722 111 111');
    $gone = ParticipantRegistry::create('Șters', '0722 333 333');
    ParticipantRegistry::anonymize($gone);

    expect(fn () => partEnter($party, 1, [$ion->id, 999]))->toThrow(DomainException::class, 'Ai ales 2 participanți pentru 1 persoană');
    expect(fn () => partEnter($party, 2, [$ion->id, $ion->id]))->toThrow(DomainException::class, 'de două ori');
    expect(fn () => partEnter($party, 1, [999]))->toThrow(DomainException::class, 'nu există');
    expect(fn () => partEnter($party, 1, [$gone->id]))->toThrow(DomainException::class, 'anonimizat');
    expect(PartyEntry::count())->toBe(0);
});

it('blocheaza a doua intrare a aceluiasi participant in aceeasi sesiune, dar nu o intrare anulata, alta petrecere sau o sesiune noua', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 111 111');

    $first = partEnter($party, 1, [$ion->id]);
    expect(fn () => partEnter($party, 1, [$ion->id]))->toThrow(DomainException::class, 'Ion Popescu” a intrat deja în această sesiune de recepție, la 23:00');

    // Anulata: nu mai blocheaza.
    EntryRecorder::cancelBatch($first->first()->batch, 'Greșeală', $admin->id);
    expect(partEnter($party, 1, [$ion->id]))->toHaveCount(1);

    // Alta petrecere: permis.
    $other = partParty(['name' => 'Altă petrecere']);
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:30'));
    expect(partEnter($other, 1, [$ion->id]))->toHaveCount(1);

    // Festival: seara urmatoare e tot aceeasi sesiune cat timp casa nu s-a inchis -> blocat;
    // dupa inchiderea casei e o sesiune noua -> permis.
    $festival = partParty(['name' => 'Festival', 'kind' => 'festival', 'end_date' => '2026-10-05']);
    partEnter($festival, 1, [$ion->id], Carbon::parse('2026-10-03 22:00'), false);
    Carbon::setTestNow(Carbon::parse('2026-10-04 22:30'));
    expect(fn () => partEnter($festival, 1, [$ion->id], Carbon::parse('2026-10-04 22:30'), false))
        ->toThrow(DomainException::class, 'a intrat deja în această sesiune');
    ReceptionSession::currentFor($festival->id)->close();
    expect(partEnter($festival, 1, [$ion->id], Carbon::parse('2026-10-04 22:30'), false))->toHaveCount(1);
    expect(ParticipantRegistry::visitCounts([$ion->id]))->toBe([$ion->id => 4]);
});

it('recepția: cauta, adauga si scoate participanti, iar numarul de persoane creste automat', function () {
    $this->actingAs(partAdmin(), 'admin');
    partParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');

    $c = Livewire::test(ReceptionForm::class)
        ->set('participantSearch', '0722 123')
        ->assertSee('Ion Popescu')
        ->assertDontSee('Ana Ionescu');

    $c->call('addParticipant', $ion->id)
        ->assertSet('participantIds', [$ion->id])
        ->assertSet('participantSearch', '')
        ->assertSet('count', 1)
        ->assertSee('1 din 1 identificați');

    $c->call('addParticipant', $ana->id)->assertSet('count', 2)->assertSee('2 din 2 identificați');   // cresc persoanele
    $c->call('addParticipant', $ana->id)->assertSet('participantIds', [$ion->id, $ana->id]);           // fara dubluri
    $c->call('stepCount', -1)->assertSet('count', 2);                                                   // nu scade sub participanti

    $c->call('removeParticipant', $ana->id)->assertSet('participantIds', [$ion->id]);
    $c->set('participantSearch', 'zzz')->assertSee('Niciun participant găsit.');
});

it('recepția: participant nou (nume + telefon) preluat din cautare, cu erori de validare', function () {
    $this->actingAs(partAdmin(), 'admin');
    partParty();
    ParticipantRegistry::create('Existent', '0722 000 000');

    $c = Livewire::test(ReceptionForm::class)
        ->set('participantSearch', '0744 555 666')
        ->call('toggleNewParticipant')
        ->assertSet('newParticipant', true)
        ->assertSet('newPhone', '0744 555 666');

    $c->set('newName', 'A')->call('createParticipant')->assertSet('participantError', fn ($e) => str_contains($e, 'minim 2'));
    $c->set('newName', 'Maria Dumitru')->set('newPhone', '0722 000 000')->call('createParticipant')
        ->assertSet('participantError', fn ($e) => str_contains($e, 'Existent'));

    $c->set('newPhone', '0744 555 666')->call('createParticipant')
        ->assertSet('participantError', null)
        ->assertSet('newParticipant', false)
        ->assertSet('newName', '');

    $maria = Participant::where('phone', '+40744555666')->firstOrFail();
    expect($c->get('participantIds'))->toBe([$maria->id]);
});

it('recepția: inregistreaza intrarea cu participanti, ii arata in lista si goleste selectia', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $ana = ParticipantRegistry::create('Ana Ionescu', '0733 987 654');

    Livewire::test(ReceptionForm::class)
        ->call('addParticipant', $ion->id)->call('addParticipant', $ana->id)
        ->call('payAll', 'cash')
        ->call('save')
        ->assertSet('error', null)
        ->assertSet('participantIds', [])
        ->assertSet('count', 1)
        ->assertSee('Ion Popescu, Ana Ionescu');

    expect(PartyEntry::orderBy('id')->pluck('participant_id')->all())->toBe([$ion->id, $ana->id]);

    // Acelasi participant din nou: chip cu avertisment si eroare la salvare.
    Livewire::test(ReceptionForm::class)
        ->call('addParticipant', $ion->id)
        ->assertSee('a intrat deja în sesiunea curentă, la 23:00')
        ->call('payAll', 'cash')
        ->call('save')
        ->assertSet('error', fn ($e) => str_contains($e, 'a intrat deja'));
    expect(PartyEntry::count())->toBe(2);
});

it('pagina Participanți: listeaza, cauta, adauga si arata numarul de intrari', function () {
    $this->actingAs(partAdmin(), 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    ParticipantRegistry::create('Ana Ionescu', '0733 987 654');
    partEnter($party, 1, [$ion->id]);

    $c = Livewire::test(ParticipantsIndex::class)
        ->assertSee('2 participanți identificați')
        ->assertSee('Ion Popescu')->assertSee('Ana Ionescu')
        ->set('search', '0722')
        ->assertSee('Ion Popescu')->assertDontSee('Ana Ionescu')
        ->set('search', 'nimeni')
        ->assertSee('Niciun participant nu se potrivește căutării.');

    $c->set('search', '')->call('toggleAdd')
        ->set('newName', 'Maria Dumitru')->set('newPhone', '0744 555 666')->call('create')
        ->assertSet('error', null)->assertSet('adding', false)
        ->assertSee('Maria Dumitru');
    $c->set('newName', 'Xavier')->set('newPhone', '0744 555 666')->call('create')->assertSet('error', fn ($e) => str_contains($e, 'Maria Dumitru'));

    $this->get(route('admin.participants.index'))->assertOk()->assertSee('Participanți');
});

it('fisa participantului: date, istoric, editare, anonimizare si stergere', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion Popescu', '0722 123 456');
    $entries = partEnter($party, 1, [$ion->id]);
    $lonely = ParticipantRegistry::create('Fără Intrări', '0722 000 001');

    Livewire::test(ParticipantsShow::class, ['participant' => $ion])
        ->assertSee('Ion Popescu')->assertSee('+40722123456')
        ->assertSee('Petrecere participanți')->assertSee('30,00 lei')
        ->set('name', 'Ion P. Popescu')->set('phone', '0722 999 888')->call('save')
        ->assertSet('message', 'Datele au fost salvate.')
        ->assertSet('phone', '+40722999888')
        ->call('delete')->assertSet('error', fn ($e) => str_contains($e, 'anonimizezi'))
        ->call('anonymize')->assertSet('message', 'Participantul a fost anonimizat.')
        ->assertSee('Participant șters');
    expect(PartyEntry::where('participant_id', $ion->id)->count())->toBe(1);

    Livewire::test(ParticipantsShow::class, ['participant' => $lonely])
        ->assertSee('poate fi șters complet')
        ->call('delete')->assertRedirect(route('admin.participants.index'));
    expect(Participant::find($lonely->id))->toBeNull();

    $this->get(route('admin.participants.show', $ion))->assertOk();
});

it('statisticile arata identificati si participanti unici, cu KPI', function () {
    $admin = partAdmin();
    $this->actingAs($admin, 'admin');
    $party = partParty();
    $ion = ParticipantRegistry::create('Ion', '0722 111 111');
    $ana = ParticipantRegistry::create('Ana', '0722 222 222');

    partEnter($party, 3, [$ion->id, $ana->id]);              // 2 identificati din 3
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:30'));      // casa s-a inchis: sesiune noua, acelasi participant iar
    ReceptionSession::currentFor($party->id)->close();
    $party->update(['status' => 'published']);
    partEnter($party, 1, [$ion->id], Carbon::parse('2026-10-04 12:30'), false);

    $a = PartyStats::attendance($party);
    expect($a->count)->toBe(4)->and($a->identified)->toBe(3)->and($a->unique_participants)->toBe(2);

    $component = Livewire::test(PartyStatsPage::class, ['party' => $party])->instance();
    $stats = PartyStats::for($party);
    expect($stats->entries_identified)->toBe(3)
        ->and(array_map(fn ($k) => $k->key, $component->kpis($stats, null)))->toContain('entries_identified');

    $this->get(route('admin.parties.stats', $party))->assertOk()->assertSee('Identificați')->assertSee('Participanți unici');
});

it('meniul are linkul Participanți', function () {
    $this->actingAs(partAdmin(), 'admin');

    $this->get(route('admin.participants.index'))->assertOk()->assertSee(route('admin.participants.index'));
});
