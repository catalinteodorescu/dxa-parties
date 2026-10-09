<?php

use App\Services\PrivacyPolicy;
use App\Services\Terms;
use App\Support\ParticipantAppSettings;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** DXA: teste (runda 69). Subsolul aplicației participanților, pe roluri: Contact, Social, Legal; linkuri discrete, fără butoane. */
it('contactele sunt împărțite pe roluri, iar site-ul se afișează cu adresa lui', function () {
    Settings::set('app_contact_phone', '0722 111 222');
    Settings::set('app_contact_email', 'salut@exemplu.ro');
    Settings::set('app_contact_website', 'https://www.exemplu.ro/dans/');
    Settings::set('app_contact_instagram', 'https://instagram.com/dxa');
    Settings::set('app_contact_facebook', 'https://facebook.com/dxa');

    $by = collect(ParticipantAppSettings::contacts())->keyBy('label');
    expect($by['Telefon']['group'])->toBe('contact')->and($by['Email']['group'])->toBe('contact')
        ->and($by['Site'])->toMatchArray(['group' => 'contact', 'text' => 'exemplu.ro/dans', 'href' => 'https://www.exemplu.ro/dans/'])
        ->and($by['Instagram'])->toMatchArray(['group' => 'social', 'text' => 'Instagram'])
        ->and($by['Facebook']['group'])->toBe('social');
});

it('subsolul: rânduri Contact / Social / Legal, fără butoane (chip), cu etichetele rețelelor', function () {
    test()->get('/')->assertOk()->assertDontSee('data-app-footer', false);

    Settings::set('app_contact_phone', '0722 111 222');
    Settings::set('app_contact_website', 'https://exemplu.ro');
    Settings::set('app_contact_instagram', 'https://instagram.com/dxa');
    Terms::publish('Termeni de probă', true);
    PrivacyPolicy::publish('Politică de probă');

    $html = test()->get('/')->assertOk()->getContent();
    expect($html)->toContain('data-foot-group="contact"')->toContain('data-foot-group="social"')->toContain('data-foot-group="legal"')
        ->and($html)->toContain('>exemplu.ro</a>')->toContain('>Instagram</a>')->toContain('>Termeni și condiții</a>')->toContain('>Politica de confidențialitate</a>')
        ->and($html)->not->toContain('pa-chip pa-chip-link" data-footer');

    // un rol fără date nu apare
    Settings::set('app_contact_instagram', '');
    expect(test()->get('/')->getContent())->not->toContain('data-foot-group="social"');

    // login / înregistrare: doar Legal
    $reg = test()->get('/inregistrare')->assertOk()->getContent();
    expect($reg)->toContain('data-foot-group="legal"')->not->toContain('data-foot-group="contact"');
});
