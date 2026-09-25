<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (Recepție - intrări). O plată (metodă + sumă) a unei intrări. Vezi EntryRecorder. */
class PartyEntryPayment extends Model
{
    protected $fillable = ['party_entry_id', 'method', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PartyEntry::class, 'party_entry_id');
    }
}
