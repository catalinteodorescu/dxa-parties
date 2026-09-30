<?php

namespace App\Services;

use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (Coduri de reducere). Singurul loc unde un cod de reducere devine preț. Folosit de EntryRecorder
 * (deci de orice canal care înregistrează intrări: acum admin/recepție API, mai târziu aplicația participanților).
 *
 * Reguli:
 *  - codul se caută pe petrecere, fără diferență între litere mari/mici și fără spații;
 *  - se aplică peste PREȚUL CURENT (cu reducerile automate și toleranța deja luate în calcul);
 *  - percent/amount: scad din prețul curent, minim 0; tier: prețul treptei cu eticheta codului (indiferent de dată),
 *    niciodată mai mare decât prețul curent;
 *  - un cod care n-ar da nicio reducere (bilet deja gratuit / deja mai ieftin) se respinge, ca să nu consume o utilizare;
 *  - o utilizare = un BILET (o reducere acordată): o comandă de 3 bilete consumă 3 utilizări. Limita totală numără bilete
 *    (comanda se respinge dacă nu mai sunt destule utilizări pentru toate biletele ei); limita per participant numără
 *    biletele fiecărui participant identificat (un participant = un bilet; intrările anonime: doar limita totală);
 *  - anularea unei intrări eliberează utilizarea (se numără doar intrări valabile).
 */
class DiscountCodes
{
    /** „ ab-12 " -> „AB-12". */
    public static function normalize(?string $code): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', (string) $code));
    }

    public static function find(Party $party, ?string $code): ?PartyDiscountCode
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }

        return PartyDiscountCode::query()->where('party_id', $party->id)->where('code', $code)->first();
    }

    /**
     * Aplică un cod unui preț curent. Nu scrie nimic în DB.
     *
     * @param  float  $currentPrice  prețul curent per persoană (cu toleranță)
     * @param  array<int, int>  $participantIds  participanții identificați din comandă (pentru limita per participant)
     * @param  int  $count  câte bilete (persoane) are comanda: fiecare consumă o utilizare
     * @return object{code: PartyDiscountCode, before: float, price: float, discount: float}
     *
     * @throws DomainException cu motivul în română
     */
    public static function apply(Party $party, string $ticketName, float $currentPrice, ?string $input, array $participantIds = [], ?Carbon $at = null, int $count = 1): object
    {
        $code = self::find($party, $input)
            ?? throw new DomainException('Codul de reducere nu există la această petrecere.');

        $at ??= now();
        self::assertUsable($code, $ticketName, $at);
        self::assertLimits($code, $participantIds, $count);

        $before = round($currentPrice, 2);
        $price = match ($code->type) {
            'percent' => round($before * (1 - min(100.0, (float) $code->value) / 100), 2),
            'amount' => round(max(0.0, $before - (float) $code->value), 2),
            'tier' => self::tierPrice($party, $ticketName, $code, $before),
            default => throw new DomainException('Cod de reducere invalid.'),
        };
        $price = max(0.0, min($before, $price));

        if ($before - $price < 0.005) {
            throw new DomainException($before <= 0.0
                ? 'Biletul este deja gratuit: codul nu aduce nicio reducere.'
                : 'Codul nu aduce nicio reducere la prețul curent.');
        }

        return (object) ['code' => $code, 'before' => $before, 'price' => $price, 'discount' => round($before - $price, 2)];
    }

    /** Activ, în perioada de valabilitate, valabil pe tipul de bilet ales. Aruncă DomainException. */
    public static function assertUsable(PartyDiscountCode $code, string $ticketName, Carbon $at): void
    {
        if (! $code->is_active) {
            throw new DomainException('Codul de reducere nu mai este activ.');
        }
        if ($code->valid_from && $at->lessThan($code->valid_from)) {
            throw new DomainException('Codul de reducere nu este încă valabil (de la '.$code->valid_from->format('d.m.Y H:i').').');
        }
        // „Până la" e exclusiv, ca la reducerile de preț: la ora exactă codul nu mai e valabil.
        if ($code->valid_until && $at->greaterThanOrEqualTo($code->valid_until)) {
            throw new DomainException('Codul de reducere a expirat.');
        }

        $only = array_values(array_filter((array) $code->ticket_types, fn ($n) => is_string($n) && $n !== ''));
        if ($only !== [] && ! in_array($ticketName, $only, true)) {
            throw new DomainException('Codul nu se aplică la biletul „'.$ticketName.'”.');
        }
    }

    /**
     * Limita totală și cea per participant. Se apelează și în tranzacția de înregistrare, pe rândul codului blocat
     * (lockForUpdate), ca două comenzi simultane să nu depășească limita.
     *
     * @param  array<int, int>  $participantIds
     */
    public static function assertLimits(PartyDiscountCode $code, array $participantIds = [], int $count = 1): void
    {
        if ($code->max_uses !== null) {
            $left = $code->max_uses - $code->usesCount();
            if ($left <= 0) {
                throw new DomainException('Codul de reducere a fost folosit de numărul maxim de ori.');
            }
            if ($left < $count) {
                throw new DomainException('Codul mai poate fi folosit doar pentru '.$left.' '.($left === 1 ? 'bilet' : 'bilete').', iar comanda are '.$count.'.');
            }
        }

        if ($code->max_uses_per_participant !== null) {
            foreach ($participantIds as $pid) {
                if ($code->usesBy((int) $pid) >= $code->max_uses_per_participant) {
                    throw new DomainException('Ai folosit deja acest cod de reducere.');
                }
            }
        }
    }

    /** Prețul treptei cu eticheta codului la acest bilet; min cu prețul curent. */
    private static function tierPrice(Party $party, string $ticketName, PartyDiscountCode $code, float $current): float
    {
        $wanted = mb_strtolower(trim((string) $code->tier_label));

        foreach ($party->entryTicketTypes() as $t) {
            if ($t['name'] !== $ticketName) {
                continue;
            }
            foreach ($t['type']['discounts'] ?? $t['type']['tiers'] ?? [] as $d) {
                if (mb_strtolower(trim((string) ($d['label'] ?? ''))) === $wanted && isset($d['price']) && is_numeric($d['price'])) {
                    return min($current, round((float) $d['price'], 2));
                }
            }
        }

        throw new DomainException('Codul nu se aplică la biletul „'.$ticketName.'” (nu are treapta „'.$code->tier_label.'”).');
    }

    /**
     * Sumarul reducerilor acordate la o petrecere, pe cod (pentru Bilanțul serii): doar intrări valabile.
     *
     * @return Collection<int, object{code: string, promoter: ?string, tickets: int, amount: float}>
     */
    public static function grantedFor(Party $party): Collection
    {
        $rows = PartyEntry::query()->where('party_id', $party->id)->active()->whereNotNull('discount_code_id')
            ->get(['discount_code_id', 'discount_amount'])
            ->groupBy('discount_code_id');

        $codes = PartyDiscountCode::query()->with('promoter')->whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(fn ($g, $id) => (object) [
            'code' => $codes->get($id)?->code ?? '#'.$id,
            'promoter' => $codes->get($id)?->promoter?->name,
            'tickets' => $g->count(),
            'amount' => round((float) $g->sum('discount_amount'), 2),
        ])->sortByDesc('amount')->values();
    }
}
