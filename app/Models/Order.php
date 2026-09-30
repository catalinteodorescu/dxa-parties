<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DXA: adaugat (Aplicația participanților - runda 14). O comandă de bilete a unui participant la o petrecere.
 * Se creează DOAR prin App\Services\TicketOrders::place().
 */
class Order extends Model
{
    public const PAY_AT_ENTRY = 'at_entry';

    protected $fillable = ['uuid', 'participant_id', 'party_id', 'tickets_count', 'subtotal', 'discount_total', 'total', 'discount_code_id', 'payment_status'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('id');
    }
}
