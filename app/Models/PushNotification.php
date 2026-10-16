<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DXA: adaugat (runda 70). O notificare trimisă unui participant. `dedupe_key` e unic per participant: același eveniment nu se trimite de două ori. */
class PushNotification extends Model
{
    public const UPDATED_AT = null;

    public const KIND_LABELS = [
        'party_new' => 'Petrecere nouă',
        'price' => 'Prețul crește',
        'seats' => 'Puține locuri',
        'buyers' => 'Bilete luate de alții',
        'tomorrow' => 'Petrecerea e mâine',
        'ready' => 'Cu 2 ore înainte',
        'ticket_new' => 'Bilet cumpărat sau primit',
        'ticket_cancelled' => 'Bilet anulat',
        'credit_payment' => 'Plată cu credite',
        'credit_load' => 'Încărcare credite',
        'card_ready' => 'Card de fidelitate complet',
        'announcement' => 'Anunț nou',
        'test' => 'Test',
    ];

    protected $fillable = ['participant_id', 'admin_id', 'app', 'kind', 'dedupe_key', 'title', 'body', 'url'];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class);
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }
}
