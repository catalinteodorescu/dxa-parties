<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (runda 70). Livrarea unei notificări către o abonare: `sent_at` = cererea către browser a plecat; `delivered_at` = service worker-ul a citit conținutul. */
class PushDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['push_notification_id', 'push_subscription_id', 'sent_at', 'delivered_at', 'status'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }
}
