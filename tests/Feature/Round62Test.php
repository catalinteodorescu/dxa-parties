<?php

use App\Models\Party;
use App\Support\PartyPublic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** DXA: teste (runda 62). Descrierea petrecerii din aplicație: în box „glass”, fără numele petrecerii repetat la început. */
function r62Party(string $description, string $name = 'Petrecere Latino'): Party
{
    return Party::create([
        'name' => $name, 'kind' => 'basic', 'start_date' => '2030-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => true, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'description' => $description,
    ]);
}

it('scoate rândul cu numele petrecerii de la începutul descrierii generate', function () {
    $party = r62Party("Petrecere Latino\n\n🗓️ Când: sâmbătă, 03.10.2030\n📍 Unde: Club");

    expect(PartyPublic::description($party))->toBe("🗓️ Când: sâmbătă, 03.10.2030\n📍 Unde: Club");
});

it('nu atinge descrierea dacă primul rând nu e numele petrecerii', function () {
    $party = r62Party("O seară specială\n\nDetalii");

    expect(PartyPublic::description($party))->toBe("O seară specială\n\nDetalii");
});

it('la blocurile pe limbă păstrează marcajul (steag + [RO]) și scoate doar numele', function () {
    $party = r62Party("🇷🇴 [RO] Petrecere Latino\n\nCând: sâmbătă\n\n———\n\n🇬🇧 [EN] Petrecere Latino\n\nWhen: Saturday");

    expect(PartyPublic::description($party))->toBe("🇷🇴 [RO]\n\nCând: sâmbătă\n\n———\n\n🇬🇧 [EN]\n\nWhen: Saturday");
});

it('pagina petrecerii arată descrierea într-un box glass, cu titlul „Despre petrecere” în afara lui și fără numele repetat', function () {
    $party = r62Party("Petrecere Latino\n\nDetalii despre seară");
    $html = $this->get('/petreceri/'.$party->id)->assertOk()->assertSee('Despre petrecere')->assertSee('Detalii despre seară')->getContent();

    $inside = substr($html, strpos($html, 'data-party-description'), 700);
    expect($html)->toMatch('/class="pa-glass pa-pad" x-data[\s\S]{0,300}data-party-description/')
        ->and($inside)->toContain('Detalii despre seară')->not->toContain('Petrecere Latino')
        ->and(strpos($html, 'Despre petrecere'))->toBeLessThan(strpos($html, 'data-party-description'));
});

it('o descriere formată doar din numele petrecerii nu afișează secțiunea', function () {
    $party = r62Party('Petrecere Latino');

    $this->get('/petreceri/'.$party->id)->assertOk()->assertDontSee('Despre petrecere')->assertDontSee('data-party-description', false);
});
