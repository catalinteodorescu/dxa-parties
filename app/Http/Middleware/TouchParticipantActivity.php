<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 52). Pentru zona cu cont a aplicației participanților: (1) un cont șters/anonimizat nu mai poate folosi o sesiune rămasă
 * deschisă pe alt dispozitiv. Conturile nu se mai șterg automat pentru inactivitate (runda 59).
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

        return $next($request);
    }
}
