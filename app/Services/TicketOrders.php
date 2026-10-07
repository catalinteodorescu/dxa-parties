<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Ticket;
use App\Support\Phone;
use App\Support\Settings\Settings;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Aplicația participanților - runda 14). Cumpărarea de bilete din aplicație: prețuri, disponibilitate, comandă.
 *
 * Reguli:
 *  - Se vinde doar dacă petrecerea e publicată, activă, cu „Se vând bilete online” și neîncheiată.
 *  - Prețul unui bilet = prețul curent al tipului (minimul dintre baza și reducerile cu dată/oră încă valabile), coborât de treapta
 *    „primele N bilete” încă deschisă (numărate din biletele online neanulate ale tipului). Un cod de reducere se aplică per bilet,
 *    peste prețul astfel calculat, ca la Recepție (DiscountCodes); un bilet la care codul n-ar reduce nimic nu consumă utilizare.
 *  - Limite: bilete per comandă (gol = oricâte, dar cel mult SAFETY_MAX), bilete la vânzare în total și pe tip. Verificate din nou în
 *    tranzacție, cu rândul petrecerii blocat, ca două comenzi simultane să nu depășească stocul.
 *  - Primul bilet e pe numele cumpărătorului. La celelalte se poate da telefonul participantului: dacă are cont, biletul apare în
 *    contul lui (cu numele lui); altfel rămâne în contul cumpărătorului, fără nume (telefonul se păstrează).
 *  - Combo (runda 26): „3+1” = în fiecare set de 4 bilete de același tip, primele 3 se plătesc, al 4-lea e oferit (0 lei, bilet real cu
 *    stoc și QR, nu expiră, nu ocupă locuri din treptele „primele N”). Codul se aplică doar biletelor plătite. Nume/telefoane rămân opționale.
 *  - Fără plată online încă: comanda e „de plătit la intrare”. Nu există anulare din aplicație.
 */
class TicketOrders
{
    /** Plafon de siguranță când petrecerea n-are limită de bilete per comandă. */
    public const SAFETY_MAX = 100;

    /** Poate petrecerea vinde bilete online acum? Motivul refuzului, sau null dacă da. */
    public static function saleBlockReason(Party $party): ?string
    {
        if ($party->isDraft() || ! $party->is_active) {
            return 'Petrecerea nu este disponibilă.';
        }
        if (! $party->online_sales) {
            return 'Biletele acestei petreceri se cumpără la intrare.';
        }
        if ($party->state() === 'past') {
            return 'Petrecerea s-a încheiat.';
        }

        return null;
    }

    /** @return array{name: string, type: array}|null */
    public static function ticketType(Party $party, string $ticketName): ?array
    {
        foreach ($party->entryTicketTypes() as $t) {
            if ($t['name'] === $ticketName) {
                return $t;
            }
        }

        return null;
    }

    public static function soldCount(Party $party, string $ticketName): int
    {
        return (int) Ticket::query()->counted()->where('party_id', $party->id)->where('ticket_type', $ticketName)->count();
    }

    /**
     * DXA: adaugat (runda 25). Câte bilete au „consumat” locuri din treptele „primele N”: doar cele cu preț de listă diferit de 0
     * (cele plătite, chiar dacă un cod le-a redus apoi la 0). Biletele gratuite (ex. „gratis până la 22:00”) nu ocupă locuri din
     * treaptă. Regula se aplică DOAR biletelor vândute după introducerea ei: cele cu id până la marcajul `tickets_tier_marker`
     * (setat o singură dată, la prima folosire, = ultimul id de bilet de atunci) se numără ca înainte, toate.
     * Stocul și limitele (soldCount) rămân neschimbate: biletele gratuite scad din locurile disponibile.
     */
    public static function tierSoldCount(Party $party, string $ticketName): int
    {
        $marker = Settings::get('tickets_tier_marker');
        if ($marker === null) {
            $marker = (int) (Ticket::query()->max('id') ?? 0);
            Settings::set('tickets_tier_marker', $marker);
        }

        return (int) Ticket::query()->counted()->where('party_id', $party->id)->where('ticket_type', $ticketName)
            ->where('combo_free', false)   // runda 26: biletul oferit într-un combo nu ocupă locuri din treaptă
            ->where(fn ($q) => $q->where('id', '<=', (int) $marker)->orWhere('list_price', '>', 0))
            ->count();
    }

    public static function soldTotal(Party $party): int
    {
        return (int) Ticket::query()->counted()->where('party_id', $party->id)->count();
    }

    /** Biletele încă disponibile pentru acest tip (min dintre limita tipului și cea totală); null = nelimitat. */
    public static function available(Party $party, string $ticketName): ?int
    {
        $left = [];
        if ($party->tickets_for_sale !== null) {
            $left[] = max(0, (int) $party->tickets_for_sale - self::soldTotal($party));
        }
        $type = self::ticketType($party, $ticketName)['type'] ?? [];
        if (isset($type['limit']) && is_numeric($type['limit'])) {
            $left[] = max(0, (int) $type['limit'] - self::soldCount($party, $ticketName));
        }

        return $left ? min($left) : null;
    }

    /** Câte bilete de acest tip se pot cumpăra într-o comandă acum. */
    public static function maxPerOrder(Party $party, string $ticketName): int
    {
        $caps = [self::SAFETY_MAX];
        if ($party->max_tickets_per_order) {
            $caps[] = (int) $party->max_tickets_per_order;
        }
        $available = self::available($party, $ticketName);
        if ($available !== null) {
            $caps[] = $available;
        }

        return max(0, min($caps));
    }

    /** Prețul de bază cerut de treapta „primele N” deschisă pentru biletul cu numărul $soldBefore+1; null dacă nu e nicio treaptă deschisă. */
    public static function tierPrice(array $type, int $soldBefore): ?float
    {
        $tiers = collect($type['qty_tiers'] ?? [])
            ->filter(fn ($q) => isset($q['price'], $q['first']) && is_numeric($q['price']) && is_numeric($q['first']))
            ->sortBy(fn ($q) => (int) $q['first']);

        foreach ($tiers as $q) {
            if ($soldBefore < (int) $q['first']) {
                return (float) $q['price'];
            }
        }

        return null;
    }

    /**
     * DXA: adaugat (runda 27). Mențiune pentru un combo când treapta „primele N” e activă și ieftinește prețul: câte bilete mai sunt la
     * prețul special și, dacă nu ajung pentru toate biletele plătite din set, că restul se plătesc la prețul curent. Null = nimic de spus.
     */
    public static function comboNote(Party $party, array $type, string $ticketName, array $combo, ?Carbon $at = null): ?string
    {
        $sold = self::tierSoldCount($party, $ticketName);
        $current = (float) ($party->priceForTypeAt($type, $at) ?? 0);
        $open = collect($type['qty_tiers'] ?? [])
            ->filter(fn ($q) => isset($q['price'], $q['first']) && is_numeric($q['price']) && is_numeric($q['first']))
            ->sortBy(fn ($q) => (int) $q['first'])
            ->first(fn ($q) => $sold < (int) $q['first']);
        if ($open === null || (float) $open['price'] >= $current - 0.005) {
            return null;
        }

        $left = (int) $open['first'] - $sold;
        $price = rtrim(rtrim(number_format((float) $open['price'], 2, ',', ''), '0'), ',');
        $text = 'Preț special '.$price.' lei: '.($left === 1 ? 'a mai rămas 1 bilet' : 'au mai rămas '.$left.' bilete').' la acest preț.';
        if ($left < $combo['buy']) {
            $text .= ' Dacă sunt mai puține decât cele '.$combo['buy'].' plătite, restul se plătesc la prețul curent.';
        }

        return $text;
    }

    /** Prețul de listă al biletului cu numărul $soldBefore+1 (fără cod): prețul curent, coborât de treapta deschisă. */
    public static function unitPrice(Party $party, array $type, int $soldBefore, ?Carbon $at = null): float
    {
        $current = (float) ($party->priceForTypeAt($type, $at) ?? 0);
        $tier = self::tierPrice($type, $soldBefore);

        return round($tier !== null ? min($current, $tier) : $current, 2);
    }

    /** Termenul „intri până la" al biletului cu numărul $soldBefore+1 (null = nu expiră): al rândului de reducere care dă prețul, nu al treptei. */
    public static function unitValidUntil(Party $party, array $type, int $soldBefore, ?Carbon $at = null): ?string
    {
        $row = $party->priceRowAt($type, $at);
        $tier = self::tierPrice($type, $soldBefore);
        if ($row === null || ($tier !== null && $tier < $row['price'] - 0.005)) {
            return null;
        }

        return $row['enter_until'];
    }

    /**
     * DXA: adaugat (runda 26). Combo-urile unui tip de bilet („3+1”: plătești 3, primești 4). Fiecare = buy (plătite) + free (oferite),
     * toate de același tip. Cheia = „3+1”. Intrările invalide sau duplicate se ignoră.
     *
     * @return array<string, array{key: string, buy: int, free: int, size: int}>
     */
    public static function combos(array $type): array
    {
        $out = [];
        foreach ($type['combos'] ?? [] as $c) {
            $buy = (int) ($c['buy'] ?? 0);
            $free = (int) ($c['free'] ?? 0);
            if ($buy < 1 || $free < 1) {
                continue;
            }
            $key = $buy.'+'.$free;
            $out[$key] ??= ['key' => $key, 'buy' => $buy, 'free' => $free, 'size' => $buy + $free];
        }

        return $out;
    }

    /**
     * Calculează o comandă fără să scrie nimic (folosit și de previzualizarea din aplicație).
     *
     * @param  array<int, int>  $holderIds  participanții cu nume din comandă (pentru limita per participant a codului)
     * @return object{lines: array<int, array{list: float, discount: float, price: float, valid_until: ?string, free: bool, combo: ?string}>, subtotal: float, discount: float, total: float, code: ?PartyDiscountCode}
     *
     * @throws DomainException
     */
    public static function quote(Party $party, string $ticketName, int $count, ?string $codeInput = null, array $holderIds = [], ?Carbon $at = null, ?string $combo = null): object
    {
        $at ??= now();

        if ($reason = self::saleBlockReason($party)) {
            throw new DomainException($reason);
        }
        $ticket = self::ticketType($party, $ticketName) ?? throw new DomainException('Tipul de bilet ales nu mai există.');

        if ($count < 1) {
            throw new DomainException('Alege cel puțin un bilet.');
        }
        $comboDef = null;
        if ($combo !== null && $combo !== '') {
            $comboDef = self::combos($ticket['type'])[$combo] ?? throw new DomainException('Combo-ul ales nu mai este disponibil.');
            if ($count % $comboDef['size'] !== 0) {
                throw new DomainException('Combo-ul '.$combo.' se cumpără în seturi de '.$comboDef['size'].' bilete.');
            }
        }
        $available = self::available($party, $ticketName);
        if ($available !== null && $available <= 0) {
            throw new DomainException('Biletele „'.$ticketName.'” au fost epuizate.');
        }
        if ($party->max_tickets_per_order && $count > $party->max_tickets_per_order) {
            throw new DomainException('Poți cumpăra cel mult '.$party->max_tickets_per_order.' bilete într-o comandă.');
        }
        if ($count > self::SAFETY_MAX) {
            throw new DomainException('Poți cumpăra cel mult '.self::SAFETY_MAX.' bilete într-o comandă.');
        }
        if ($available !== null && $count > $available) {
            throw new DomainException('Mai sunt doar '.$available.' '.($available === 1 ? 'bilet disponibil' : 'bilete disponibile').'.');
        }

        // Treptele „primele N” se numără doar pe biletele cu preț ≠ 0: un bilet gratuit din comandă nu avansează numărătoarea.
        $paid = self::tierSoldCount($party, $ticketName);
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            // Într-un set de combo, ultimele `free` bilete sunt oferite: 0 lei, fără termen, nu avansează treptele.
            if ($comboDef && ($i % $comboDef['size']) >= $comboDef['buy']) {
                $lines[] = ['list' => 0.0, 'discount' => 0.0, 'price' => 0.0, 'valid_until' => null, 'free' => true, 'combo' => $combo];

                continue;
            }
            $list = self::unitPrice($party, $ticket['type'], $paid, $at);
            $lines[] = ['list' => $list, 'discount' => 0.0, 'price' => $list, 'valid_until' => self::unitValidUntil($party, $ticket['type'], $paid, $at), 'free' => false, 'combo' => $comboDef ? $combo : null];
            if ($list > 0) {
                $paid++;
            }
        }

        $code = null;
        $codeApplied = 0;
        $codeInput = trim((string) $codeInput);
        if ($codeInput !== '') {
            $found = DiscountCodes::find($party, $codeInput)
                ?? throw new DomainException('Codul de reducere nu există la această petrecere.');
            DiscountCodes::assertUsable($found, $ticketName, $at);

            $discounted = 0;
            $lastError = null;
            foreach ($lines as $i => $line) {
                if ($line['free']) {
                    continue; // biletul oferit în combo nu primește cod
                }
                try {
                    $applied = DiscountCodes::apply($party, $ticketName, $line['list'], $codeInput, [], $at, 1);
                } catch (DomainException $e) {
                    $lastError = $e; // biletul acesta nu primește reducere (ex. deja gratuit): nu consumă o utilizare

                    continue;
                }
                $lines[$i] = ['list' => $line['list'], 'discount' => $applied->discount, 'price' => $applied->price, 'valid_until' => $line['valid_until'], 'free' => false, 'combo' => $line['combo']];
                $discounted++;
            }

            if ($discounted === 0) {
                throw $lastError ?? new DomainException('Codul nu aduce nicio reducere.');
            }

            // Limita totală a codului (runda 28): dacă mai sunt utilizări doar pentru unele bilete, reducerea se dă primelor, restul se plătesc întreg.
            if ($found->max_uses !== null) {
                $left = $found->max_uses - $found->usesCount();
                if ($left <= 0) {
                    throw new DomainException('Codul de reducere a fost folosit de numărul maxim de ori.');
                }
                for ($i = count($lines) - 1; $i >= 0 && $discounted > $left; $i--) {
                    if ($lines[$i]['discount'] > 0) {
                        $lines[$i]['discount'] = 0.0;
                        $lines[$i]['price'] = $lines[$i]['list'];
                        $discounted--;
                    }
                }
            }

            DiscountCodes::assertLimits($found, array_values(array_unique($holderIds)), $discounted);
            $code = $found;
            $codeApplied = $discounted;
        }

        $subtotal = round(array_sum(array_column($lines, 'list')), 2);
        $total = round(array_sum(array_column($lines, 'price')), 2);

        return (object) ['lines' => $lines, 'subtotal' => $subtotal, 'discount' => round($subtotal - $total, 2), 'total' => $total, 'code' => $code, 'code_applied' => $codeApplied];
    }

    /**
     * Plasează comanda. `$phones` = telefoanele participanților pentru biletele 2..N (chei/ordine ignorate: se citesc pe rând;
     * intrările goale = bilet fără nume). Totul într-o tranzacție, cu petrecerea (și codul) blocate.
     *
     * @param  array<int, ?string>  $phones
     *
     * @throws DomainException cu motivul în română
     */
    public static function place(Participant $buyer, Party $party, string $ticketName, int $count, array $phones = [], ?string $codeInput = null, ?Carbon $at = null, ?string $combo = null): Order
    {
        if (! $buyer->hasAccount()) {
            throw new DomainException('Ai nevoie de un cont activ ca să cumperi bilete.');
        }

        $others = self::resolveOthers($buyer, $count, $phones);

        return DB::transaction(function () use ($buyer, $party, $ticketName, $count, $others, $codeInput, $at, $combo) {
            // Blochează petrecerea (stocul) și codul (utilizările) înainte de a recalcula.
            $party = Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();
            $code = $codeInput !== null && trim($codeInput) !== '' ? DiscountCodes::find($party, $codeInput) : null;
            if ($code) {
                PartyDiscountCode::query()->whereKey($code->id)->lockForUpdate()->first();
            }

            $holderIds = array_values(array_filter(array_merge([$buyer->id], array_column($others, 'holder_id'))));
            $quote = self::quote($party, $ticketName, $count, $codeInput, $holderIds, $at, $combo);

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'participant_id' => $buyer->id,
                'party_id' => $party->id,
                'tickets_count' => $count,
                'subtotal' => $quote->subtotal,
                'discount_total' => $quote->discount,
                'total' => $quote->total,
                'discount_code_id' => $quote->code?->id,
                'payment_status' => config('app.dxa_tickets_auto_paid') ? Order::PAY_PAID : Order::PAY_AT_ENTRY,
            ]);

            foreach ($quote->lines as $i => $line) {
                $other = $i === 0 ? null : $others[$i - 1];

                Ticket::create([
                    'order_id' => $order->id,
                    'party_id' => $party->id,
                    'ticket_type' => $ticketName,
                    'owner_participant_id' => $i === 0 ? $buyer->id : ($other['holder_id'] ?? $buyer->id),
                    'holder_participant_id' => $i === 0 ? $buyer->id : ($other['holder_id'] ?? null),
                    'holder_phone' => $other['phone'] ?? null,
                    'list_price' => $line['list'],
                    'discount_amount' => $line['discount'],
                    'price' => $line['price'],
                    'discount_code_id' => $line['discount'] > 0 ? $quote->code?->id : null,
                    'valid_until' => Party::enterUntilAt($line['valid_until']),
                    'combo_label' => $line['combo'],
                    'combo_free' => $line['free'],
                    'status' => Ticket::VALID,
                ]);
            }

            ActivityLogger::log('tickets.ordered', sprintf(
                '%s a cumpărat %d %s „%s” la „%s” (total %s lei%s%s).',
                $buyer->name, $count, $count === 1 ? 'bilet' : 'bilete', $ticketName, $party->name,
                number_format($quote->total, 2, ',', '.'), $quote->code ? ', cod '.$quote->code->code : '', $combo ? ', combo '.$combo : ''
            ), actor: null);

            return $order->load('tickets');
        });
    }

    /**
     * Telefoanele biletelor 2..N → [['phone' => ?normalizat, 'holder_id' => ?int], ...] (câte $count - 1).
     *
     * @param  array<int, ?string>  $phones
     * @return array<int, array{phone: ?string, holder_id: ?int}>
     */
    public static function resolveOthers(Participant $buyer, int $count, array $phones): array
    {
        $phones = array_values($phones);
        $out = [];
        $seen = [];

        for ($i = 0; $i < max(0, $count - 1); $i++) {
            $raw = trim((string) ($phones[$i] ?? ''));
            if ($raw === '') {
                $out[] = ['phone' => null, 'holder_id' => null];

                continue;
            }

            $phone = Phone::normalize($raw) ?? throw new DomainException('Telefonul „'.$raw.'” nu este valid.');
            if ($phone === $buyer->phone) {
                throw new DomainException('Primul bilet este deja pe numele tău. Folosește alt telefon pentru celelalte bilete.');
            }
            if (isset($seen[$phone])) {
                throw new DomainException('Telefonul '.$raw.' apare de două ori în comandă.');
            }
            $seen[$phone] = true;

            $holder = Participant::query()->where('phone', $phone)->first();
            $out[] = ['phone' => $phone, 'holder_id' => $holder && $holder->hasAccount() ? $holder->id : null];
        }

        return $out;
    }
}
