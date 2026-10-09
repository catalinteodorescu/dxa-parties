<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DXA: adaugat (runda 70). Un telefon/browser abonat la notificări push. Endpoint-ul e dat de browser și e secret (nu se afișează). */
class PushSubscription extends Model
{
    protected $fillable = ['participant_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'user_agent', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class);
    }
}
