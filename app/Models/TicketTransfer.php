<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DXA: adaugat (runda 39). O trimitere de bilet de la un participant la altul; doar se adaugă, nu se modifică. */
class TicketTransfer extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'from_participant_id', 'to_participant_id', 'to_phone', 'to_had_account', 'sms_sent'];

    protected function casts(): array
    {
        return ['to_had_account' => 'boolean', 'sms_sent' => 'boolean', 'created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'from_participant_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'to_participant_id');
    }
}
