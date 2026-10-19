<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\Push\Notifier;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 70). Notificările push ale aplicației participanților: abonare/dezabonare (cont logat), test, și
 * `necitite` — apelat de service worker (fără sesiune, cu endpoint-ul abonării, care e secret) ca să afle textul notificării.
 */
class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        abort_unless(Notifier::enabled(), 404);
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Participant $me */
        $me = Auth::guard('participant')->user();
        Notifier::subscribe($me, $data['endpoint'], $data['keys']['p256dh'] ?? null, $data['keys']['auth'] ?? null, $request->userAgent());

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        Notifier::unsubscribe($data['endpoint'], Auth::guard('participant')->user());

        return response()->json(['ok' => true]);
    }

    /** O notificare de test către telefoanele proprii (butonul din Cont). */
    public function test(Request $request): JsonResponse
    {
        abort_unless(Notifier::enabled(), 404);
        $me = Auth::guard('participant')->user();
        $n = Notifier::notify($me, 'test', 'test:'.Str::uuid(), 'Notificări active', 'Așa vei primi vești de la '.Branding::name().'.', '/cont');

        return response()->json(['ok' => $n !== null], $n ? 200 : 409);
    }

    /** Pentru service worker: conținutul notificărilor în așteptare ale acestui telefon. Un endpoint necunoscut primește listă goală. */
    public function pending(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');

        return response()->json(['items' => $endpoint !== '' ? Notifier::pendingFor($endpoint) : []], 200, ['Cache-Control' => 'no-store']);
    }
}
