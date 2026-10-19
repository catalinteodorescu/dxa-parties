<?php

namespace App\Http\Middleware;

use App\Services\Plan\PlanGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 80 — planuri în aplicații). `plan.app:cheie` pe rutele aplicațiilor (participant, Recepție, Bar).
 *
 *  - cheie de aplicație (`app_*`): toată aplicația e indisponibilă pe planul instalării (503); manifestul, service worker-ul,
 *    iconițele, contoarele, webhook-ul Stripe și citirea notificărilor rămân deschise, ca instalările existente să nu dea erori;
 *  - altă funcție: pagina nu există pe planul acesta (403, cu mesaj), iar în meniurile aplicației linkul lipsește.
 * Planul se citește direct din `PlanGate` (în aplicații nu există proprietar și nici admin cu permisiuni de verificat).
 */
class EnsureAppPlan
{
    /** Rute tehnice care nu se opresc odată cu aplicația. */
    private const ALWAYS_OPEN = [
        'app.manifest', 'app.sw', 'app.icon', 'app.metrics', 'app.webhooks.stripe', 'app.push.pending',
        'receptie.manifest', 'receptie.sw', 'receptie.icon', 'bar.manifest', 'bar.sw', 'bar.icon',
    ];

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (PlanGate::allows($feature)) {
                continue;
            }

            if (str_starts_with($feature, 'app_')) {
                if ($request->routeIs(...self::ALWAYS_OPEN)) {
                    continue;
                }

                return response()->view('errors.app-unavailable', ['feature' => $feature, 'whole' => true], 503);
            }

            return response()->view('errors.app-unavailable', ['feature' => $feature, 'whole' => false], 403);
        }

        return $next($request);
    }
}
