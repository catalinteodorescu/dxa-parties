<?php

namespace App\Http\Middleware;

use App\Auth\ZoneGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 74). `admin.zone:reception|bar|admin` — alege zona de autentificare a cererii (vezi ZoneGuard). Fără parametru,
 * zona vine din câmpul `to` (deconectarea comună). E middleware persistent în Livewire, ca acțiunile să rămână în aceeași zonă.
 */
class ApplyAuthZone
{
    public const ZONES = ['admin', 'reception', 'bar'];

    public function handle(Request $request, Closure $next, ?string $zone = null): Response
    {
        $zone ??= match ($request->input('to')) {
            'receptie' => 'reception',
            'bar' => 'bar',
            default => 'admin',
        };

        $guard = Auth::guard('admin');

        if ($guard instanceof ZoneGuard) {
            $guard->setZone(in_array($zone, self::ZONES, true) ? $zone : 'admin');
        }

        return $next($request);
    }

    /** Ieșire din zona curentă: nu atinge celelalte zone (nici conturile de participant din același browser). */
    public static function logout(Request $request): string
    {
        $guard = Auth::guard('admin');
        $zone = $guard instanceof ZoneGuard ? $guard->zone() : 'admin';

        $guard->logout();
        $request->session()->forget(ApplyAppWorker::key($zone));
        $request->session()->regenerateToken();

        return $zone;
    }

    public static function loginRoute(?string $zone = null): string
    {
        $guard = Auth::guard('admin');
        $zone ??= $guard instanceof ZoneGuard ? $guard->zone() : 'admin';

        return match ($zone) {
            'reception' => 'receptie.login',
            'bar' => 'bar.login',
            default => 'admin.login',
        };
    }
}
