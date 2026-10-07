<?php

use App\Livewire\Admin\Participants\Show as ParticipantsShow;
use App\Models\Admin;
use App\Models\CreditTransaction;
use App\Services\CreditLedger;
use App\Services\ParticipantRegistry;
use App\Support\PaymentMethods;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function credAdmin(): Admin
{
    return Admin::create([
        'name' => 'Credite Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'admin', 'permissions' => Permissions::legacyAdmin(),
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

it('incarca, ajusteaza si refundeaza credite prin CreditLedger, cu soldul recalculat', function () {
    $admin = credAdmin();
    $p = ParticipantRegistry::create('Ion Credite', '0722 111 222');

    expect(CreditLedger::balance($p))->toBe(0.0);

    CreditLedger::load($p, 50, CreditTransaction::SOURCE_MANUAL, $admin->id, 'Stoc inițial');
    $p->refresh();
    expect(CreditLedger::balance($p))->toBe(50.0);

    CreditLedger::adjust($p, -10, 'Corecție', $admin->id);
    $p->refresh();
    expect(CreditLedger::balance($p))->toBe(40.0);

    CreditLedger::refund($p, 15, 'Cerere participant', $admin->id);
    $p->refresh();
    expect(CreditLedger::balance($p))->toBe(25.0);

    expect(CreditTransaction::where('participant_id', $p->id)->count())->toBe(3);
    expect(CreditLedger::totalOutstanding())->toBe(25.0);
});

it('valideaza sumele si motivele la incarcare/ajustare/refund', function () {
    $p = ParticipantRegistry::create('Ana Credite', '0722 333 444');

    expect(fn () => CreditLedger::load($p, 0, CreditTransaction::SOURCE_MANUAL))
        ->toThrow(DomainException::class);

    expect(fn () => CreditLedger::load($p, 50, CreditTransaction::SOURCE_MANUAL, null, null))
        ->toThrow(DomainException::class, 'motiv obligatoriu');

    expect(fn () => CreditLedger::adjust($p, 0, 'motiv'))
        ->toThrow(DomainException::class);

    expect(fn () => CreditLedger::adjust($p, -5, ''))
        ->toThrow(DomainException::class);

    expect(fn () => CreditLedger::adjust($p, -5, 'motiv'))
        ->toThrow(DomainException::class, 'sub zero');

    expect(fn () => CreditLedger::refund($p, 5, ''))
        ->toThrow(DomainException::class);
});

it('gardul de credite blocheaza oprirea cat exista sold, si il elibereaza dupa refund', function () {
    $admin = credAdmin();
    $p = ParticipantRegistry::create('Mara Credite', '0722 555 666');

    CreditLedger::load($p, 30, CreditTransaction::SOURCE_MANUAL, $admin->id, 'Stoc inițial');
    $p->refresh();

    expect(PaymentMethods::blockReason(PaymentMethods::CREDIT))->not->toBeNull();

    CreditLedger::refund($p, 30, 'Golire sold', $admin->id);

    expect(PaymentMethods::blockReason(PaymentMethods::CREDIT))->toBeNull();
});

it('pay() debiteaza soldul si arunca eroare daca nu ajunge', function () {
    $p = ParticipantRegistry::create('Vlad Credite', '0722 777 888');
    CreditLedger::load($p, 20, CreditTransaction::SOURCE_MANUAL, null, 'Stoc inițial');
    $p->refresh();

    expect(fn () => CreditLedger::pay($p, 25, 'App\\Models\\Sale', 1))
        ->toThrow(DomainException::class, 'nu ajunge');

    CreditLedger::pay($p, 20, 'App\\Models\\Sale', 1);
    $p->refresh();
    expect(CreditLedger::balance($p))->toBe(0.0);
});

it('fisa participantului: portofelul de credite - incarcare, ajustare, refund din admin', function () {
    $admin = credAdmin();
    $this->actingAs($admin, 'admin');
    $p = ParticipantRegistry::create('Petru Portofel', '0722 999 000');

    $c = Livewire::test(ParticipantsShow::class, ['participant' => $p])
        ->assertSee('Portofel de credite')
        ->assertSee('0,00');

    $c->set('creditLoadAmount', '50')->set('creditLoadNote', 'Stoc inițial')->call('loadCredits')
        ->assertSet('error', null)
        ->assertSet('message', 'Creditele au fost încărcate.')
        ->assertSee('50,00');

    $c->set('creditAdjustAmount', '-10')->set('creditAdjustReason', 'Corecție')->call('adjustCredits')
        ->assertSet('error', null)
        ->assertSee('40,00');

    $c->set('creditRefundAmount', '15')->set('creditRefundReason', 'Cerere participant')->call('refundCredits')
        ->assertSet('error', null)
        ->assertSee('25,00');

    expect(CreditTransaction::where('participant_id', $p->id)->count())->toBe(3);

    // Motiv lipsa -> eroare, fara sa se creeze tranzactia
    $c->set('creditAdjustAmount', '5')->set('creditAdjustReason', '')->call('adjustCredits')
        ->assertSet('error', fn ($e) => str_contains($e, 'motiv'));
    expect(CreditTransaction::where('participant_id', $p->id)->count())->toBe(3);

    $this->get(route('admin.participants.show', $p))->assertOk()->assertSee('Portofel de credite');
});
