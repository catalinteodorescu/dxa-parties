<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyAppWorker;
use App\Models\Admin;
use App\Services\Push\StaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 77). Notificările push ale personalului: abonare / dezabonare / test, per telefon și per aplicație
 * (reception | bar, dată prin `defaults('app')` pe rută). Abonarea aparține CONTULUI cu care s-a făcut login, nu lucrătorului
 * activ din aplicație (schimbat cu PIN). Textul notificărilor îl cere service worker-ul de la `app.push.pending` (ca la participanți).
 */
class StaffPushController extends Controller
{
    public function subscribe(Request $request, string $app): JsonResponse
    {
        abort_unless(StaffNotifier::enabled(), 404);
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
        ]);
        $account = $this->account($app);
        StaffNotifier::subscribe($account, $app, $data['endpoint'], $data['keys']['p256dh'] ?? null, $data['keys']['auth'] ?? null, $request->userAgent());

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request, string $app): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        StaffNotifier::unsubscribe($data['endpoint'], $this->account($app));

        return response()->json(['ok' => true]);
    }

    /** O notificare de test către telefoanele proprii ale contului, în aplicația respectivă. */
    public function test(string $app): JsonResponse
    {
        abort_unless(StaffNotifier::enabled(), 404);
        $n = StaffNotifier::notify($this->account($app), 'test', 'test:'.Str::uuid(), 'Notificări active', 'Așa vei primi alertele de la Dance Xplosion Academy.',
            $app === 'bar' ? '/bar' : '/receptie', $app);

        return response()->json(['ok' => $n !== null], $n ? 200 : 409);
    }

    /** Popup-ul de la login a fost tratat (activat, sau „Mai târziu”): nu mai apare până la următorul login. */
    public function prompted(string $app): JsonResponse
    {
        abort_unless(in_array($app, StaffNotifier::APPS, true), 404);
        session()->forget('staff_push_prompt.'.$app);

        return response()->json(['ok' => true]);
    }

    private function account(string $app): Admin
    {
        abort_unless(in_array($app, StaffNotifier::APPS, true), 404);
        $id = ApplyAppWorker::loginId();
        /** @var Admin|null $account */
        $account = ($id ? Admin::query()->find($id) : null) ?? Auth::guard('admin')->user();
        abort_unless($account && $account->is_active && $account->canAccess($app), 403);

        return $account;
    }
}
