<?php

use App\Contracts\SmsSender;
use App\Livewire\Admin\Legal\PrivacyPanel;
use App\Livewire\Admin\ParticipantApp\AppSettings as AdminParticipantAppSettings;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Participant\PartyShow;
use App\Models\Admin;
use App\Models\ParticipantVerification;
use App\Models\Party;
use App\Services\ParticipantAccounts;
use App\Services\ParticipantRegistry;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/** DXA: teste (runda 68). Pagina Legal (Termeni mutați + Politica de confidențialitate), secțiunea Legal scoasă din App participant, scroll la eroare, mesaj de cumpărare. */
function r68Admin(array $levels = ['legal' => 2], string $role = 'admin'): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => []],
    ]);
}

it('pagina Legal cere permisiunea „Legal”: fără ea 403, cu ea se văd ambele panouri', function () {
    test()->actingAs(r68Admin(['settings' => 3]), 'admin')->get(route('admin.legal.index'))->assertForbidden();
    test()->actingAs(r68Admin(['legal' => 1]), 'admin')->get(route('admin.legal.index'))->assertOk()
        ->assertSee('data-terms-panel', false)->assertSee('data-privacy-panel', false)->assertDontSee('data-terms-publish', false);
    test()->actingAs(r68Admin(['legal' => 2]), 'admin')->get(route('admin.legal.index'))->assertOk()->assertSee('data-terms-publish', false)->assertSee('data-privacy-publish', false);
    test()->actingAs(r68Admin([], 'superadmin'), 'admin')->get(route('admin.legal.index'))->assertOk();
});

it('Termenii nu mai sunt în Setări generale, iar meniul arată „Legal” doar cui are voie', function () {
    test()->actingAs(r68Admin(['settings' => 3, 'legal' => 2]), 'admin')->get(route('admin.settings.index'))->assertOk()->assertDontSee('data-terms-panel', false);
    test()->actingAs(r68Admin(['legal' => 1]), 'admin')->get(route('admin.legal.index'))->assertSee(route('admin.legal.index'), false);
    test()->actingAs(r68Admin(['settings' => 3]), 'admin')->get(route('admin.settings.index'))->assertDontSee(route('admin.legal.index'), false);
});

it('Politica: panoul pornește cu text de probă, publică versiuni și nu cere acceptare', function () {
    $admin = r68Admin();
    $c = Livewire::actingAs($admin, 'admin')->test(PrivacyPanel::class)->assertSet('body', PrivacyPolicy::sample());
    expect(PrivacyPolicy::enabled())->toBeFalse();

    $c->set('body', "# Date\n\nText final")->call('publish')->assertSet('error', '');
    expect(PrivacyPolicy::current()->body)->toBe("# Date\n\nText final")->and(PrivacyPolicy::current()->published_by)->toBe($admin->id);
    $c->assertSee('data-privacy-history', false)->call('publish')->assertSet('error', 'Textul nu s-a schimbat față de versiunea publicată.');
    $c->set('body', '')->call('publish')->assertSet('error', 'Scrie textul Politicii de confidențialitate.');

    // publicarea Politicii nu atinge acceptarea Termenilor
    expect(Terms::requiredId())->toBeNull();
});

it('panourile Legal refuză publicarea fără nivelul „Modificare” pe Legal', function () {
    $view = r68Admin(['legal' => 1, 'settings' => 3]);
    Livewire::actingAs($view, 'admin')->test(PrivacyPanel::class)->call('publish')->assertForbidden();
    expect(PrivacyPolicy::enabled())->toBeFalse();
});

it('în aplicație: /confidentialitate e 404 fără text, apoi se vede, are chip în subsol și link (fără bifă) la cont nou', function () {
    test()->get('/confidentialitate')->assertNotFound();
    test()->get('/inregistrare')->assertOk()->assertDontSee('data-register-privacy', false);

    PrivacyPolicy::publish("# Date\n\nNu vindem datele tale.");

    test()->get('/confidentialitate')->assertOk()->assertSee('Politica de confidențialitate')->assertSee('Nu vindem datele tale.')->assertSee('data-privacy-body', false);
    test()->get('/')->assertSee('data-footer-privacy', false);
    test()->get('/inregistrare')->assertOk()->assertSee('data-register-privacy', false)->assertDontSee('data-accept-terms', false);
});

it('Politica nu se acceptă: contul nou se creează fără nicio bifă, chiar dacă Politica e publicată', function () {
    PrivacyPolicy::publish('Politică de probă');

    ParticipantAccounts::startRegistration('Ana Politică', '0722680111', 'parola-sigura-123', app(SmsSender::class));
    expect(ParticipantVerification::query()->count())->toBe(1);
});

it('App participant nu mai are secțiunea Legal (linkurile externe au dispărut)', function () {
    test()->actingAs(r68Admin(['settings' => 3]), 'admin')->get(route('admin.settings.participant-app'))->assertOk()
        ->assertDontSee('Termeni și condiții (link)')->assertDontSee('Politica de confidențialitate (link)')->assertSee('Texte SMS');
});

it('migrarea acordă „Legal” adminilor care puteau modifica Setările', function () {
    $edit = r68Admin(['settings' => 3]);
    $view = r68Admin(['settings' => 1]);
    $none = r68Admin(['parties' => 3]);
    $already = r68Admin(['settings' => 3, 'legal' => 0]);

    Schema::dropIfExists('privacy_versions');
    (require base_path('database/migrations/2024_08_10_000001_create_privacy_versions.php'))->up();

    expect($edit->fresh()->permissionLevel('legal'))->toBe(Permissions::EDIT)
        ->and($view->fresh()->permissionLevel('legal'))->toBe(Permissions::VIEW)
        ->and($none->fresh()->permissionLevel('legal'))->toBe(0)
        ->and($already->fresh()->permissionLevel('legal'))->toBe(0);
});

it('salvare cu erori: Setări generale și App participant cer derularea la primul câmp invalid', function () {
    test()->actingAs(r68Admin(['settings' => 3]), 'admin');

    Livewire::test(SettingsIndex::class)->set('values.school_name', '')->call('save')->assertHasErrors('values.school_name')->assertDispatched('dxa-scroll-error');
    Livewire::test(SettingsIndex::class)->set('values.school_name', 'Studio Ritm')->call('save')->assertHasNoErrors()->assertNotDispatched('dxa-scroll-error');
    Livewire::test(AdminParticipantAppSettings::class)->set('values.app_home_parties', '99')->call('save')->assertHasErrors('values.app_home_parties')->assertDispatched('dxa-scroll-error');
    test()->get(route('admin.settings.index'))->assertSee('dxa-scroll-error', false);
});

it('la cumpărare nu mai apare mesajul despre biletele libere trimise altcuiva', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00'));
    $party = Party::create([
        'name' => 'Petrecere mesaje', 'kind' => 'basic', 'start_date' => '2026-10-03', 'start_time' => '21:00', 'end_time' => '03:00',
        'is_free' => false, 'audience' => 'all', 'in_carousel' => false, 'is_active' => true, 'status' => 'published',
        'payment_methods' => ['cash'], 'online_sales' => true,
        'ticket_types' => [['name' => 'Bilet', 'price' => 50, 'discounts' => [], 'qty_tiers' => []]],
    ]);
    $ana = ParticipantRegistry::create('Ana Mesaje', '0722680222');
    $ana->forceFill(['password' => 'parola-sigura', 'phone_verified_at' => now()])->save();

    Livewire::actingAs($ana->fresh(), 'participant')->test(PartyShow::class, ['party' => $party->id])
        ->call('selectTicket', 'Bilet')->set('qty', 3)
        ->assertSee('Primul bilet e pe numele tău.')->assertDontSee('le poți trimite altcuiva');
});
