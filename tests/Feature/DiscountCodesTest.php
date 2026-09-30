<?php

use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Livewire\Admin\Reconciliation\Index as ReconciliationIndex;
use App\Models\Admin;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\Promoter;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Services\DiscountCodes;
use App\Services\EntryRecorder;
use App\Services\ParticipantRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * DXA: teste (Coduri de reducere). Petrecere 3.10.2026, „Bilet" 50 lei cu treapta „Early bird" 30 lei până la 1.10 23:59,
 * „VIP" 80 lei fără trepte. Implicit „acum" = 25.09 (early bird activ), deci Bilet = 30 lei.
 */
function dcAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Coduri',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

function dcParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere coduri',
        'kind' => 'basic',
        'start_date' => '2026-10-03',
        'start_time' => '21:00',
        'end_time' => '03:00',
        'is_free' => false,
        'audience' => 'all',
        'in_carousel' => false,
        'is_active' => true,
        'status' => 'published',
        'payment_methods' => ['cash', 'card'],
        'ticket_types' => [
            ['name' => 'Bilet', 'price' => 50, 'discounts' => [['label' => 'Early bird', 'price' => 30, 'until' => '2026-10-01T23:59']]],
            ['name' => 'VIP', 'price' => 80, 'discounts' => []],
        ],
    ], $overrides));
}

function dcCode(Party $party, array $attrs = []): PartyDiscountCode
{
    return PartyDiscountCode::create(array_merge([
        'party_id' => $party->id,
        'code' => 'ANA10',
        'type' => 'percent',
        'value' => 10,
        'max_uses_per_participant' => null,
        'is_active' => true,
    ], $attrs));
}

function dcRecord(Party $party, ?string $code, string $ticket = 'Bilet', int $count = 1, array $participants = [])
{
    $unit = EntryRecorder::quote($party, $ticket, null, $code, $participants, $count)->price;

    return EntryRecorder::record(
        $party, $ticket, $count,
        $unit * $count > 0 ? [['method' => 'cash', 'amount' => $unit * $count]] : [],
        participants: $participants, discountCode: $code,
    );
}

it('normalizeaza codul si il gaseste fara diferenta intre litere mari/mici', function () {
    $party = dcParty();
    $code = dcCode($party, ['code' => 'ANA-10']);

    expect(DiscountCodes::normalize('  ana -10 '))->toBe('ANA-10')
        ->and(DiscountCodes::find($party, 'ana-10')?->id)->toBe($code->id)
        ->and(DiscountCodes::find($party, ''))->toBeNull()
        ->and(DiscountCodes::find($party, 'ALTUL'))->toBeNull();

    // Un cod al altei petreceri nu se aplica aici.
    $other = dcParty(['name' => 'Alta']);
    expect(DiscountCodes::find($other, 'ANA-10'))->toBeNull();
});

it('aplica procentul si suma peste pretul CURENT (inclusiv early bird activ), fara sa coboare sub 0', function () {
    $party = dcParty();
    dcCode($party, ['code' => 'P10', 'type' => 'percent', 'value' => 10]);
    dcCode($party, ['code' => 'L5', 'type' => 'amount', 'value' => 5]);
    dcCode($party, ['code' => 'L500', 'type' => 'amount', 'value' => 500]);
    dcCode($party, ['code' => 'P100', 'type' => 'percent', 'value' => 100]);

    // 25.09: early bird activ => pret curent 30.
    expect(EntryRecorder::quote($party, 'Bilet', null, 'p10')->price)->toBe(27.0)
        ->and(EntryRecorder::quote($party, 'Bilet', null, 'L5')->price)->toBe(25.0)
        ->and(EntryRecorder::quote($party, 'Bilet', null, 'L500')->price)->toBe(0.0)
        ->and(EntryRecorder::quote($party, 'Bilet', null, 'P100')->price)->toBe(0.0);

    $q = EntryRecorder::quote($party, 'Bilet', null, 'P10');
    expect($q->list_price)->toBe(30.0)->and($q->price_before_code)->toBe(30.0)->and($q->discount)->toBe(3.0)->and($q->code->code)->toBe('P10');

    // 2.10: early bird expirat => pret curent 50.
    Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
    expect(EntryRecorder::quote($party, 'Bilet', null, 'P10')->price)->toBe(45.0)
        ->and(EntryRecorder::quote($party, 'VIP', null, 'P10')->price)->toBe(72.0);

    // Fara cod, nimic nu se schimba.
    expect(EntryRecorder::quote($party, 'Bilet')->price)->toBe(50.0)->and(EntryRecorder::quote($party, 'Bilet')->code)->toBeNull();
});

it('codul „treapta" da pretul early bird oricand, dar nu urca niciodata pretul', function () {
    $party = dcParty();
    dcCode($party, ['code' => 'EB', 'type' => 'tier', 'tier_label' => 'early bird', 'value' => null]);

    Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00')); // early bird expirat
    $q = EntryRecorder::quote($party, 'Bilet', null, 'EB');
    expect($q->price)->toBe(30.0)->and($q->discount)->toBe(20.0)->and($q->list_price)->toBe(50.0);

    // VIP nu are treapta „Early bird": codul nu se aplica.
    expect(fn () => EntryRecorder::quote($party, 'VIP', null, 'EB'))->toThrow(DomainException::class, 'nu are treapta');

    // Cat timp early bird e activ, pretul curent e deja cel al treptei: codul nu aduce reducere.
    Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));
    expect(fn () => EntryRecorder::quote($party, 'Bilet', null, 'EB'))->toThrow(DomainException::class, 'nicio reducere');

    // Treapta mai scumpa decat pretul curent nu-l urca (min).
    $p2 = dcParty(['name' => 'P2', 'ticket_types' => [['name' => 'Bilet', 'price' => 20, 'discounts' => [['label' => 'Late', 'price' => 40, 'until' => '2026-12-01T00:00']]]]]);
    dcCode($p2, ['code' => 'LATE', 'type' => 'tier', 'tier_label' => 'Late', 'value' => null]);
    expect(fn () => EntryRecorder::quote($p2, 'Bilet', null, 'LATE'))->toThrow(DomainException::class, 'nicio reducere');
});

it('respecta valabilitatea (de la / pana la exclusiv), activarea si tipul de bilet', function () {
    $party = dcParty();
    dcCode($party, ['code' => 'FEREASTRA', 'valid_from' => '2026-09-26 10:00', 'valid_until' => '2026-09-28 22:30']);
    dcCode($party, ['code' => 'OFF', 'is_active' => false]);
    dcCode($party, ['code' => 'DOARVIP', 'ticket_types' => ['VIP']]);

    $q = fn (string $code, string $ticket = 'VIP') => EntryRecorder::quote($party, $ticket, null, $code);

    Carbon::setTestNow(Carbon::parse('2026-09-26 09:59:59'));
    expect(fn () => $q('fereastra'))->toThrow(DomainException::class, 'nu este încă valabil');
    Carbon::setTestNow(Carbon::parse('2026-09-26 10:00:00'));
    expect($q('fereastra')->price)->toBe(72.0);
    Carbon::setTestNow(Carbon::parse('2026-09-28 22:29:59'));
    expect($q('fereastra')->price)->toBe(72.0);
    Carbon::setTestNow(Carbon::parse('2026-09-28 22:30:00'));
    expect(fn () => $q('fereastra'))->toThrow(DomainException::class, 'a expirat');

    expect(fn () => $q('off'))->toThrow(DomainException::class, 'nu mai este activ')
        ->and(fn () => $q('DOARVIP', 'Bilet'))->toThrow(DomainException::class, 'nu se aplică la biletul „Bilet”')
        ->and($q('DOARVIP', 'VIP')->price)->toBe(72.0)
        ->and(fn () => $q('NUEXISTA'))->toThrow(DomainException::class, 'nu există');
});

it('inregistreaza intrarea cu codul: pret de lista pastrat, pret platit si reducere inghetate, aplicat fiecarei persoane', function () {
    $party = dcParty();
    $code = dcCode($party, ['code' => 'PROMO', 'type' => 'amount', 'value' => 5, 'promoter_id' => Promoter::create(['name' => 'Ana'])->id]);

    $rows = dcRecord($party, 'promo', 'Bilet', 3);

    expect($rows)->toHaveCount(3);
    foreach ($rows as $e) {
        expect((float) $e->list_price)->toBe(30.0)
            ->and((float) $e->price_paid)->toBe(25.0)
            ->and((float) $e->discount_amount)->toBe(5.0)
            ->and($e->discount_code_id)->toBe($code->id);
    }
    expect((float) $rows->first()->payments()->sum('amount') + (float) $rows->last()->payments()->sum('amount'))->toBeGreaterThan(0.0)
        ->and(round((float) $rows->sum(fn ($e) => $e->payments()->sum('amount')), 2))->toBe(75.0);

    // Fiecare bilet cu reducere = o utilizare: comanda de 3 persoane consuma 3.
    expect($code->usesCount())->toBe(3);

    // Fara cod: coloanele raman neatinse.
    $plain = dcRecord($party, null, 'VIP')->first();
    expect($plain->discount_code_id)->toBeNull()->and((float) $plain->discount_amount)->toBe(0.0);
});

it('limita totala numara BILETE (fiecare reducere data); anularea unei intrari elibereaza utilizarea', function () {
    $party = dcParty();
    $code = dcCode($party, ['code' => 'TREI', 'max_uses' => 3]);

    // O comanda de 4 bilete nu incape in 3 utilizari: se respinge intreaga, nu se creeaza nimic.
    expect(fn () => EntryRecorder::quote($party, 'VIP', null, 'TREI', [], 4))->toThrow(DomainException::class, 'doar pentru 3 bilete')
        ->and(fn () => dcRecord($party, 'TREI', 'VIP', 4))->toThrow(DomainException::class, 'doar pentru 3 bilete');
    expect(PartyEntry::query()->count())->toBe(0);

    $first = dcRecord($party, 'TREI', 'VIP', 2);   // 2 bilete = 2 utilizari
    expect($code->usesCount())->toBe(2)
        ->and(fn () => dcRecord($party, 'TREI', 'VIP', 2))->toThrow(DomainException::class, 'doar pentru 1 bilet');

    dcRecord($party, 'TREI', 'VIP');               // ultimul bilet
    expect($code->usesCount())->toBe(3)
        ->and(fn () => dcRecord($party, 'TREI', 'VIP'))->toThrow(DomainException::class, 'numărul maxim');
    expect(PartyEntry::query()->count())->toBe(3);

    // Anularea comenzii de 2 elibereaza 2 utilizari.
    EntryRecorder::cancelBatch($first->first()->batch, 'Greșeală');
    expect($code->fresh()->usesCount())->toBe(1);
    dcRecord($party, 'TREI', 'VIP', 2);
    expect($code->fresh()->usesCount())->toBe(3);
});

it('limita per participant numara biletele fiecarui participant; intrarile anonime au doar limita totala', function () {
    $party = dcParty();
    $code = dcCode($party, ['code' => 'UNA', 'max_uses_per_participant' => 1]);
    $admin = dcAdmin();

    $ana = ParticipantRegistry::create('Ana Test', '0722111111', $admin->id);
    $bob = ParticipantRegistry::create('Bob Test', '0722222222', $admin->id);

    dcRecord($party, 'UNA', 'VIP', 1, [$ana->id]);
    expect($code->usesBy($ana->id))->toBe(1);

    // Ana nu mai poate folosi codul (chiar intr-o alta sesiune de receptie / comanda).
    expect(fn () => EntryRecorder::quote($party, 'VIP', null, 'UNA', [$ana->id]))->toThrow(DomainException::class, 'deja acest cod');

    // Bob poate; anonimii nu sunt limitati per participant.
    expect(dcRecord($party, 'UNA', 'VIP', 1, [$bob->id]))->toHaveCount(1);
    expect(dcRecord($party, 'UNA', 'VIP', 2))->toHaveCount(2);
    expect($code->usesCount())->toBe(4);

    // Dupa anularea comenzii Anei, poate folosi din nou codul.
    $anaEntry = PartyEntry::query()->where('participant_id', $ana->id)->first();
    EntryRecorder::cancelBatch($anaEntry->batch, 'Anulat');
    expect(EntryRecorder::quote($party, 'VIP', null, 'UNA', [$ana->id])->price)->toBe(72.0);
});

it('la suprascrierea pretului, referinta e pretul cu cod (motiv cerut doar daca difera)', function () {
    $party = dcParty();
    dcCode($party, ['code' => 'P10']); // Bilet: 30 -> 27

    // Acelasi pret cu cel al codului: fara motiv.
    $ok = EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 27]], overridePrice: 27.0, discountCode: 'P10');
    expect((float) $ok->first()->price_paid)->toBe(27.0)->and($ok->first()->override_reason)->toBeNull();

    // Alt pret: motiv obligatoriu.
    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 20]], overridePrice: 20.0, discountCode: 'P10'))
        ->toThrow(DomainException::class, 'motiv');
});

it('un cod invalid opreste inregistrarea intreaga (nu se creeaza intrari)', function () {
    $party = dcParty();

    expect(fn () => EntryRecorder::record($party, 'Bilet', 1, [['method' => 'cash', 'amount' => 30]], discountCode: 'NUEXISTA'))
        ->toThrow(DomainException::class, 'nu există');
    expect(PartyEntry::query()->count())->toBe(0);
});

it('formularul petrecerii salveaza mai multe coduri (cate unul pe promotor) si le reincarca', function () {
    $this->actingAs(dcAdmin(), 'admin');
    $ana = Promoter::create(['name' => 'Ana']);
    $bob = Promoter::create(['name' => 'Bob']);

    $c = Livewire::test(PartyForm::class)
        ->set('name', 'Petrecere cu promotori')
        ->set('ticket_types', [['name' => 'Bilet', 'price' => '50', 'discounts' => [['label' => 'Early bird', 'price' => '30', 'until' => '2026-10-01T23:59']]]])
        ->set('discount_codes', [
            ['id' => null, 'code' => ' ana10 ', 'promoter_id' => (string) $ana->id, 'type' => 'percent', 'value' => '10', 'tier_label' => '', 'ticket_types' => [], 'valid_from' => '', 'valid_until' => '2026-10-03T20:00', 'max_uses' => '', 'max_uses_per_participant' => '1', 'is_active' => true, 'note' => ''],
            ['id' => null, 'code' => 'BOB5', 'promoter_id' => (string) $bob->id, 'type' => 'amount', 'value' => '5,50', 'tier_label' => '', 'ticket_types' => ['Bilet'], 'valid_from' => '', 'valid_until' => '', 'max_uses' => '20', 'max_uses_per_participant' => '', 'is_active' => true, 'note' => 'promotor facultate'],
            ['id' => null, 'code' => 'EARLY', 'promoter_id' => '', 'type' => 'tier', 'value' => '', 'tier_label' => 'early bird', 'ticket_types' => [], 'valid_from' => '', 'valid_until' => '', 'max_uses' => '', 'max_uses_per_participant' => '1', 'is_active' => true, 'note' => ''],
        ])
        ->call('save')->assertHasNoErrors();

    $party = Party::query()->where('name', 'Petrecere cu promotori')->firstOrFail();
    $codes = $party->discountCodes->keyBy('code');

    expect($codes->keys()->all())->toBe(['ANA10', 'BOB5', 'EARLY'])
        ->and($codes['ANA10']->promoter->name)->toBe('Ana')
        ->and($codes['BOB5']->promoter_id)->toBe($bob->id)
        ->and($codes['EARLY']->promoter_id)->toBeNull()
        ->and((float) $codes['ANA10']->value)->toBe(10.0)
        ->and($codes['ANA10']->valid_until->format('Y-m-d H:i'))->toBe('2026-10-03 20:00')
        ->and($codes['ANA10']->max_uses_per_participant)->toBe(1)
        ->and((float) $codes['BOB5']->value)->toBe(5.5)
        ->and($codes['BOB5']->ticket_types)->toBe(['Bilet'])
        ->and($codes['BOB5']->max_uses)->toBe(20)
        ->and($codes['BOB5']->max_uses_per_participant)->toBeNull()
        ->and($codes['EARLY']->tier_label)->toBe('early bird');

    // Se reincarca la editare; sectiunea apare dupa Pret (si inaintea Vanzarii de bilete).
    Livewire::test(PartyForm::class, ['party' => $party])
        ->assertSet('discount_codes.0.code', 'ANA10')
        ->assertSet('discount_codes.1.value', '5.5')
        ->assertSet('discount_codes.0.valid_until', '2026-10-03T20:00')
        ->assertSeeInOrder(['Adaugă tip de bilet', 'Coduri de reducere', 'Vânzare bilete și capacitate', 'Modalități de plată']);
});

it('formularul respinge coduri invalide (validari pe rand)', function () {
    $this->actingAs(dcAdmin(), 'admin');
    $base = ['id' => null, 'code' => 'COD1', 'promoter_id' => '', 'type' => 'percent', 'value' => '10', 'tier_label' => '', 'ticket_types' => [], 'valid_from' => '', 'valid_until' => '', 'max_uses' => '', 'max_uses_per_participant' => '1', 'is_active' => true, 'note' => ''];

    $try = function (array $rows, array $expectErrors) use ($base) {
        $rows = array_map(fn ($r) => array_merge($base, $r), $rows);
        Livewire::test(PartyForm::class)->set('name', 'Validare coduri')
            ->set('ticket_types', [['name' => 'Bilet', 'price' => '50', 'discounts' => [['label' => 'Early bird', 'price' => '30', 'until' => '']]]])
            ->set('discount_codes', $rows)->call('save')->assertHasErrors($expectErrors);
    };

    $try([[], ['code' => 'cod1']], ['discount_codes.1.code']);                     // dublura (litere mari/mici)
    $try([['code' => 'AB']], ['discount_codes.0.code']);                            // prea scurt
    $try([['code' => 'ARE SPATIU!']], ['discount_codes.0.code']);                   // caractere nepermise
    $try([['value' => '0']], ['discount_codes.0.value']);
    $try([['value' => '150']], ['discount_codes.0.value']);
    $try([['type' => 'amount', 'value' => 'abc']], ['discount_codes.0.value']);
    $try([['type' => 'tier', 'tier_label' => '']], ['discount_codes.0.tier_label']);
    $try([['type' => 'tier', 'tier_label' => 'Late owls']], ['discount_codes.0.tier_label']);  // treapta redenumita/inexistenta
    $try([['ticket_types' => ['Vechiul nume']]], ['discount_codes.0.ticket_types']);           // bilet redenumit
    $try([['valid_from' => '2026-10-03T22:00', 'valid_until' => '2026-10-03T20:00']], ['discount_codes.0.valid_until']);
    $try([['promoter_id' => '9999']], ['discount_codes.0.promoter_id']);                        // promotor inexistent
    $try([['max_uses' => '0']], ['discount_codes.0.max_uses']);
    $try([['max_uses_per_participant' => 'x']], ['discount_codes.0.max_uses_per_participant']);

    expect(Party::query()->count())->toBe(0)->and(PartyDiscountCode::query()->count())->toBe(0);
});

it('un cod folosit nu se sterge din formular (se dezactiveaza); unul nefolosit se sterge la salvare', function () {
    $this->actingAs(dcAdmin(), 'admin');
    $party = dcParty();
    $used = dcCode($party, ['code' => 'FOLOSIT']);
    $free = dcCode($party, ['code' => 'LIBER']);
    dcRecord($party, 'FOLOSIT', 'VIP');

    $c = Livewire::test(PartyForm::class, ['party' => $party])->assertCount('discount_codes', 2);

    $c->call('removeDiscountCode', 0)->assertHasErrors(['discount_codes.0.code'])->assertCount('discount_codes', 2);
    $c->call('removeDiscountCode', 1)->assertCount('discount_codes', 1)->call('save')->assertHasNoErrors();

    expect(PartyDiscountCode::query()->whereKey($free->id)->exists())->toBeFalse()
        ->and($used->fresh()->is_active)->toBeTrue();

    // Scos din lista prin manipulare (fara buton): tot nu se pierde, ci se dezactiveaza.
    Livewire::test(PartyForm::class, ['party' => $party->fresh()])->set('discount_codes', [])->call('save')->assertHasNoErrors();
    expect($used->fresh())->not->toBeNull()->and($used->fresh()->is_active)->toBeFalse();
});

it('genereaza un cod scurt, unic pe petrecere', function () {
    $this->actingAs(dcAdmin(), 'admin');

    $c = Livewire::test(PartyForm::class)->call('addDiscountCode')->call('addDiscountCode')
        ->call('generateDiscountCode', 0)->call('generateDiscountCode', 1);

    expect($c->get('discount_codes.0.code'))->toMatch('/^[A-HJ-NP-Z2-9]{6}$/')
        ->and($c->get('discount_codes.1.code'))->not->toBe($c->get('discount_codes.0.code'));
});

it('petrecerea gratuita nu atinge codurile existente', function () {
    $this->actingAs(dcAdmin(), 'admin');
    $party = dcParty();
    dcCode($party, ['code' => 'RAMAS']);

    Livewire::test(PartyForm::class, ['party' => $party])->set('is_free', true)->set('discount_codes', [])->call('save')->assertHasNoErrors();
    expect($party->discountCodes()->count())->toBe(1);
});

it('Bilantul serii arata „Reduceri acordate" pe cod, cu promotor, doar din intrari valabile', function () {
    $this->actingAs(dcAdmin(), 'admin');
    $party = dcParty();
    $ana = Promoter::create(['name' => 'Ana']);
    $bob = Promoter::create(['name' => 'Bob']);
    dcCode($party, ['code' => 'ANA10', 'promoter_id' => $ana->id, 'type' => 'amount', 'value' => 5]);
    dcCode($party, ['code' => 'BOB', 'promoter_id' => $bob->id, 'type' => 'amount', 'value' => 10]);

    dcRecord($party, 'ANA10', 'VIP', 2);                        // 2 bilete x 5 = 10
    $cancelled = dcRecord($party, 'BOB', 'VIP', 1);              // 10, va fi anulat
    EntryRecorder::cancelBatch($cancelled->first()->batch, 'test');
    dcRecord($party, 'BOB', 'VIP', 1);                          // 10

    $g = DiscountCodes::grantedFor($party);
    expect($g->pluck('code')->all())->toBe(['ANA10', 'BOB'])
        ->and($g->firstWhere('code', 'ANA10')->tickets)->toBe(2)
        ->and($g->firstWhere('code', 'ANA10')->amount)->toBe(10.0)
        ->and($g->firstWhere('code', 'BOB')->amount)->toBe(10.0);

    // Pagina apare doar pentru petreceri cu raportari de casa: deschidem una.
    ReceptionReport::startFor(ReceptionSession::query()->where('party_id', $party->id)->firstOrFail());
    Livewire::test(ReconciliationIndex::class)->assertSet('party', (string) $party->id)
        ->assertSeeInOrder(['Reduceri acordate', '20,00', 'ANA10', 'Ana', '2 bilete cu reducere', 'BOB', 'Bob', '1 bilet cu reducere']);
});
