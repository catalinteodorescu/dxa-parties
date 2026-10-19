<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ruta /deploy răspunde 404 fără cheie configurată sau cu cheie greșită', function () {
    config(['deploy.key' => null]);
    $this->get('/deploy/oricare')->assertNotFound();

    config(['deploy.key' => 'cheie-corecta-123']);
    $this->get('/deploy/cheie-gresita')->assertNotFound();
});

it('ruta /deploy cu cheia corectă rulează migrările și curățarea fără sesiune', function () {
    config(['deploy.key' => 'cheie-corecta-123', 'deploy.clean_cache' => false]);

    $r = $this->get('/deploy/cheie-corecta-123')->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('text/plain')
        ->and($r->getContent())->toContain('[migrate] ok')->toContain('Gata.');
});

it('pasul ?step=up scoate aplicația din mentenanță', function () {
    config(['deploy.key' => 'cheie-corecta-123']);

    $this->get('/deploy/cheie-corecta-123?step=up')->assertOk()->assertSee('mentenanță oprită');
});

it('pagina 503 e statică și poartă numele aplicației', function () {
    config(['app.name' => 'Baile']);
    $html = view('errors.503')->render();

    expect($html)->toContain('Actualizăm aplicația')->toContain('Baile')->not->toContain('@vite');
});

it('workflow-ul de deploy pornește doar la cerere, nu la push', function () {
    $yml = file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($yml)->toContain('workflow_dispatch:')->not->toMatch('/^\s*push:/m')->toContain('FTP-Deploy-Action');
});

it('ruta /deploy rămâne accesibilă cât aplicația e în mentenanță, restul site-ului primește 503', function () {
    config(['deploy.key' => 'cheie-corecta-123']);
    app()->maintenanceMode()->activate(['retry' => 60]);

    try {
        $this->get('/')->assertStatus(503);
        $this->get('/deploy/cheie-corecta-123?step=up')->assertOk();
        expect(app()->isDownForMaintenance())->toBeFalse();
    } finally {
        if (app()->isDownForMaintenance()) {
            app()->maintenanceMode()->deactivate();
        }
    }
});
