<?php

use App\Models\Participant;
use App\Services\ParticipantRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * DXA: teste (runda 61). Loader între ecrane (inel degrade care se rotește) + swipe înapoi: scriptul de mișcare e prezent în ambele layout-uri
 * ale aplicației, o singură dată pe pagină, o singură dată pe sesiune de navigare (data-navigate-once). Comportamentul în sine
 * (apariția după 250 ms, swipe de pe margine, praguri) a fost verificat într-un browser cu touch, în afara suitei Pest.
 */
function r61User(): Participant
{
    $p = ParticipantRegistry::create('Ana', '0722111222');
    $p->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    return $p;
}

it('layout-ul aplicației include scriptul de mișcare (loader + swipe înapoi), o singură dată', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'data-navigate-once'))->toBeGreaterThanOrEqual(1)
        ->and(substr_count($html, '__dxaMotion = true'))->toBe(1)
        ->and($html)->toContain('livewire:navigate')->toContain('dxa-loader')->toContain('navigator.standalone')->toContain('dxa-ring-arc');
});

it('și paginile pentru oaspeți (login, înregistrare) și cele din cont au loaderul', function () {
    $this->get('/intra')->assertOk()->assertSee('__dxaMotion', false);
    $this->get('/inregistrare')->assertOk()->assertSee('__dxaMotion', false);

    $this->actingAs(r61User(), 'participant');
    $this->get('/cont')->assertOk()->assertSee('__dxaMotion', false);
    $this->get('/bilete')->assertOk()->assertSee('__dxaMotion', false);
});

it('swipe-ul înapoi e activ pe ecrane tactile, are mod de diagnostic (?dxdebug=1), iar loaderul respectă „reduce motion”', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('window.navigator.standalone === true')->toContain("'ontouchstart' in window")->toContain('dxdebug')->toContain('prefers-reduced-motion: reduce');
});
