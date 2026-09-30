<?php

use App\Http\Middleware\EnsureAdminHasAccess;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminPhoneIsSetUp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            default => route('app.login'),
        });
        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('receptie', 'receptie/*') => route('receptie.home'),
            $request->is('bar', 'bar/*') => route('bar.home'),
            $request->is('admin', 'admin/*') => route('admin.dashboard'),
            default => route('app.home'),
        });

        $middleware->alias([
            'admin.phone_setup' => EnsureAdminPhoneIsSetUp::class,
            'admin.active' => EnsureAdminIsActive::class,
            'admin.access' => EnsureAdminHasAccess::class, // DXA: adaugat (Utilizatori - acces pe aplicație)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
