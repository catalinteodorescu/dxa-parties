<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 46 — permisiuni). `admin.can:secțiune[,nivel[,acțiune]]` — nivelul implicit e „view”; acțiunea
 * (opțională) e o bifă sensibilă cerută în plus (ex. export_pdf). Fără permisiune: pagina „Nu ai permisiunea” (403),
 * în layout-ul panoului, fără delogare.
 */
class EnsureAdminHasPermission
{
    public function handle(Request $request, Closure $next, string $section, string $level = 'view', ?string $action = null): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin
            && (! $admin->permits($section, $level) || ($action !== null && ! $admin->permitsAction($action)))) {
            $key = 'permission_denied_logged.'.$section;
            if (! $request->session()->has($key)) {
                $request->session()->put($key, true);
                ActivityLogger::log(
                    'admin.permission.denied',
                    'A fost refuzat la „'.(Permissions::SECTIONS[$section][0] ?? $section).'” (fără permisiune).',
                    $admin,
                    actor: null,
                );
            }

            return response()->view('errors.no-permission', [
                'section' => Permissions::SECTIONS[$section][0] ?? $section,
            ], 403);
        }

        return $next($request);
    }
}
