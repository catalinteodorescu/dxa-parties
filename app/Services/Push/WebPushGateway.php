<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Throwable;

/** DXA: adaugat (runda 70). Web Push fără payload: POST gol către endpoint, cu antet VAPID. TTL 12 h (un semnal vechi nu mai are rost). */
class WebPushGateway implements PushGateway
{
    public function send(PushSubscription $subscription, string $urgency = 'normal'): int
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => Vapid::authorization($subscription->endpoint),
                    'TTL' => '43200',
                    'Urgency' => $urgency,
                ])
                ->withBody('', 'application/octet-stream')
                ->post($subscription->endpoint);

            return $response->status();
        } catch (Throwable) {
            return 0;
        }
    }
}
