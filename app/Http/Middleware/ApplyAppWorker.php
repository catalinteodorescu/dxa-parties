<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DXA: adaugat (runda 73). Lucrătorul activ al unei aplicații (`admin.worker:reception|bar`). Schimbarea cu PIN din Recepție / Bar
 * se ține DOAR în sesiune, pe aplicație (`app_worker.<aplicație>`): contul cu care s-a făcut login nu se schimbă, deci Bar, Recepție și panoul
 * admin pot avea fiecare lucrătorul lor pe același telefon. Pe fiecare cerere, dacă există un lucrător valid (activ, cu PIN, cu acces la
 * aplicație), acesta devine utilizatorul cererii (în memorie, fără a atinge sesiunea de login); altfel marcajul se șterge.
 * E middleware persistent în Livewire (vezi AppServiceProvider), ca acțiunile Livewire să se înregistreze pe același lucrător.
 */
class ApplyAppWorker
{
    public static function key(string $app): string
    {
        return 'app_worker.'.$app;
    }

    /** Id-ul contului cu care s-a făcut login (nu al lucrătorului activ dintr-o aplicație). */
    public static function loginId(): ?int
    {
        $guard = Auth::guard('admin');
        $id = $guard->getSession()->get($guard->getName());

        return $id !== null ? (int) $id : null;
    }

    public function handle(Request $request, Closure $next, string $app = Admin::APP_RECEPTION): Response
    {
        $guard = Auth::guard('admin');
        $id = (int) $request->session()->get(self::key($app));

        if ($id > 0 && $guard->check()) {
            $worker = Admin::query()->find($id);

            if ($worker && $worker->is_active && $worker->pin !== null && $worker->canAccess($app)) {
                if ((int) $guard->id() !== $worker->id) {
                    $guard->setUser($worker);
                }
            } else {
                $request->session()->forget(self::key($app));
            }
        }

        return $next($request);
    }
}
