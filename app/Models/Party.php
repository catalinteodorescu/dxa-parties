<?php

namespace App\Models;

use App\Services\ContentStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Party extends Model
{
    /** Stiluri pentru invitati (la 'other' se completeaza `style_other`). */
    public const GUEST_STYLES = [
        'bachata' => 'Bachata',
        'salsa' => 'Salsa',
        'kizomba' => 'Kizomba',
        'dj' => 'DJ',
        'guest_dancer' => 'Guest dancer',
        'mc' => 'MC',
        'other' => 'Altul',
    ];

    /** Tipuri de item din programul unei zile. */
    public const PROGRAM_TYPES = [
        'workshop' => 'Workshop',
        'social' => 'Social',
        'show' => 'Show',
        'other' => 'Altul',
    ];

    protected $fillable = [
        'name',
        'image_path',
        'kind',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location_name',
        'location_address',
        'location_url',
        'dresscode',
        'is_free',
        'ticket_types',
        'contacts',
        'payment_methods',
        'loyalty_eligible',
        'online_sales',
        'max_tickets_per_order',
        'tickets_for_sale',
        'max_participants',
        'audience',
        'in_carousel',
        'is_active',
        'status',
        'days',
        'guests',
        'music_styles',
        'links',
        'custom_fields',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_free' => 'boolean',
            'loyalty_eligible' => 'boolean',
            'online_sales' => 'boolean',
            'max_tickets_per_order' => 'integer',
            'tickets_for_sale' => 'integer',
            'max_participants' => 'integer',
            'in_carousel' => 'boolean',
            'is_active' => 'boolean',
            'ticket_types' => 'array',
            'contacts' => 'array',
            'days' => 'array',
            'guests' => 'array',
            'music_styles' => 'array',
            'payment_methods' => 'array',
            'links' => 'array',
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Party $party) {
            [$party->starts_at, $party->ends_at] = $party->resolveInterval();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** DXA: adaugat — inversul lui StockReport::party() (belongsTo). Nu era folosita pana acum, dar ne trebuie explicit acum. */
    public function stockReports(): HasMany
    {
        return $this->hasMany(StockReport::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFestival(): bool
    {
        return $this->kind === 'festival';
    }

    public function state(): string
    {
        if ($this->status === 'draft') {
            return 'draft';
        }

        if (! $this->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return 'upcoming';
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return 'past';
        }

        return 'live';
    }

    /**
     * Este reducerea inca valabila la $now? `until` poate fi:
     *  - data si ora (Y-m-d\TH:i): „pana la 22:30" = EXCLUSIV - la 22:30:00 reducerea
     *    nu mai e valabila (ultima secunda valabila e 22:29:59);
     *  - doar data (Y-m-d, vechiul format): valabila pe tot parcursul zilei respective;
     *  - gol: fara limita.
     */
    public static function discountActive(?string $until, ?Carbon $now = null): bool
    {
        if ($until === null || trim($until) === '') {
            return true;
        }

        $until = trim($until);
        $now ??= now();

        return mb_strlen($until) <= 10
            ? $now->lessThanOrEqualTo(Carbon::parse($until)->endOfDay())
            : $now->lessThan(Carbon::parse($until));
    }

    /** Text pentru afisare: „24.09.2026" (doar data) sau „24.09.2026 22:30" (cu ora). */
    public static function formatUntil(?string $until): string
    {
        if ($until === null || trim($until) === '') {
            return '';
        }

        return Carbon::parse($until)->format(mb_strlen(trim($until)) <= 10 ? 'd.m.Y' : 'd.m.Y H:i');
    }

    /**
     * Pretul curent al unui singur tip de bilet: minimul dintre pretul de baza si
     * reducerile inca valabile la now() (ex. early-bird sau „gratuit pana la 22:30").
     * null daca tipul n-are pret. Citeste `discounts` (cum le salveaza formularul),
     * cu fallback pe vechea cheie `tiers`.
     */
    public function currentPriceForType(array $type): ?float
    {
        return $this->priceForTypeAt($type, now());
    }

    /**
     * Pretul unui tip de bilet la momentul $at, cu o toleranta optionala (DXA: Recepție).
     *
     * Toleranta ($graceMinutes) se aplica DOAR reducerilor cu limita de ora („gratuit pana la 22:30"): reducerea
     * ramane valabila inca $graceMinutes minute dupa limita. Reducerile cu limita doar de zi si pretul de baza
     * nu sunt afectate. Cu $graceMinutes = 0 se comporta exact ca inainte.
     */
    public function priceForTypeAt(array $type, ?Carbon $at = null, int $graceMinutes = 0): ?float
    {
        return $this->priceRowAt($type, $at, $graceMinutes)['price'] ?? null;
    }

    /**
     * DXA: adaugat (runda 15). Rândul de preț câștigător la momentul $at: prețul cel mai mic dintre baza și reducerile valabile.
     * O reducere are două limite, independente și opționale: `until` (cumperi până la) și `enter_until` (intri până la).
     * Ambele trebuie să fie încă valabile ca reducerea să se aplice (la cumpărare și la intrare). La preț egal câștigă cea fără
     * limită de intrare, apoi cea cu limita de intrare mai târzie. `enter_until` e limita pusă pe bilet (null = biletul nu expiră).
     *
     * @return array{price: float, enter_until: ?string}|null
     */
    public function priceRowAt(array $type, ?Carbon $at = null, int $graceMinutes = 0): ?array
    {
        $at ??= now();
        $candidates = [];

        if (isset($type['price']) && is_numeric($type['price'])) {
            $candidates[] = ['price' => (float) $type['price'], 'enter_until' => null];
        }

        foreach ($type['discounts'] ?? $type['tiers'] ?? [] as $t) {
            if (! isset($t['price']) || ! is_numeric($t['price'])) {
                continue;
            }

            $active = true;
            foreach (['until', 'enter_until'] as $key) {
                $limit = $t[$key] ?? null;
                $check = $at;
                if ($graceMinutes > 0 && $limit !== null && mb_strlen(trim((string) $limit)) > 10) {
                    $check = $at->copy()->subMinutes($graceMinutes);
                }
                if (! static::discountActive($limit, $check)) {
                    $active = false;
                }
            }
            if (! $active) {
                continue;
            }

            $enter = trim((string) ($t['enter_until'] ?? ''));
            $candidates[] = ['price' => (float) $t['price'], 'enter_until' => $enter !== '' ? $enter : null];
        }

        if (! $candidates) {
            return null;
        }

        usort($candidates, function ($x, $y) {
            if ($x['price'] !== $y['price']) {
                return $x['price'] <=> $y['price'];
            }
            // La preț egal: fără limită de intrare întâi, apoi limita cea mai târzie.
            if ($x['enter_until'] === null || $y['enter_until'] === null) {
                return ($x['enter_until'] === null ? 0 : 1) <=> ($y['enter_until'] === null ? 0 : 1);
            }

            return strcmp((string) $y['enter_until'], (string) $x['enter_until']);
        });

        return $candidates[0];
    }

    /** Data și ora „intri până la" ca Carbon (o limită doar cu zi = sfârșitul zilei); null dacă nu e limită. */
    public static function enterUntilAt(?string $enterUntil): ?Carbon
    {
        $enterUntil = trim((string) $enterUntil);
        if ($enterUntil === '') {
            return null;
        }

        return mb_strlen($enterUntil) <= 10 ? Carbon::parse($enterUntil)->endOfDay() : Carbon::parse($enterUntil);
    }

    /**
     * Tipurile de bilet pentru Recepție: [['name' => ..., 'type' => <tip original>], ...]. O petrecere gratuita are
     * un singur tip („Intrare gratuită", 0 lei); tipurile fara pret sunt ignorate; formatul vechi (`price`
     * pe petrecere, fara tipuri) devine un singur bilet.
     *
     * @return array<int, array{name: string, type: array}>
     */
    public function entryTicketTypes(): array
    {
        if ($this->is_free) {
            return [['name' => 'Intrare gratuită', 'type' => ['name' => 'Intrare gratuită', 'price' => 0]]];
        }

        $types = $this->ticket_types ?? [];
        if ($types === [] && $this->price !== null) {
            $types = [['name' => '', 'price' => $this->price]];
        }

        $out = [];
        foreach ($types as $type) {
            if ($this->priceForTypeAt($type) === null) {
                continue;
            }
            $name = trim((string) ($type['name'] ?? ''));
            $out[] = ['name' => $name !== '' ? $name : 'Bilet', 'type' => $type];
        }

        return $out;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartyEntry::class);
    }

    /** DXA: adaugat (Coduri de reducere): codurile petrecerii (câte unul pe promotor etc.). */
    /** DXA: adaugat (runda 40). Participanții care au salvat petrecerea (inima). */
    public function interests(): HasMany
    {
        return $this->hasMany(PartyInterest::class);
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(PartyDiscountCode::class)->orderBy('id');
    }

    /** DXA: adaugat (Recepție - sesiuni): sesiunile de recepție ale petrecerii (una deschisă cel mult). */
    public function receptionSessions(): HasMany
    {
        return $this->hasMany(ReceptionSession::class);
    }

    /** DXA: adaugat (Recepție - tokeni): vânzările de tokeni făcute la această petrecere. */
    public function tokenTransactions(): HasMany
    {
        return $this->hasMany(TokenTransaction::class);
    }

    /**
     * Cel mai mic pret curent dintre toate tipurile de bilet (pentru „de la … lei”).
     * 0 daca e gratuit, null daca nu s-a setat niciun pret.
     */
    public function currentPrice(): ?float
    {
        if ($this->is_free) {
            return 0.0;
        }

        $prices = [];
        foreach ($this->ticket_types ?? [] as $type) {
            $p = $this->currentPriceForType($type);
            if ($p !== null) {
                $prices[] = $p;
            }
        }

        return $prices ? min($prices) : null;
    }

    /** Are mai mult de un tip de bilet cu pret? (pentru afisarea „de la”). */
    public function hasMultipleTicketTypes(): bool
    {
        return collect($this->ticket_types ?? [])
            ->filter(fn ($t) => $this->currentPriceForType($t) !== null)
            ->count() > 1;
    }

    /** DXA: runda 40 — contoare reale (vezi App\Services\ContentStats). Afișări = card vizibil în aplicație. */
    public function statViews(): int
    {
        return ContentStats::totals('party', $this->id)['impression'];
    }

    /** Deschideri ale paginii. */
    public function statOpens(): int
    {
        return ContentStats::totals('party', $this->id)['open'];
    }

    /** Click-uri pe acțiune („Cumpără bilete”). */
    public function statActions(): int
    {
        return ContentStats::totals('party', $this->id)['action'];
    }

    public static function dayInterval(string $date, ?string $startTime, ?string $endTime): array
    {
        $start = Carbon::parse($date.' '.($startTime ?: '00:00'));

        $end = null;
        if ($endTime) {
            $end = Carbon::parse($date.' '.$endTime);
            if ($startTime && $end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
        }

        return [$start, $end];
    }

    public function resolveInterval(): array
    {
        if ($this->isFestival() && ! empty($this->days)) {
            $start = null;
            $end = null;

            foreach ($this->days as $day) {
                if (empty($day['date'])) {
                    continue;
                }

                [$s, $e] = self::dayInterval($day['date'], $day['start_time'] ?? null, $day['end_time'] ?? null);

                if (! $start || $s->lessThan($start)) {
                    $start = $s;
                }
                if ($e && (! $end || $e->greaterThan($end))) {
                    $end = $e;
                }
            }

            if ($start) {
                return [$start, $end];
            }
        }

        if (! $this->start_date) {
            return [$this->starts_at, $this->ends_at];
        }

        $date = $this->start_date instanceof Carbon
            ? $this->start_date->format('Y-m-d')
            : (string) $this->start_date;

        return self::dayInterval($date, $this->start_time, $this->end_time);
    }

    /**
     * DXA: adaugat (Recepție). Petrecerile la care se pot înregistra operațiuni la recepție: publicate, active,
     * neîncheiate, cea mai devreme începută prima (deci cea în desfășurare). Folosit de admin și de PWA Recepție.
     */
    public function scopeForReception(Builder $query): Builder
    {
        // Ca la bar (scopeForBar): după ora de sfârșit petrecerea rămâne selectabilă cât timp are o sesiune de recepție
        // deschisă (casa nu a fost predată prin raportare). Ciornele și petrecerile dezactivate rămân excluse.
        return $query
            ->where('status', '!=', 'draft')
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now())
                ->orWhereIn('id', ReceptionSession::query()->open()->select('party_id')))
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * DXA: adaugat (ora de sfârșit la Recepție). Poate primi petrecerea înregistrări la recepție (intrări, tokeni, credite)?
     * Viitoare / în desfășurare: da. Încheiată (după `ends_at`): doar cât există o sesiune de recepție deschisă, la fel ca
     * la bar; după închiderea casei nu mai, iar prima înregistrare după sfârșit (fără sesiune deschisă) e refuzată.
     * Ciornă / dezactivată: niciodată.
     */
    public function acceptsReceptionRecords(): bool
    {
        return match ($this->state()) {
            'upcoming', 'live' => true,
            'past' => ReceptionSession::currentFor($this->id) !== null,
            default => false,
        };
    }

    /** Petrecerea e încheiată și se lucrează doar pe sesiunea de recepție rămasă deschisă (nu se poate deschide una nouă). */
    public function receptionOnlyExistingSession(): bool
    {
        return $this->state() === 'past';
    }

    /**
     * DXA: adaugat (PWA Bar). Petrecerile la care poate lucra barmanul: ca la recepție (publicate, active, neîncheiate),
     * plus cele cu o sesiune de vânzări încă deschisă (chiar încheiate: seara trebuie predată prin raportare).
     */
    public function scopeForBar(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'draft')
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('is_active', true)
                    ->where(fn ($q3) => $q3->whereNull('ends_at')->orWhere('ends_at', '>=', now())))
                ->orWhereIn('id', SalesGroup::query()->open()->whereNotNull('party_id')->select('party_id')))
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function scopeVisible(Builder $query, string $audience = 'all'): Builder
    {
        $now = now();

        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->when($audience === 'all', fn ($q) => $q->where('audience', 'all'))
            ->orderBy('starts_at');
    }

    /**
     * DXA: adaugat (runda 16b). Petrecerile publicate și active care s-au încheiat (cele mai recente întâi), pentru „Petreceri trecute”.
     */
    public function scopeVisiblePast(Builder $query, string $audience = 'all'): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereNotNull('ends_at')->where('ends_at', '<', now())
            ->when($audience === 'all', fn ($q) => $q->where('audience', 'all'))
            ->orderByDesc('starts_at');
    }

    /**
     * DXA: adaugat (runda 13). Starea capacității: null dacă petrecerea n-are „număr maxim de participanți”. Doar informativă —
     * nu blochează nici intrarea, nici cumpărarea; Recepția afișează o atenționare când pragul e atins (intrările valabile, neanulate).
     *
     * @return object{max: int, count: int, reached: bool, over: bool}|null
     */
    public function capacityStatus(): ?object
    {
        if (! $this->max_participants) {
            return null;
        }

        $count = PartyEntry::query()->where('party_id', $this->id)->whereNull('cancelled_at')->count();

        return (object) [
            'max' => (int) $this->max_participants,
            'count' => $count,
            'reached' => $count >= $this->max_participants,
            'over' => $count > $this->max_participants,
        ];
    }
}
