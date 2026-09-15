<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->is_active) {
            ActivityLogger::log(
                'admin.session.terminated_inactive',
                'A fost delogat automat (cont dezactivat).',
                $admin,
                actor: null,
            );

            Auth::guard('admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Contul tău a fost dezactivat.');
        }

        return $next($request);
    }
}
