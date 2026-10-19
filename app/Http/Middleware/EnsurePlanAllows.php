<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 79 — planuri). `plan:cheie` — pagina există doar dacă planul instalării include funcția (proprietarul trece mereu).
 * Altfel: pagina „disponibil în Pro”, în layout-ul panoului. Se pune pe lângă `admin.can`, pentru funcțiile care nu sunt o secțiune de permisiuni.
 */
class EnsurePlanAllows
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin && Features::exists($feature) && ! $admin->planAllows($feature)) {
            return response()->view('errors.plan-locked', ['feature' => $feature], 403);
        }

        return $next($request);
    }
}
