<?php

use App\Http\Middleware\ApplyAppWorker;
use App\Http\Middleware\ApplyAuthZone;
use App\Http\Middleware\EnsureAdminHasAccess;
use App\Http\Middleware\EnsureAdminHasPermission;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminPhoneIsSetUp;
use App\Http\Middleware\EnsureAppPlan;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\EnsurePlanAllows;
use App\Http\Middleware\TouchParticipantActivity;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // DXA: adaugat (PWA Recepție): aplicația de recepție are propriul login și propria pagină de start.
        // DXA: adaugat (Aplicația participanților): zonele de contul participantului (/cont, /iesire, /intra ...) au login propriu.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('receptie', 'receptie/*') => route('receptie.login'),
            $request->is('bar', 'bar/*') => route('bar.login'),
            $request->is('admin', 'admin/*') => route('admin.login'),
            // Runda 87: deconectarea comună (/sesiune/logout) cu sesiunea deja expirată ajungea în aplicația participanților.
            $request->is('sesiune/logout') => route(match ($request->input('to')) {
                'receptie' => 'receptie.login',
                'bar' => 'bar.login',
                default => 'admin.login',
            }),
            default => route('app.login'),
        });
        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('receptie', 'receptie/*') => route('receptie.home'),
            $request->is('bar', 'bar/*') => route('bar.home'),
            $request->is('admin', 'admin/*') => route('admin.dashboard'),
            default => route('app.home'),
        });

        // DXA: adaugat (runda 40). Contoarele din aplicație (sendBeacon, fără token): doar numără, nu citesc nimic.
        // DXA: runda 70 — `notificari/necitite` e apelat de service worker (fără sesiune), cu endpoint-ul secret al abonării.
        // DXA: runda 64 — webhook-ul Stripe vine de la server, fără token CSRF; autenticitatea se verifică prin semnătura Stripe.
        // Deploy: ruta /deploy/* trebuie să meargă și cât aplicația e în mentenanță (pașii „down” și „up” din workflow).
        $middleware->preventRequestsDuringMaintenance(except: ['deploy/*']);

        $middleware->validateCsrfTokens(except: ['m', 'webhooks/stripe', 'notificari/necitite']);

        // DXA: runda 46 — permisiunea se verifică ÎNAINTE de model binding (fără permisiune → 403, nu 404 care ar trăda existența).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureAdminHasPermission::class);

        // DXA: runda 74 — zona de autentificare se alege ÎNAINTE de verificarea autentificării.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ApplyAuthZone::class);

        $middleware->alias([
            'admin.zone' => ApplyAuthZone::class, // DXA: adaugat (runda 74 - login separat pe zonă)
            'admin.phone_setup' => EnsureAdminPhoneIsSetUp::class,
            'admin.active' => EnsureAdminIsActive::class,
            'admin.access' => EnsureAdminHasAccess::class, // DXA: adaugat (Utilizatori - acces pe aplicație)
            'admin.worker' => ApplyAppWorker::class, // DXA: adaugat (runda 73 - lucrătorul activ pe aplicație)
            'plan' => EnsurePlanAllows::class, // DXA: adaugat (runda 79 - planuri)
            'plan.app' => EnsureAppPlan::class, // DXA: adaugat (runda 80 - planuri în aplicații)
            'owner' => EnsureOwner::class, // DXA: adaugat (runda 79 - pagina proprietarului)
            'admin.can' => EnsureAdminHasPermission::class, // DXA: adaugat (runda 46 - permisiuni)
            'participant.active' => TouchParticipantActivity::class, // DXA: adaugat (runda 52)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
