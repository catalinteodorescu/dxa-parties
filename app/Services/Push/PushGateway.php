<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

/** DXA: adaugat (runda 70). Trimite „semnalul” de push către un browser abonat (fără conținut: textul îl citește service worker-ul de pe server). */
interface PushGateway
{
    /** Codul HTTP al serviciului de push (201 = acceptat; 404/410 = abonare dispărută; 0 = fără răspuns). */
    public function send(PushSubscription $subscription, string $urgency = 'normal'): int;
}
