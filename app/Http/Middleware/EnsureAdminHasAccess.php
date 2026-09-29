<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (Utilizatori - acces pe aplicație). Verifică pe FIECARE cerere că utilizatorul autentificat are
 * bifa de acces a aplicației (`admin.access:admin|bar|reception`), nu doar la login — dacă un superadmin scoate
 * accesul, utilizatorul e blocat imediat. Fără acces: pagina „Nu ai acces la această aplicație” (403), FĂRĂ
 * delogare, ca sesiunea (comună pe același domeniu) să rămână valabilă în aplicațiile la care ARE acces.
 */
class EnsureAdminHasAccess
{
    public function handle(Request $request, Closure $next, string $app = Admin::APP_ADMIN): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->canAccess($app)) {
            // Un singur rând în jurnal per sesiune și aplicație (nu la fiecare cerere refuzată).
            $key = 'access_denied_logged.'.$app;
            if (! $request->session()->has($key)) {
                $request->session()->put($key, true);
                ActivityLogger::log(
                    'admin.access.denied',
                    'A fost refuzat la '.mb_strtolower(Admin::APP_LABELS[$app] ?? $app).' (fără acces).',
                    $admin,
                    actor: null,
                );
            }

            return response()->view('errors.no-access', ['admin' => $admin, 'app' => $app], 403);
        }

        return $next($request);
    }
}
