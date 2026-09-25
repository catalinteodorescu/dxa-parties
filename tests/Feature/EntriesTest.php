<?php

use App\Livewire\Admin\Parties\Index as PartiesIndex;
use App\Livewire\Admin\Parties\Stats as PartyStatsPage;
use App\Livewire\Admin\Reception\Form as ReceptionForm;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Services\EntryRecorder;
use App\Services\PartyStats;
use App\Support\PaymentMethods;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function entryAdmin(): Admin
{
    return Admin::create([
        'name' => 'Recepționer Test',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/**
 * Petrecere 3.10.2026, 21:00-03:00, publicata. Bilet 30 lei, cu „gratuit pana la 22:30\" (limita cu ora) si
 * un bilet VIP 50 lei. `$methods` = lista de metode a petrecerii.
 */
function entryParty(?array $methods = null, array $overrides = []): Party
{
    return Party::create(array_merge([
        'name' => 'Petrecere intrări',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => $methods,
        'ticket_types' => [
            ['name' => 'Bilet', 'price' => 30, 'discounts' => [['label' => 'Gratuit până la 22:30', 'price' => 0, 'until' => '2026-10-03T22:30']]],
            ['name' => 'VIP', 'price' => 50, 'discounts' => []],
        ],
    ], $overrides));
}

function entryNow(string $time): void
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 '.$time.':00'));
}

it('calculeaza pretul la un moment dat, cu toleranta doar pe reducerile cu ora', function () {
    $party = entryParty();
    $type = $party->ticket_types[0];

    $at = fn ($t) => Carbon::parse('2026-10-03 '.$t);

    expect($party->priceForTypeAt($type, $at('22:29:59'), 0))->toBe(0.0)
        ->and($party->priceForTypeAt($type, $at('22:30:00'), 0))->toBe(30.0)   // limita e exclusiva
        ->and($party->priceForTypeAt($type, $at('22:35:00'), 10))->toBe(0.0)   // in toleranta
        ->and($party->priceForTypeAt($type, $at('22:39:59'), 10))->toBe(0.0)
        ->and($party->priceForTypeAt($type, $at('22:40:00'), 10))->toBe(30.0); // toleranta epuizata

    // Reducerea cu limita doar de zi nu primeste toleranta; pretul de baza nu se schimba.
    $dayOnly = ['name' => 'Bilet', 'price' => 30, 'discounts' => [['price' => 10, 'until' => '2026-10-03']]];
    expect($party->priceForTypeAt($dayOnly, Carbon::parse('2026-10-04 00:05'), 10))->toBe(30.0);

    // Comportamentul vechi ramane: currentPriceForType = pretul acum, fara toleranta.
    entryNow('22:35');
    expect($party->currentPriceForType($type))->toBe(30.0);
});

it('ofera tipurile de bilet pentru recepție: gratuit, fara pret ignorat, format vechi', function () {
    expect(array_column(entryParty()->entryTicketTypes(), 'name'))->toBe(['Bilet', 'VIP']);

    $free = entryParty(null, ['is_free' => true, 'ticket_types' => null]);
    expect(array_column($free->entryTicketTypes(), 'name'))->toBe(['Intrare gratuită']);

    $partial = entryParty(null, ['ticket_types' => [['name' => 'Fără preț', 'price' => null], ['name' => '', 'price' => 20]]]);
    expect(array_column($partial->entryTicketTypes(), 'name'))->toBe(['Bilet']);
});

it('foloseste toleranta din Setari (implicit 10 minute) la pretul de incasat', function () {
    $party = entryParty();
    entryNow('22:35');

    expect(EntryRecorder::graceMinutes())->toBe(10);
    $q = EntryRecorder::quote($party, 'Bilet');
    expect($q->list_price)->toBe(30.0)->and($q->price)->toBe(0.0)->and($q->grace)->toBeTrue();

    Settings::set('entry_grace_minutes', 0);
    $q = EntryRecorder::quote($party, 'Bilet');
    expect($q->price)->toBe(30.0)->and($q->grace)->toBeFalse();

    expect(EntryRecorder::quote($party, 'Inexistent'))->toBeNull();
});

it('inregistreaza o intrare gratuita in toleranta, cu pretul de lista pastrat', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('22:35');

    $entries = EntryRecorder::record($party, 'Bilet', 2, adminId: $admin->id);

    expect($entries)->toHaveCount(2)
        ->and($entries->every(fn ($e) => (float) $e->price_paid === 0.0 && (float) $e->list_price === 30.0 && $e->grace_applied && $e->override_reason === null))->toBeTrue()
        ->and($entries->pluck('batch')->unique())->toHaveCount(1)
        ->and($entries->first()->created_by)->toBe($admin->id)
        ->and($entries->first()->payments)->toHaveCount(0);
});

it('distribuie plata mixta introdusa pe total pe randurile grupului', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('23:10');

    // 3 x 30 = 90, incasat 50 cash + 40 card
    $entries = EntryRecorder::record($party, 'Bilet', 3, [
        ['method' => 'cash', 'amount' => '50'],
        ['method' => 'card', 'amount' => '40,00'],
        ['method' => 'cash', 'amount' => ''], // rand gol: ignorat
    ], adminId: $admin->id);

    $perEntry = $entries->map(fn ($e) => $e->payments->mapWithKeys(fn ($p) => [$p->method => (float) $p->amount])->all())->all();
    expect($perEntry)->toBe([['cash' => 30.0], ['cash' => 20.0, 'card' => 10.0], ['card' => 30.0]]);

    foreach ($entries as $e) {
        expect((float) $e->payments->sum('amount'))->toBe(30.0);
    }

    $att = PartyStats::attendance($party);
    expect($att->payments)->toBe(['cash' => 50.0, 'card' => 40.0])->and($att->revenue)->toBe(90.0);
});

it('aduna aceeasi metoda introdusa de mai multe ori si evita erorile de virgula mobila', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty(null, ['ticket_types' => [['name' => 'Bilet', 'price' => 12.10]]]);
    entryNow('22:00');

    $entries = EntryRecorder::record($party, 'Bilet', 3, [['method' => 'cash', 'amount' => '10'], ['method' => 'cash', 'amount' => '26.30']], adminId: $admin->id);

    expect((float) $entries->sum('price_paid'))->toBe(36.3)
        ->and((float) $entries->flatMap->payments->sum('amount'))->toBe(36.3);
});

it('valideaza plata: total, metode acceptate la intrare, intrare gratuita, numar de persoane, petrecere neincheiata', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    PaymentMethods::setActive('credit', true);
    $party = entryParty(['card', 'credit', 'token']);   // fara cash pe petrecere
    entryNow('23:00');

    $rec = fn (array $payments, int $count = 1, string $type = 'Bilet') => fn () => EntryRecorder::record($party, $type, $count, $payments, adminId: $admin->id);

    expect($rec([['method' => 'card', 'amount' => 20]]))->toThrow(DomainException::class, 'nu se potrivește');
    expect($rec([]))->toThrow(DomainException::class, 'nu se potrivește');
    expect($rec([['method' => 'card', 'amount' => 40]]))->toThrow(DomainException::class, 'nu se potrivește');
    expect($rec([['method' => 'cash', 'amount' => 30]]))->toThrow(DomainException::class, 'nu este acceptată la intrare');
    expect($rec([['method' => 'token', 'amount' => 30]]))->toThrow(DomainException::class, 'nu este acceptată la intrare');
    expect($rec([['method' => 'revolut', 'amount' => 30]]))->toThrow(DomainException::class, 'nu este acceptată la intrare');
    expect($rec([['method' => 'card', 'amount' => 30]], 0))->toThrow(DomainException::class, 'între 1 și 50');
    expect($rec([['method' => 'card', 'amount' => 30]], 51))->toThrow(DomainException::class, 'între 1 și 50');
    expect($rec([['method' => 'card', 'amount' => 30]], 1, 'Inexistent'))->toThrow(DomainException::class, 'nu există');

    // Credite: accepta la intrare cand sunt active si alese pe petrecere.
    expect($rec([['method' => 'card', 'amount' => 10], ['method' => 'credit', 'amount' => 20]])()->count())->toBe(1);

    // Gratuit: nicio plata.
    entryNow('22:00'); // gratuit pana la 22:30
    expect($rec([['method' => 'card', 'amount' => 5]]))->toThrow(DomainException::class, 'gratuită');

    // Petrecere incheiata sau nepublicata.
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00'));
    expect($rec([['method' => 'card', 'amount' => 30]]))->toThrow(DomainException::class, 'nu primește intrări');
    entryNow('23:00');
    $party->update(['status' => 'draft']);
    expect($rec([['method' => 'card', 'amount' => 30]]))->toThrow(DomainException::class, 'nu primește intrări');

    expect(PartyEntry::count())->toBe(1);
});

it('cere motiv pentru suprascrierea pretului si il pastreaza pe intrare', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('23:00');

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 20]], 20.0, '  ', $admin->id))
        ->toThrow(DomainException::class, 'motiv');
    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [], -5.0, 'x', $admin->id))->toThrow(DomainException::class);

    $entries = EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 40]], 20.0, 'Invitați ai școlii', $admin->id);
    $e = $entries->first();
    expect((float) $e->price_paid)->toBe(20.0)->and((float) $e->list_price)->toBe(30.0)
        ->and($e->override_reason)->toBe('Invitați ai școlii')->and($e->grace_applied)->toBeFalse()->and($e->isOverridden())->toBeTrue();

    // Acelasi pret ca cel calculat = nu e suprascriere (nu cere motiv).
    $same = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], 30.0, null, $admin->id);
    expect($same->first()->override_reason)->toBeNull();

    // Gratuit prin suprascriere.
    $free = EntryRecorder::record($party, 'VIP', 1, [], 0.0, 'Barman în tură', $admin->id);
    expect($free->first()->isFree())->toBeTrue();

    expect(AdminActivityLog::where('action', 'entries.recorded')->latest('id')->first()->description)->toContain('Barman în tură');
});

it('anuleaza un grup cu motiv, fara sa-l stearga, si il scoate din statistici', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('23:00');

    $keep = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);
    $drop = EntryRecorder::record($party, 'VIP', 2, [['method' => 'card', 'amount' => 100]], adminId: $admin->id);

    expect(fn () => EntryRecorder::cancelBatch($drop->first()->batch, ' ', $admin->id))->toThrow(DomainException::class, 'motiv');
    expect(EntryRecorder::cancelBatch($drop->first()->batch, 'Au plecat', $admin->id))->toBe(2);
    expect(fn () => EntryRecorder::cancelBatch($drop->first()->batch, 'din nou', $admin->id))->toThrow(DomainException::class, 'deja anulată');

    expect(PartyEntry::count())->toBe(3)
        ->and(PartyEntry::active()->count())->toBe(1)
        ->and(PartyEntry::whereNotNull('cancelled_at')->first()->cancel_reason)->toBe('Au plecat');

    $att = PartyStats::attendance($party);
    expect($att->count)->toBe(1)->and($att->revenue)->toBe(30.0)->and($att->payments)->toBe(['cash' => 30.0]);
    expect(AdminActivityLog::pluck('action')->all())->toContain('entries.recorded', 'entries.cancelled');
});

it('calculeaza participantii si intrarile pe ore (ore de ceas, peste miezul noptii)', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();

    expect(PartyStats::attendance($party))->toBeNull();

    entryNow('22:10');
    EntryRecorder::record($party, 'Bilet', 3, adminId: $admin->id);                                          // 3 gratuite (reducere)
    entryNow('23:15');
    EntryRecorder::record($party, 'Bilet', 2, [['method' => 'cash', 'amount' => 60]], adminId: $admin->id);
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 10]], 10.0, 'Preț special', $admin->id);
    Carbon::setTestNow(Carbon::parse('2026-10-04 00:20'));
    EntryRecorder::record($party, 'VIP', 1, [['method' => 'card', 'amount' => 50]], adminId: $admin->id);

    $a = PartyStats::attendance($party);
    expect($a->count)->toBe(7)->and($a->free)->toBe(3)->and($a->paid)->toBe(4)
        ->and($a->free_pct)->toBe(42.9)->and($a->grace)->toBe(0)->and($a->overridden)->toBe(1)
        ->and($a->revenue)->toBe(120.0)
        ->and($a->payments)->toBe(['cash' => 70.0, 'card' => 50.0])
        ->and(array_keys($a->hours))->toBe([0, 22, 23])
        ->and($a->hours[22]['count'])->toBe(3)->and($a->hours[23]['revenue'])->toBe(70.0);

    expect(PartyStats::hourAxis($a->hours))->toBe([22, 23, 0]);
});

it('adauga indicatorii de participanti in KPI doar cand exista intrari, si ii da si la comparatie', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    $component = Livewire::test(PartyStatsPage::class, ['party' => $party])->instance();

    $keys = fn ($stats, $cmp = null) => array_map(fn ($k) => $k->key, $component->kpis($stats, $cmp));

    expect($keys(PartyStats::for($party)))->not->toContain('entries_count');

    entryNow('23:00');
    EntryRecorder::record($party, 'Bilet', 4, [['method' => 'cash', 'amount' => 120]], adminId: $admin->id);

    $stats = PartyStats::for($party);
    expect($stats->entries_count)->toBe(4)->and($stats->entries_revenue)->toBe(120.0)->and($stats->entries_free_pct)->toBe(0.0)
        ->and($keys($stats))->toContain('entries_count', 'entries_revenue', 'entries_free_pct', 'bar_per_participant')
        ->and($stats->bar_per_participant)->toBeNull(); // fara raportari finalizate nu exista venit bar

    // Petrecerea comparata fara intrari: cardurile apar oricum daca A are intrari, iar valoarea comparata e null.
    $other = entryParty(null, ['name' => 'Alta', 'start_date' => '2026-09-26']);
    $row = collect($component->kpis($stats, PartyStats::for($other)))->firstWhere('key', 'entries_count');
    expect($row->value)->toBe(4)->and($row->compare)->toBeNull();
});

it('afiseaza „Participanti & intrari” in statistici si fara raportari finalizate', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('22:10');
    EntryRecorder::record($party, 'Bilet', 2, adminId: $admin->id);
    entryNow('23:30');
    EntryRecorder::record($party, 'VIP', 1, [['method' => 'card', 'amount' => 50]], adminId: $admin->id);

    $this->get(route('admin.parties.stats', $party))
        ->assertOk()
        ->assertSee('Participanți &amp; intrări', false)
        ->assertSee('Venit intrări')
        ->assertSee('22:00')
        ->assertSee('Nicio raportare finalizată');
});

it('recepția preselecteaza petrecerea in desfasurare si listeaza doar petreceri publicate si neincheiate', function () {
    $this->actingAs(entryAdmin(), 'admin');
    entryNow('23:00');

    $live = entryParty(null, ['name' => 'În desfășurare']);
    entryParty(null, ['name' => 'Viitoare', 'start_date' => '2026-10-10']);
    entryParty(null, ['name' => 'Încheiată', 'start_date' => '2026-09-20']);
    entryParty(null, ['name' => 'Ciornă', 'start_date' => '2026-10-11', 'status' => 'draft']);

    $c = Livewire::test(ReceptionForm::class)->assertSet('partyId', $live->id)->assertSet('ticket', 'Bilet');
    $c->assertSee('În desfășurare')->assertSee('Viitoare')->assertDontSee('Încheiată')->assertDontSee('Ciornă');
});

it('recepția inregistreaza o intrare cu plata mixta, arata rezumatul si o poate anula', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('23:00');

    $c = Livewire::test(ReceptionForm::class)
        ->assertSee('30,00 lei')
        ->set('count', 3)
        ->assertSee('90,00');

    // Plata incompleta: eroare, nimic salvat.
    $c->set('payments.0.amount', '50')->call('save')->assertSet('error', fn ($e) => str_contains($e, 'nu se potrivește'));
    expect(PartyEntry::count())->toBe(0);

    // Plata mixta pe total.
    $c->call('addPayment')
        ->set('payments.0.method', 'cash')->set('payments.0.amount', '50')
        ->set('payments.1.method', 'card')->set('payments.1.amount', '40')
        ->call('save')
        ->assertSet('error', null)
        ->assertSet('count', 1)
        ->assertSee('Înregistrat: 3 × Bilet');

    expect(PartyEntry::count())->toBe(3)->and(PartyEntry::first()->created_by)->toBe($admin->id);
    $c->assertSee('3 × Bilet')->assertSee('Recepționer Test');

    // "Tot cu card" completeaza suma.
    $c->call('payAll', 'card')->assertSet('payments', [['method' => 'card', 'amount' => '30']]);

    // Anulare (motiv obligatoriu).
    $batch = PartyEntry::first()->batch;
    $c->call('cancel', $batch, ' ')->assertSet('entryCancelError', fn ($e) => str_contains($e, 'motiv'));
    $c->call('cancel', $batch, 'Greșeală')->assertSet('entryCancelMessage', 'Cele 3 intrări au fost anulate.')->assertSee('Anulat: Greșeală');
    expect(PartyEntry::active()->count())->toBe(0);
});

it('recepția cere motiv la schimbarea pretului si arata toleranta', function () {
    $this->actingAs(entryAdmin(), 'admin');
    entryParty();
    entryNow('22:34');

    $c = Livewire::test(ReceptionForm::class)
        ->assertSee('În toleranță (10 min)')
        ->assertSee('Gratuit');

    $c->set('override', true)->assertSet('overridePrice', '0')
        ->set('overridePrice', '15')->set('payments.0.amount', '15')
        ->call('save')->assertSet('error', fn ($e) => str_contains($e, 'motiv'))
        ->set('overrideReason', 'Reducere prietenă')
        ->call('save')->assertSet('error', null)->assertSet('override', false);

    expect((float) PartyEntry::first()->price_paid)->toBe(15.0)->and(PartyEntry::first()->override_reason)->toBe('Reducere prietenă');
});

it('pagina de adaugare a intrarilor se randeaza si arata mesaj cand nu exista petreceri', function () {
    $this->actingAs(entryAdmin(), 'admin');

    $this->get(route('admin.reception.create'))->assertOk()->assertSee('Nu există nicio petrecere publicată');

    entryNow('22:00');
    entryParty();
    $this->get(route('admin.reception.create'))->assertOk()->assertSee('Petrecere intrări')->assertSee('Intrare nouă');
});

it('pagina Recepție (lista de intrari) se randeaza', function () {
    $this->actingAs(entryAdmin(), 'admin');

    $this->get(route('admin.reception.index'))->assertOk()->assertSee('Recepție · Intrări')->assertSee('Intrare nouă');
});

it('tolerante din Setari: implicit 10, se salveaza si se valideaza intre 0 si 60', function () {
    $this->actingAs(entryAdmin(), 'admin');

    $s = Livewire::test(SettingsIndex::class)->assertSee('Toleranță la ora reducerilor');
    $s->set('values.entry_grace_minutes', 61)->call('save')->assertHasErrors(['values.entry_grace_minutes']);
    $s->set('values.entry_grace_minutes', -1)->call('save')->assertHasErrors(['values.entry_grace_minutes']);
    $s->set('values.entry_grace_minutes', 15)->call('save')->assertHasNoErrors();

    expect(EntryRecorder::graceMinutes())->toBe(15);
});

it('nu lasa stergerea unei petreceri care are intrari', function () {
    $admin = entryAdmin();
    $this->actingAs($admin, 'admin');
    $party = entryParty();
    entryNow('23:00');
    EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], adminId: $admin->id);

    Livewire::test(PartiesIndex::class)->call('delete', $party->id);

    expect(Party::find($party->id))->not->toBeNull();

    $empty = entryParty(null, ['name' => 'Fără intrări', 'start_date' => '2026-11-01']);
    Livewire::test(PartiesIndex::class)->call('delete', $empty->id);
    expect(Party::find($empty->id))->toBeNull();
});
