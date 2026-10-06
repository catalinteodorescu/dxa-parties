<?php

use App\Models\Party;
use App\Support\PartyCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 41). „Adaugă în calendar” (.ics) și butoanele de navigare / calendar din detaliile petrecerii. */
function pcParty(array $o = []): Party
{
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));

    return Party::create(array_merge([
        'name' => 'Petrecere calendar, cu virgulă', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'location_name' => 'Club Test', 'location_address' => 'Str. Exemplu 1; București',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ], $o));
}

function pcUtc(string $local): string
{
    return Carbon::parse($local, config('app.timezone'))->utc()->format('Ymd\THis\Z');
}

it('fișierul .ics are un eveniment cu ora de început și de sfârșit (după miezul nopții) în UTC', function () {
    $party = pcParty();

    $res = $this->get(route('app.party.calendar', $party))->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/calendar')->and($res->headers->get('Content-Disposition'))->toContain('petrecere-calendar-cu-virgula.ics');

    $ics = $res->getContent();
    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1);
    expect($ics)->toContain("BEGIN:VCALENDAR\r\n")->toContain("END:VCALENDAR\r\n")
        ->toContain('DTSTART:'.pcUtc('2026-10-03 21:00'))
        ->toContain('DTEND:'.pcUtc('2026-10-04 03:00'))
        ->toContain('SUMMARY:Petrecere calendar\, cu virgulă')
        ->toContain('LOCATION:Club Test\, Str. Exemplu 1\; București')
        ->toContain('URL:'.route('app.party', $party));
});

it('festivalul are câte un eveniment pe zi; fără oră de început = toată ziua; fără oră de sfârșit = 3 ore', function () {
    $fest = pcParty(['kind' => 'festival', 'name' => 'Festival', 'days' => [
        ['date' => '2026-10-03', 'start_time' => '20:00', 'end_time' => '23:00'],
        ['date' => '2026-10-04', 'start_time' => null, 'end_time' => null],
    ], 'end_date' => '2026-10-04']);

    $ics = PartyCalendar::ics($fest->fresh(), 'https://exemplu.ro/petreceri/1');
    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2);
    expect($ics)->toContain('SUMMARY:Festival (ziua 1)')->toContain('SUMMARY:Festival (ziua 2)')
        ->toContain('DTSTART:'.pcUtc('2026-10-03 20:00'))->toContain('DTEND:'.pcUtc('2026-10-03 23:00'))
        ->toContain('DTSTART;VALUE=DATE:20261004')->toContain('DTEND;VALUE=DATE:20261005');

    $noEnd = pcParty(['end_time' => null]);
    expect(PartyCalendar::ics($noEnd, 'https://exemplu.ro/p'))->toContain('DTEND:'.pcUtc('2026-10-04 00:00'))
        ->not->toContain('DTEND:'.pcUtc('2026-10-03 21:00'));
});

it('liniile lungi se împart la 75 de octeți, fără a rupe un caracter', function () {
    $party = pcParty(['name' => str_repeat('Petrecere într-o seară frumoasă ', 6)]);
    $ics = PartyCalendar::ics($party, 'https://exemplu.ro/petreceri/'.$party->id);

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
    expect(str_replace("\r\n ", '', $ics))->toContain(str_repeat('Petrecere într-o seară frumoasă ', 6) === '' ? '' : 'SUMMARY:Petrecere într-o seară frumoasă');
});

it('ciornele și petrecerile dezactivate dau 404; cele „doar logați” nu se dau vizitatorului', function () {
    $this->get(route('app.party.calendar', pcParty(['status' => 'draft'])))->assertNotFound();
    $this->get(route('app.party.calendar', pcParty(['is_active' => false])))->assertNotFound();
    $this->get(route('app.party.calendar', pcParty(['audience' => 'auth'])))->assertNotFound();
});

it('pagina petrecerii: butonul de navigare doar cu link de hartă, butonul de calendar doar cât petrecerea nu s-a încheiat', function () {
    $with = pcParty(['location_url' => 'https://maps.google.com/?q=club']);
    $this->get(route('app.party', $with))->assertOk()
        ->assertSee('data-open-maps', false)->assertSee('href="https://maps.google.com/?q=club"', false)->assertSee('Navighează la locație')
        ->assertDontSee('Vezi pe hartă')
        ->assertSee('data-add-calendar', false)->assertSee(route('app.party.calendar', $with), false);

    $without = pcParty(['name' => 'Fără hartă']);
    $this->get(route('app.party', $without))->assertOk()->assertDontSee('data-open-maps', false)->assertSee('Club Test');

    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
    $this->get(route('app.party', $with))->assertOk()->assertDontSee('data-add-calendar', false)->assertSee('data-open-maps', false);
});
