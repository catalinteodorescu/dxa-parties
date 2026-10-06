<?php

use App\Livewire\Participant\Home;
use App\Livewire\Participant\Parties;
use App\Livewire\Participant\PartyShow;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyInterest;
use App\Services\ParticipantRegistry;
use App\Services\PartyInterests;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 40). Petrecerile salvate (inima): salvare / scoatere, lista „Salvate”, reguli, curățare. */
function piParty(string $name = 'Petrecere inimă', string $date = '2026-10-03', array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => $name, 'kind' => 'basic', 'start_date' => $date, 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => true, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function piUser(string $phone = '0722111222', string $name = 'Ana'): Participant
{
    $p = ParticipantRegistry::create($name, $phone);
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

it('salvează și scoate o petrecere; contorul de interesați se actualizează', function () {
    $party = piParty();
    $ana = piUser();

    expect(PartyInterests::toggle($ana, $party))->toBeTrue()->and(PartyInterests::count($party))->toBe(1)->and(PartyInterests::ids($ana))->toBe([$party->id]);
    expect(PartyInterests::toggle($ana, $party))->toBeFalse()->and(PartyInterests::count($party))->toBe(0);
});

it('nu poți salva ciorne sau petreceri dezactivate, dar poți salva și scoate o petrecere încheiată', function () {
    $ana = piUser();
    $draft = piParty('Ciornă', '2026-10-03', ['status' => 'draft']);
    $off = piParty('Oprită', '2026-10-03', ['is_active' => false]);
    $past = piParty('Trecută', '2026-10-03');

    expect(fn () => PartyInterests::toggle($ana, $draft))->toThrow(DomainException::class);
    expect(fn () => PartyInterests::toggle($ana, $off))->toThrow(DomainException::class);

    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
    $past = $past->fresh();
    expect($past->state())->toBe('past')
        ->and(PartyInterests::toggle($ana, $past))->toBeTrue()
        ->and(PartyInterests::count($past))->toBe(1)
        ->and(PartyInterests::toggle($ana, $past))->toBeFalse();
});

it('petrecerile trecute salvate apar în „Salvate” doar dacă setarea „arată petreceri trecute” e activă, dar se numără mereu la interesați', function () {
    $past = piParty('Petrecere veche');
    $future = piParty('Petrecere viitoare', '2026-10-25');
    $ana = piUser();
    PartyInterests::toggle($ana, $future);
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
    PartyInterests::toggle($ana, $past->fresh());
    $this->actingAs($ana, 'participant');

    Settings::set('app_show_past_parties', true);
    Livewire::test(Parties::class)->call('showView', 'saved')->assertSee('Petrecere viitoare')->assertSee('Petrecere veche')->assertSee('Trecute');

    Settings::set('app_show_past_parties', false);
    Livewire::test(Parties::class)->call('showView', 'saved')->assertSee('Petrecere viitoare')->assertDontSee('Petrecere veche');

    expect(PartyInterests::count($past))->toBe(1);
});

it('inima din aplicație: apare pe card, salvează, iar lista Salvate arată doar petrecerile salvate', function () {
    $a = piParty('Petrecere A');
    $b = piParty('Petrecere B', '2026-10-04');
    $ana = piUser();
    $this->actingAs($ana, 'participant');

    Livewire::test(Parties::class)->assertSee("data-heart=\"{$a->id}\"", false)->assertSee('Petrecere A')->assertSee('Petrecere B')
        ->call('toggleInterest', $a->id)->assertDispatched('toast', message: 'Petrecerea a fost adăugată la favorite.', type: 'ok')
        ->assertSee('Favorite')
        ->call('showView', 'saved')->assertSet('view', 'saved')->assertSee('Petrecere A')->assertDontSee('Petrecere B')
        ->call('toggleInterest', $a->id)->assertSee('Nu ai petreceri la favorite')->assertDispatched('toast', message: 'Petrecerea a fost scoasă din favorite.', type: 'ok')
        ->call('showView', 'all')->assertSee('Petrecere B');
});

it('inima e salvată vizibil (aria-pressed) pe Acasă și pe pagina petrecerii', function () {
    $a = piParty('Petrecere A');
    $ana = piUser();
    PartyInterests::toggle($ana, $a);
    $this->actingAs($ana, 'participant');

    Livewire::test(Home::class)->assertSee('aria-pressed="true"', false);
    Livewire::test(PartyShow::class, ['party' => $a])->assertSee('aria-pressed="true"', false)->call('toggleInterest', $a->id)->assertSee('aria-pressed="false"', false);
    expect(PartyInterest::count())->toBe(0);
});

it('vizitatorul fără cont e trimis la login (nu se salvează nimic), la fel pentru lista Salvate', function () {
    $a = piParty();

    Livewire::test(Parties::class)->call('toggleInterest', $a->id)->assertRedirect(route('app.login'));
    Livewire::test(Parties::class)->call('showView', 'saved')->assertRedirect(route('app.login'));
    expect(PartyInterest::count())->toBe(0);
});

it('un participant șters sau anonimizat nu lasă petreceri salvate', function () {
    $a = piParty();
    $ana = piUser();
    $bob = piUser('0733111222', 'Bob');
    PartyInterests::toggle($ana, $a);
    PartyInterests::toggle($bob, $a);

    ParticipantRegistry::delete($ana);
    ParticipantRegistry::anonymize($bob);

    expect(PartyInterest::count())->toBe(0);
});
