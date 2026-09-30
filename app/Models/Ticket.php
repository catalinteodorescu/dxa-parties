<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Aplicația participanților - runda 14). Un bilet cumpărat din aplicație, cu QR propriu (`DXA:T:<uuid>`).
 * Se creează DOAR prin App\Services\TicketOrders::place(). Biletul e valabil până devine intrare la Recepție (`used`) sau e `void`.
 */
class Ticket extends Model
{
    public const QR_PREFIX = 'DXA:T:';

    public const VALID = 'valid';

    public const USED = 'used';

    public const VOID = 'void';

    protected $fillable = [
        'uuid', 'order_id', 'party_id', 'ticket_type', 'owner_participant_id', 'holder_participant_id', 'holder_phone',
        'list_price', 'discount_amount', 'price', 'discount_code_id', 'valid_until', 'status', 'party_entry_id', 'used_at',
    ];

    protected function casts(): array
    {
        return ['list_price' => 'decimal:2', 'discount_amount' => 'decimal:2', 'price' => 'decimal:2', 'used_at' => 'datetime', 'valid_until' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $t) {
            $t->uuid ??= (string) Str::uuid();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'owner_participant_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'holder_participant_id');
    }

    /** Biletele care încă ocupă un loc (valabile sau deja folosite); cele anulate nu. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('status', '!=', self::VOID);
    }

    /** Termenul de intrare cu prețul plătit a trecut (doar biletele cu „intri până la"). */
    public function isExpired(?Carbon $at = null): bool
    {
        return $this->valid_until !== null && ($at ?? now())->greaterThan($this->valid_until);
    }

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->uuid;
    }

    public static function findByQrPayload(string $payload): ?self
    {
        $code = trim($payload);
        if (str_starts_with($code, self::QR_PREFIX)) {
            $code = substr($code, strlen(self::QR_PREFIX));
        }

        return $code !== '' ? self::query()->where('uuid', $code)->first() : null;
    }

    public function isValid(): bool
    {
        return $this->status === self::VALID;
    }
}
