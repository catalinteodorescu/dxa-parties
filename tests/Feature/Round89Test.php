<?php

use App\Support\ParticipantAppSettings;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Blade;

function r89View(string $path): string
{
    return file_get_contents(resource_path("views/{$path}.blade.php"));
}

it('șabloanele SMS implicite nu mai conțin „DXA”, ci numele aplicației', function () {
    foreach (['app_sms_activation', 'app_sms_reset', 'app_sms_ticket'] as $k) {
        expect(SettingsRegistry::field($k)['default'])->not->toContain('DXA')->toContain('{aplicatie}');
    }
    expect(ParticipantAppSettings::smsActivation('123456'))->not->toContain('DXA');
});

it('ștergerea are iconița coș de gunoi la liniile de bon, metodele de plată, bilete și participanți', function () {
    foreach (['bar/sale', 'reception/entry', 'reception/_sale-payment', 'partials/entry-tickets', 'admin/_participant-picker'] as $v) {
        expect(r89View("livewire/{$v}"))->toContain('<x-icon-trash');
    }
    expect(r89View('livewire/bar/sale'))->not->toContain('tooltip="Scoate"')
        ->and(Blade::render('<x-icon-trash class="w-4 h-4" />'))->toContain('<svg')->toContain('w-4 h-4');
});

it('la bar „−” e dezactivat când produsul ajunge la 1 (se scoate doar cu coșul de gunoi)', function () {
    expect(r89View('livewire/bar/sale'))->toContain('x-on:click="set(q - 1)"')->toContain('x-bind:disabled="q <= 1"');
});

it('tooltip-ul nu mai apare pe ecrane tactile (ieșea din ecran și făcea scroll orizontal)', function () {
    $html = Blade::render('<x-btn tooltip="Participant nou">+</x-btn>');

    expect($html)->toContain("matchMedia('(hover: hover)')")->toContain('tip && canHover');
});

it('numărul de persoane și de tokeni se schimbă local, la server ajunge o singură dată', function () {
    foreach (['reception/entry' => "\$wire.set('count'", 'reception/token-sale' => "\$wire.set('tokens'"] as $v => $call) {
        expect(r89View("livewire/{$v}"))->toContain($call)->toContain('x-on:click="set(q + 1)"')->not->toContain('wire:click="stepCount')->not->toContain('wire:click="stepTokens');
    }
});

it('lista de participanți din aplicații e un panou evident, cu rânduri înalte', function () {
    $v = r89View('livewire/admin/_participant-picker');

    expect($v)->toContain('Alege participantul')->toContain('min-h-16')->toContain('border-primary/40');
});

it('butoanele de înregistrare și cele din Intrare / Vânzare au dimensiuni mari', function () {
    $btn = r89View('components/btn');

    expect($btn)->toContain("'icon-lg' => 'h-12 w-12")->toContain("'lg'   => 'h-12");
    foreach (['bar/sale', 'reception/entry', 'reception/_sale-payment'] as $v) {
        expect(r89View("livewire/{$v}"))->toContain('py-2.5 text-sm font-medium')->toContain('+ Altă metodă');
    }
});
