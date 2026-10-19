<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** DXA: adaugat (runda 79). Pagina proprietarului: oricine altcineva primește 404, ca să nu se știe că există. */
class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        abort_unless($admin instanceof Admin && $admin->isOwner(), 404);

        return $next($request);
    }
}
