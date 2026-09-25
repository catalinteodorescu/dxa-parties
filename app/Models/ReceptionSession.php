<?php

namespace App\Models;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (Recepție - sesiuni). Sesiunea de recepție a unei petreceri, ca sesiunea de vânzări de la bar:
 * se deschide singură la prima intrare / vânzare de tokeni, o singură sesiune deschisă per petrecere, se închide la
 * finalizarea raportării de recepție (ReceptionReport::finalize()). O sesiune închisă nu mai primește înregistrări.
 * Intrările și vânzările de tokeni o poartă în `reception_session_id`; sesiunea e și „aceeași seară” pentru dedupe-ul
 * participanților (ParticipantRegistry::enteredInSession).
 */
class ReceptionSession extends Model
{
    public const STATUSES = [
        'open' => 'Deschisă',
        'closed' => 'Închisă',
    ];

    /** După cât timp o sesiune deschisă e semnalată ca „uitată” (alerta din Recepție și de pe dashboard). */
    public const STALE_HOURS = 12;

    protected $fillable = [
        'party_id',
        'session_number',
        'status',
        'opened_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'opened_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartyEntry::class);
    }

    /** Vânzările de tokeni ale sesiunii (doar tipul „sold”: ajustările și casările nu au sesiune). */
    public function tokenSales(): HasMany
    {
        return $this->hasMany(TokenTransaction::class)->where('type', TokenTransaction::SOLD);
    }

    public function report(): HasOne
    {
        return $this->hasOne(ReceptionReport::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Deschisă de prea mult timp (probabil o seară uitată deschisă). */
    public function isStale(): bool
    {
        return $this->isOpen() && $this->created_at !== null && $this->created_at->lt(now()->subHours(self::STALE_HOURS));
    }

    /**
     * „Sesiune 2 · 25.09.2026” — numărul e per petrecere (a câta sesiune a EI e asta), la fel ca la bar
     * (SalesGroup::title()), ca formatul să fie consecvent între cele două module.
     */
    public function title(): string
    {
        return 'Sesiune '.$this->session_number.' · '.$this->created_at?->format('d.m.Y');
    }

    /** Sesiunea deschisă a petrecerii, sau null. */
    public static function currentFor(int $partyId): ?self
    {
        return static::open()->where('party_id', $partyId)->orderBy('id')->first();
    }

    /** Ultima sesiune închisă a petrecerii (pentru mesajul „casa fusese închisă la …”), sau null. */
    public static function lastClosedFor(int $partyId): ?self
    {
        return static::query()->where('party_id', $partyId)->where('status', 'closed')
            ->orderByDesc('closed_at')->orderByDesc('id')->with('report')->first();
    }

    /**
     * Mesajul afișat când o înregistrare a deschis o sesiune nouă (apelat DUPĂ înregistrare): dacă petrecerea avea deja o
     * sesiune închisă, spune când s-a închis casa; altfel doar anunță că a început o sesiune.
     */
    public static function openedNotice(int $partyId): string
    {
        $last = static::lastClosedFor($partyId);
        if (! $last) {
            return 'A început o sesiune de recepție.';
        }

        return 'Casa fusese închisă la '.$last->closed_at?->format('d.m H:i')
            .($last->report ? ' (raportarea nr. '.$last->report->number().')' : '')
            .' — această înregistrare a început o sesiune nouă.';
    }

    /**
     * Sesiunea deschisă a petrecerii; dacă nu există, o creează (și loghează). Un singur rând deschis per petrecere.
     * Apelată DOAR de EntryRecorder / TokenLedger, în tranzacția înregistrării.
     */
    public static function openFor(int $partyId, ?int $adminId = null): self
    {
        return DB::transaction(function () use ($partyId, $adminId) {
            if ($existing = static::currentFor($partyId)) {
                return $existing;
            }

            $lastNumber = static::query()->where('party_id', $partyId)->max('session_number');

            $session = static::create([
                'party_id' => $partyId,
                'session_number' => ($lastNumber ?? 0) + 1,
                'status' => 'open',
                'opened_by' => $adminId,
            ]);

            ActivityLogger::log('reception.session_opened', sprintf(
                'A început o sesiune de recepție la „%s”.',
                Party::query()->whereKey($partyId)->value('name') ?? '—'
            ));

            return $session;
        });
    }

    /**
     * Motivul pentru care o înregistrare nu se mai poate anula: sesiunea ei a fost închisă (casa e predată). Null dacă
     * sesiunea e deschisă sau înregistrarea n-are sesiune. $what = „intrarea” / „vânzarea de tokeni”.
     *
     * @param  iterable<int|null>  $sessionIds
     */
    public static function closedBlockReason(iterable $sessionIds, string $what): ?string
    {
        $ids = collect($sessionIds)->filter()->unique()->values()->all();
        if ($ids === []) {
            return null;
        }

        $closed = static::query()->whereIn('id', $ids)->where('status', 'closed')->with('report')->first();
        if (! $closed) {
            return null;
        }

        return 'Casa a fost închisă'.($closed->report ? ' (raportarea nr. '.$closed->report->number().')' : '').', deci '.$what.' nu se mai poate anula.';
    }

    /** Închide sesiunea (apelată doar din ReceptionReport::finalize(), în aceeași tranzacție). */
    public function close(): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);
    }
}
