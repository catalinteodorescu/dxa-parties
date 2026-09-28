<?php

use App\Livewire\Admin\Reception\CreditSale;
use App\Livewire\Admin\Reception\Form as ReceptionForm;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\Party;
use App\Models\ReceptionReport;
use App\Models\ReceptionSession;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function csAdmin(): Admin
{
    return Admin::create([
        'name' => 'Casier Credite',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

/** Petrecere 3.10.2026, 21:00-03:00; „acum” = 23:00 din prima zi. Activează cumpărarea de credite. */
function csParty(array $overrides = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00:00'));
    PaymentMethods::setActive(PaymentMethods::CREDIT, true);
    PaymentMethods::setCreditsPurchasable(true);

    return Party::create(array_merge([
        'name' => 'Petrecere credite',
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

it('vinde credite unui participant la recepție, cu plata mixtă, sesiune și log', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $p = ParticipantRegistry::create('Ion Recepție', '0722 111 000');

    $tx = CreditLedger::sell($party, $p, 50, [['method' => 'cash', 'amount' => 30], ['method' => 'card', 'amount' => 20]], $admin->id);

    expect($tx->type)->toBe('load')->and($tx->source)->toBe('reception')->and((float) $tx->amount)->toBe(50.0)
        ->and($tx->party_id)->toBe($party->id)->and($tx->participant_id)->toBe($p->id)->and($tx->created_by)->toBe($admin->id)
        ->and($tx->reception_session_id)->toBe(ReceptionSession::currentFor($party->id)->id)
        ->and($tx->payments->mapWithKeys(fn ($pay) => [$pay->method => (float) $pay->amount])->all())->toBe(['cash' => 30.0, 'card' => 20.0]);

    expect(CreditLedger::balance($p->fresh()))->toBe(50.0);
    expect(AdminActivityLog::pluck('action')->all())->toContain('credits.sold');
});

it('valideaza vanzarea de credite: suma, stare cumparare, participant obligatoriu, plata, metode fara credite, petrecere', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty(['payment_methods' => ['cash', 'card', 'credit']]);
    $p = ParticipantRegistry::create('Ana Recepție', '0722 222 000');

    $sell = fn (float $amount, array $pay) => fn () => CreditLedger::sell($party, $p, $amount, $pay, $admin->id);

    expect($sell(0, [['method' => 'cash', 'amount' => 0]]))->toThrow(DomainException::class, 'între 0 și');
    expect($sell(6000, [['method' => 'cash', 'amount' => 6000]]))->toThrow(DomainException::class, 'între 0 și');
    expect($sell(50, [['method' => 'cash', 'amount' => 40]]))->toThrow(DomainException::class, 'nu se potrivește');
    expect($sell(50, []))->toThrow(DomainException::class, 'nu se potrivește');
    expect($sell(50, [['method' => 'credit', 'amount' => 50]]))->toThrow(DomainException::class, 'nu este acceptată la cumpărarea de credite');

    // Cumpararea de credite oprita.
    PaymentMethods::setCreditsPurchasable(false);
    expect($sell(50, [['method' => 'cash', 'amount' => 50]]))->toThrow(DomainException::class, 'nu se mai vând');
    PaymentMethods::setCreditsPurchasable(true);

    // Petrecere incheiata.
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00'));
    expect($sell(50, [['method' => 'cash', 'amount' => 50]]))->toThrow(DomainException::class, 'nu primește vânzări');
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:00'));

    expect(CreditTransaction::count())->toBe(0);
    expect($sell(50, [['method' => 'card', 'amount' => 20], ['method' => 'cash', 'amount' => 30]])()->amount)->toEqual(50);
});

it('anuleaza o vanzare de credite cu motiv, doar daca nu au fost deja folosite si sesiunea e deschisa', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $p = ParticipantRegistry::create('Mihai Recepție', '0722 333 000');

    $a = CreditLedger::sell($party, $p, 50, [['method' => 'cash', 'amount' => 50]], $admin->id);
    $adj = CreditLedger::adjust($p, 10, 'Corecție', $admin->id);

    expect(fn () => CreditLedger::cancel($a->id, ' ', $admin->id))->toThrow(DomainException::class, 'motiv');
    expect(fn () => CreditLedger::cancel($adj->id, 'Nu se poate', $admin->id))->toThrow(DomainException::class, 'nu există');

    // Cheltuieste o parte din credite (refund manual simuleaza consumul soldului).
    CreditLedger::refund($p->fresh(), 55, 'Folosite deja', $admin->id); // sold 60 - 55 = 5 < 50
    expect(fn () => CreditLedger::cancel($a->id, 'Greșeală', $admin->id))->toThrow(DomainException::class, 'deja folosite');

    // Reincarca sold suficient si anuleaza cu succes.
    CreditLedger::load($p->fresh(), 100, CreditTransaction::SOURCE_MANUAL, $admin->id, 'Reincarcare test');
    CreditLedger::cancel($a->id, 'Greșeală', $admin->id);
    expect($a->fresh()->isCancelled())->toBeTrue()->and($a->fresh()->cancel_reason)->toBe('Greșeală');
    expect(fn () => CreditLedger::cancel($a->id, 'din nou', $admin->id))->toThrow(DomainException::class, 'deja anulată');
    expect(AdminActivityLog::pluck('action')->all())->toContain('credits.sale_cancelled');

    // Sesiune inchisa: nu se mai poate anula.
    $b = CreditLedger::sell($party, $p->fresh(), 20, [['method' => 'cash', 'amount' => 20]], $admin->id);
    $session = ReceptionSession::currentFor($party->id);
    $report = ReceptionReport::startFor($session, $admin->id);
    $report->update(['counted_cash' => 0]);
    $report->finalize($admin->id);
    expect(fn () => CreditLedger::cancel($b->id, 'Târziu', $admin->id))->toThrow(DomainException::class, 'Casa a fost închisă');
});

it('calculeaza totalurile de credite ale unei petreceri', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $other = csParty(['name' => 'Alta', 'start_date' => '2026-10-03']);
    $p = ParticipantRegistry::create('Vlad Recepție', '0722 444 000');

    expect(CreditLedger::partyTotals($party))->toBeNull();

    CreditLedger::sell($party, $p, 50, [['method' => 'cash', 'amount' => 50]], $admin->id);
    CreditLedger::sell($party, $p->fresh(), 30, [['method' => 'cash', 'amount' => 30]], $admin->id);
    CreditLedger::sell($other, $p->fresh(), 20, [['method' => 'cash', 'amount' => 20]], $admin->id);
    $cancelled = CreditLedger::sell($party, $p->fresh(), 10, [['method' => 'cash', 'amount' => 10]], $admin->id);
    CreditLedger::cancel($cancelled->id, 'Anulat', $admin->id);

    $t = CreditLedger::partyTotals($party);
    expect($t->sold_count)->toBe(2)->and($t->sold_amount)->toBe(80.0);
    expect(CreditLedger::partyTotals($other)->sold_amount)->toBe(20.0);
});

it('cardul Vinde credite din Recepție: participant obligatoriu, plata mixta, fara credite, si poate anula', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $p = ParticipantRegistry::create('Radu Recepție', '0722 555 000');

    $c = Livewire::test(CreditSale::class, ['partyId' => $party->id])
        ->assertSee('1 credit = 1 leu')
        ->assertDontSee('Tot cu Credite'); // creditele nu se cumpara cu credite

    // Fara participant ales: eroare, nimic salvat.
    $c->call('sell')->assertSet('error', fn ($e) => str_contains($e, 'Alege participantul'));
    expect(CreditTransaction::count())->toBe(0);

    $c->call('addParticipant', $p->id)
        ->set('amount', '40')->call('payAll', 'cash')
        ->assertSee('Încasat complet')
        ->call('sell')
        ->assertSet('error', null)
        ->assertSee('Vândut: 40,00 lei credite');

    $tx = CreditTransaction::first();
    expect((float) $tx->amount)->toBe(40.0)->and($tx->created_by)->toBe($admin->id);
    $c->assertDispatched('credits-changed');
});

it('ecranul Recepție arata ultimele vanzari de credite in coloana din dreapta si le poate anula', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $p = ParticipantRegistry::create('Sorin Recepție', '0722 666 000');

    $c = Livewire::test(ReceptionForm::class)->assertSee('Nicio vânzare de credite încă.');

    $tx = CreditLedger::sell($party, $p, 40, [['method' => 'cash', 'amount' => 40]], $admin->id);
    $c->dispatch('credits-changed')->assertSee('40,00 lei credite')->assertSee('Casier Credite')->assertSee($p->name);

    $c->call('cancelCreditSale', $tx->id, ' ')->assertSet('creditCancelError', fn ($e) => str_contains($e, 'motiv'));
    $c->call('cancelCreditSale', $tx->id, 'Greșeală')
        ->assertSet('creditCancelMessage', 'Vânzarea de credite a fost anulată.')
        ->assertDispatched('credits-changed')
        ->assertSee('Anulat: Greșeală');
    expect(CreditLedger::balance($p->fresh()))->toBe(0.0);
});

it('raportarea de receptie include creditele: cash asteptat, alte metode si inghetarea la finalizare', function () {
    $admin = csAdmin();
    $this->actingAs($admin, 'admin');
    $party = csParty();
    $p = ParticipantRegistry::create('Elena Recepție', '0722 777 000');

    CreditLedger::sell($party, $p, 50, [['method' => 'cash', 'amount' => 30], ['method' => 'card', 'amount' => 20]], $admin->id);
    $cancelled = CreditLedger::sell($party, $p->fresh(), 15, [['method' => 'cash', 'amount' => 15]], $admin->id);
    CreditLedger::cancel($cancelled->id, 'Greșeală', $admin->id);

    $session = ReceptionSession::currentFor($party->id);
    $report = ReceptionReport::startFor($session, $admin->id);
    $report->update(['opening_float' => 0, 'counted_cash' => 30]);
    $fig = $report->fresh()->figures();

    expect($fig->cash_credits)->toBe(30.0)
        ->and($fig->credits_amount)->toBe(50.0)
        ->and($fig->credit_sales)->toBe(1)
        ->and($fig->credit_sales_cancelled)->toBe(1)
        ->and($fig->expected_cash)->toBe(30.0)
        ->and(collect($fig->other_methods)->pluck('amount', 'key')->all())->toEqual(['card' => 20.0]);

    $report->finalize($admin->id);
    $frozen = $report->fresh()->figures();
    expect($frozen->finalized)->toBeTrue()
        ->and($frozen->cash_credits)->toBe(30.0)
        ->and($frozen->credits_amount)->toBe(50.0)
        ->and($frozen->credit_sales)->toBe(1)
        ->and($frozen->credit_sales_cancelled)->toBe(1);

    $this->get(route('admin.reception.reports.show', $report))
        ->assertOk()
        ->assertSee('Credite vândute')
        ->assertSee('Încasat din credite');
});
