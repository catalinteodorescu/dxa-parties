<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 52). Pentru zona cu cont a aplicației participanților: (1) un cont șters/anonimizat nu mai poate folosi o sesiune rămasă
 * deschisă pe alt dispozitiv; (2) runda 75: ține minte ultima activitate. Conturile nu se mai șterg automat pentru inactivitate (runda 59).
 */
class TouchParticipantActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('participant');
        $me = $guard->user();

        if ($me && $me->isAnonymized()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('app.home');
        }

        // DXA: runda 75 — ultima activitate (cel mult o dată pe oră), pentru blocul „Aplicație” din dashboard.
        if ($me && (! $me->last_seen_at || $me->last_seen_at->lt(now()->subHour()))) {
            $me->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
