<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (runda 40). O petrecere salvată (inima) de un participant. */
class PartyInterest extends Model
{
    public $timestamps = false;

    protected $fillable = ['participant_id', 'party_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
