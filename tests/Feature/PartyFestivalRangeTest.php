<?php

use App\Livewire\Admin\Parties\Form as PartyForm;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function partyAdmin(): Admin
{
    return Admin::create([
        'name' => 'Party Admin',
        'phone' => '+40700'.random_int(100000, 999999),
        'role' => 'superadmin',
        'is_active' => true,
        'password' => 'secret-pass',
    ]);
}

it('fills "Program pe zile" with every day between the festival start and end dates', function () {
    $this->actingAs(partyAdmin(), 'admin');

    $c = Livewire::test(PartyForm::class)->set('kind', 'festival');
    $start = $c->get('start_date');

    // La comutare: sfarsitul propus = a doua zi.
    expect(collect($c->get('days'))->pluck('date')->all())
        ->toBe([$start, Carbon::parse($start)->addDay()->format('Y-m-d')]);

    $c->set('end_date', Carbon::parse($start)->addDays(3)->format('Y-m-d'));
    expect(count($c->get('days')))->toBe(4);
});

it('keeps what was filled on existing days and restores a removed day when the range grows back', function () {
    $this->actingAs(partyAdmin(), 'admin');

    $c = Livewire::test(PartyForm::class)->set('kind', 'festival');
    $start = $c->get('start_date');

    $c->set('end_date', Carbon::parse($start)->addDays(3)->format('Y-m-d'))
        ->set('days.0.dresscode', 'Casual')
        ->set('days.3.dresscode', 'All white');

    // Scurtam intervalul: zilele 3-4 dispar din program...
    $c->set('end_date', Carbon::parse($start)->addDay()->format('Y-m-d'));
    expect(count($c->get('days')))->toBe(2)
        ->and($c->get('days.0.dresscode'))->toBe('Casual');

    // ...si revin, cu continut, cand intervalul se extinde la loc.
    $c->set('end_date', Carbon::parse($start)->addDays(3)->format('Y-m-d'));
    expect(count($c->get('days')))->toBe(4)
        ->and($c->get('days.3.dresscode'))->toBe('All white');
});

it('moves the end date along when the start date is set after it', function () {
    $this->actingAs(partyAdmin(), 'admin');

    $c = Livewire::test(PartyForm::class)->set('kind', 'festival');
    $later = Carbon::parse($c->get('start_date'))->addDays(10)->format('Y-m-d');

    $c->set('start_date', $later);

    expect($c->get('end_date'))->toBe($later)
        ->and(count($c->get('days')))->toBe(1);
});

it('rejects an end date before the start date', function () {
    $this->actingAs(partyAdmin(), 'admin');

    $c = Livewire::test(PartyForm::class)->set('kind', 'festival')->set('name', 'Festival test');
    $before = Carbon::parse($c->get('start_date'))->subDay()->format('Y-m-d');

    $c->set('end_date', $before)->call('save')->assertHasErrors(['end_date']);
});

it('orders the sections: Când, Program pe zile, Locație, Invitați', function () {
    $this->actingAs(partyAdmin(), 'admin');

    Livewire::test(PartyForm::class)->assertSeeInOrder(['Când', 'Program pe zile', 'Locație', 'Invitați']);
});
