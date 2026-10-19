<?php

use App\Livewire\Admin\Legal\OrganizerPanel;
use App\Livewire\Admin\Legal\PrivacyPanel;
use App\Livewire\Admin\Legal\TermsPanel;
use App\Models\Admin;
use App\Services\LegalTemplates;
use App\Services\PrivacyPolicy;
use App\Services\Terms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** DXA: teste (runda 83). Modelele legale implicite, datele organizatorului, blocarea publicării cu câmpuri necompletate, comanda de instalare. */
function r83Admin(array $levels = ['legal' => 2], string $role = 'admin'): Admin
{
    return Admin::create([
        'name' => 'Admin '.random_int(1, 99999), 'phone' => '07'.random_int(10000000, 99999999),
        'role' => $role, 'is_active' => true, 'password' => 'secret-pass',
        'permissions' => ['levels' => $levels, 'actions' => []],
    ]);
}

function r83Data(): array
{
    return [
        'organizer_name' => 'Club Salsa SRL', 'organizer_cui' => 'RO123456', 'organizer_regcom' => 'J32/1/2020',
        'organizer_address' => 'Str. Mare 1, Sibiu', 'organizer_email' => 'contact@club.ro', 'organizer_phone' => '0722000000',
    ];
}

it('r83: modelele conțin doar variabile cunoscute, fără paranteze drepte și fără „școală”', function () {
    foreach (array_keys(LegalTemplates::KINDS) as $kind) {
        $text = LegalTemplates::template($kind);
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
        expect(array_diff(array_unique($m[1]), array_keys(LegalTemplates::values())))->toBe([])
            ->and(mb_strtolower($text))->not->toContain('școal')->not->toContain('scoal')
            ->and($text)->not->toMatch('/\[[A-ZĂÂÎȘȚ][^\]]*\]/');
    }
});

it('r83: fill completează variabilele, iar unresolved le listează pe cele rămase', function () {
    expect(LegalTemplates::fill('A {{organizer_name}} B', ['organizer_name' => 'X']))->toBe('A X B');
    expect(LegalTemplates::unresolved('A {{organizer_cui}} {{organizer_cui}}'))->toBe(['CUI']);

    LegalTemplates::saveOrganizer(r83Data());
    expect(LegalTemplates::unresolved(LegalTemplates::rendered('terms')))->toBe([])
        ->and(LegalTemplates::rendered('terms'))->toContain('Club Salsa SRL')
        ->and(LegalTemplates::rendered('privacy'))->toContain('RO123456')->toContain('Selimbar');
});

it('r83: publicarea e blocată cu câmpuri necompletate și permisă după ce organizatorul e completat', function () {
    $empty = LegalTemplates::fill('Text {{organizer_cui}}');
    expect(fn () => Terms::publish($empty, false))->toThrow(DomainException::class, 'necompletate')
        ->and(fn () => PrivacyPolicy::publish($empty))->toThrow(DomainException::class, 'necompletate');

    LegalTemplates::saveOrganizer(r83Data());
    Terms::publish(LegalTemplates::rendered('terms'), false);
    PrivacyPolicy::publish(LegalTemplates::rendered('privacy'));
    expect(Terms::enabled())->toBeTrue()->and(PrivacyPolicy::enabled())->toBeTrue();
});

it('r83: panourile pornesc de la model dacă nimic nu e publicat; după completare, „refill” păstrează modificările', function () {
    $admin = r83Admin();
    Livewire::actingAs($admin, 'admin')->test(TermsPanel::class)->assertSet('body', LegalTemplates::rendered('terms'));

    $c = Livewire::actingAs($admin, 'admin')->test(PrivacyPanel::class)->set('body', 'Text propriu {{organizer_cui}}');
    LegalTemplates::saveOrganizer(r83Data());
    $c->dispatch('organizer-saved')->assertSet('body', 'Text propriu RO123456');
});

it('r83: „Reîncarcă modelul” readuce modelul implicit', function () {
    LegalTemplates::saveOrganizer(r83Data());
    Livewire::actingAs(r83Admin(), 'admin')->test(TermsPanel::class)
        ->set('body', 'altceva')->call('reloadModel')->assertSet('body', LegalTemplates::rendered('terms'));
});

it('r83: panoul Date organizator validează, salvează și cere permisiunea de editare', function () {
    $admin = r83Admin();

    // e-mail invalid (pornim de la valori deja salvate, panoul le încarcă la montare)
    LegalTemplates::saveOrganizer(['organizer_email' => 'nu-e-email'] + r83Data());
    Livewire::actingAs($admin, 'admin')->test(OrganizerPanel::class)->call('save')->assertHasErrors('org.organizer_email')->assertNotDispatched('organizer-saved');

    LegalTemplates::saveOrganizer(r83Data());
    Livewire::actingAs($admin, 'admin')->test(OrganizerPanel::class)->call('save')->assertHasNoErrors()->assertDispatched('organizer-saved');
    expect(LegalTemplates::organizer()['organizer_cui'])->toBe('RO123456')
        ->and(LegalTemplates::missingOrganizerFields())->toBe([]);

    Livewire::actingAs(r83Admin(['legal' => 1]), 'admin')->test(OrganizerPanel::class)->call('save')->assertForbidden();
    expect(LegalTemplates::organizer()['organizer_cui'])->toBe('RO123456');
});

it('r83: pagina Legal arată panoul Date organizator', function () {
    test()->actingAs(r83Admin(), 'admin')->get(route('admin.legal.index'))->assertOk()->assertSee('data-organizer-save', false);
});

it('r83: app:install-legal fără date nu publică nimic și listează ce lipsește', function () {
    $code = Artisan::call('app:install-legal', ['--name' => 'Club']);
    expect($code)->toBe(1)->and(Artisan::output())->toContain('CUI')
        ->and(Terms::enabled())->toBeFalse()->and(PrivacyPolicy::enabled())->toBeFalse();
});

it('r83: app:install-legal salvează datele și publică ambele texte; a doua rulare nu schimbă nimic', function () {
    $args = ['--name' => 'Club Salsa SRL', '--cui' => 'RO123456', '--regcom' => 'J32/1/2020', '--address' => 'Str. Mare 1, Sibiu', '--email' => 'contact@club.ro', '--phone' => '0722000000'];
    expect(Artisan::call('app:install-legal', $args))->toBe(0)
        ->and(Terms::enabled())->toBeTrue()->and(PrivacyPolicy::enabled())->toBeTrue()
        ->and(Terms::current()->requires_reaccept)->toBeTrue()
        ->and(Terms::current()->body)->toContain('Club Salsa SRL');

    $id = Terms::current()->id;
    Artisan::call('app:install-legal');
    expect(Terms::current()->id)->toBe($id)->and(Artisan::output())->toContain('deja publicați');
});

it('r83: app:install-legal --update publică o versiune nouă doar dacă textul s-a schimbat', function () {
    $args = ['--name' => 'Club', '--cui' => 'RO1', '--regcom' => 'J1/1/1', '--address' => 'Sibiu', '--email' => 'a@b.ro', '--phone' => '0722000000'];
    Artisan::call('app:install-legal', $args);
    $id = Terms::current()->id;

    Artisan::call('app:install-legal', ['--update' => true]);   // același text: refuzat
    expect(Terms::current()->id)->toBe($id);

    Artisan::call('app:install-legal', ['--update' => true, '--address' => 'Brașov']);
    expect(Terms::current()->id)->toBeGreaterThan($id)->and(Terms::current()->requires_reaccept)->toBeFalse();
});

it('r83: domeniul din text vine din APP_URL', function () {
    config(['app.url' => 'https://parties.exemplu.ro']);
    expect(LegalTemplates::domain())->toContain('parties.exemplu.ro');
});
