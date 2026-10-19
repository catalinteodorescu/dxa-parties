<?php

// DXA: adaugat (runda 78). Fonturile se servesc local; nicio pagină nu mai încarcă nimic de la Google.

it('fișierele fontului Manrope există local și CSS-ul le referă', function () {
    $css = file_get_contents(public_path('fonts/manrope.css'));

    expect(public_path('fonts/manrope-latin-wght-normal.woff2'))->toBeFile()
        ->and(public_path('fonts/manrope-latin-ext-wght-normal.woff2'))->toBeFile()
        ->and($css)->toContain("font-family: 'Manrope'")
        ->toContain('manrope-latin-wght-normal.woff2')
        ->toContain('manrope-latin-ext-wght-normal.woff2')
        ->not->toContain('googleapis')->not->toContain('gstatic');
});

it('nicio pagină nu mai încarcă fonturi de la Google', function () {
    $dirs = [resource_path('views/layouts'), resource_path('views/errors')];
    foreach ($dirs as $dir) {
        foreach (glob($dir.'/*.php') as $file) {
            expect(file_get_contents($file))->not->toContain('fonts.googleapis.com')->not->toContain('fonts.gstatic.com');
        }
    }
});

it('paginile de login din fiecare zonă referă fontul local', function () {
    foreach (['/admin/login', '/intra'] as $url) {
        $this->get($url)->assertOk()->assertSee('fonts/manrope.css', false)->assertDontSee('fonts.googleapis.com', false);
    }
});
