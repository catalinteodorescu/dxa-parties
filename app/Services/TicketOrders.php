<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyDiscountCode;
use App\Models\Ticket;
use App\Support\PaymentMethods;
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
 *  - Fără plată online încă: comanda e „de plătit la intrare” (sau achitată, cu DXA_TICKETS_AUTO_PAID, ca simulare a plății cu cardul).
 *    Runda 50: plata cu credite — integrală, din portofelul cumpărătorului, doar dacă soldul acoperă totalul; debitul se face în aceeași
 *    tranzacție cu comanda (sold verificat pe rândul blocat). Nu există anulare din aplicație.
 */
class TicketOrders
{
    /** Plafon de siguranță când petrecerea n-are limită de bilete per comandă. */
    public const SAFETY_MAX = 100;

    /** DXA: adaugat (runda 65). Cât timp rămân rezervate biletele unei comenzi cu cardul (minute). Mai mult decât sesiunea Stripe (31), ca o plată de ultim moment să nu găsească locurile eliberate. */
    public const RESERVATION_MINUTES = 35;

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

    /**
     * DXA: adaugat (runda 50). Se pot plăti biletele cu credite la această petrecere? Motivul refuzului, sau null dacă da:
     * metoda „Credite” activă în Setări și acceptată la intrare de petrecere (PaymentMethods::forEntry).
     */
    public static function creditsBlockReason(Party $party): ?string
    {
        if (! PaymentMethods::isEnabled(PaymentMethods::CREDIT) || ! isset(PaymentMethods::forEntry($party)[PaymentMethods::CREDIT])) {
            return 'La această petrecere nu se poate plăti cu credite.';
        }

        return null;
    }

    /** Poate cumpărătorul plăti un total cu credite (metoda permisă, total > 0, sold suficient)? Folosit de aplicație ca să arate sau nu opțiunea. */
    public static function canPayWithCredits(Participant $buyer, Party $party, float $total): bool
    {
        return $total > 0 && self::creditsBlockReason($party) === null
            && PaymentRows::cents(CreditLedger::balance($buyer)) >= PaymentRows::cents($total);
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

    /** Motivul pentru care nu se pot cumpăra mai multe bilete într-o comandă (afișat lângă butonul „+”), pe baza celei mai mici limite. */
    public static function capReason(Party $party, string $ticketName): string
    {
        $perOrder = $party->max_tickets_per_order ? (int) $party->max_tickets_per_order : self::SAFETY_MAX;
        $perOrder = min($perOrder, self::SAFETY_MAX);
        $available = self::available($party, $ticketName);

        if ($available !== null && $available <= $perOrder) {
            return $available <= 0
                ? 'Biletele „'.$ticketName.'” au fost epuizate.'
                : 'Nu mai pot fi adăugate bilete: au mai rămas disponibile doar '.$available.' bilete „'.$ticketName.'”.';
        }

        return 'Ai atins numărul maxim de bilete pe o comandă ('.$perOrder.').';
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
     * Plasează comanda. Primul bilet e pe numele cumpărătorului, celelalte rămân libere în contul lui (runda 53). Totul într-o tranzacție, cu petrecerea (și codul) blocate.
     *
     *
     * @throws DomainException cu motivul în română
     */
    public static function place(Participant $buyer, Party $party, string $ticketName, int $count, ?string $codeInput = null, ?Carbon $at = null, ?string $combo = null, bool $withCredits = false, bool $withCard = false): Order
    {
        self::expireStalePending();

        if (! $buyer->hasAccount()) {
            throw new DomainException('Ai nevoie de un cont activ ca să cumperi bilete.');
        }

        return DB::transaction(function () use ($buyer, $party, $ticketName, $count, $codeInput, $at, $combo, $withCredits, $withCard) {
            // Blochează petrecerea (stocul) și codul (utilizările) înainte de a recalcula.
            $party = Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();
            $code = $codeInput !== null && trim($codeInput) !== '' ? DiscountCodes::find($party, $codeInput) : null;
            if ($code) {
                PartyDiscountCode::query()->whereKey($code->id)->lockForUpdate()->first();
            }

            $holderIds = [$buyer->id];
            $quote = self::quote($party, $ticketName, $count, $codeInput, $holderIds, $at, $combo);

            if ($withCredits) {
                if ($reason = self::creditsBlockReason($party)) {
                    throw new DomainException($reason);
                }
                if ($quote->total <= 0) {
                    throw new DomainException('Comanda e gratuită: nu e nimic de plătit cu credite.');
                }
                // Soldul se citește de pe rândul blocat al cumpărătorului: două comenzi simultane nu pot cheltui aceiași bani.
                $wallet = Participant::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();
                if (PaymentRows::cents(CreditLedger::balance($wallet)) < PaymentRows::cents($quote->total)) {
                    throw new DomainException('Soldul de credite ('.PaymentRows::money(CreditLedger::balance($wallet)).' lei) nu ajunge pentru '.PaymentRows::money($quote->total).' lei.');
                }
            }

            // Runda 65: cu cardul (doar dacă e ceva de plătit) biletele se rezervă `pending` până vine plata; comenzile gratuite merg ca până acum.
            $card = $withCard && ! $withCredits && $quote->total > 0;

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'participant_id' => $buyer->id,
                'party_id' => $party->id,
                'tickets_count' => $count,
                'subtotal' => $quote->subtotal,
                'discount_total' => $quote->discount,
                'total' => $quote->total,
                'discount_code_id' => $quote->code?->id,
                'payment_status' => $card ? Order::PAY_CARD_PENDING : ($withCredits ? Order::PAY_CREDITS : (config('app.dxa_tickets_auto_paid') ? Order::PAY_PAID : Order::PAY_AT_ENTRY)),
                'payment_expires_at' => $card ? now()->addMinutes(self::RESERVATION_MINUTES) : null,
                'payment_provider' => $card ? 'stripe' : null,
            ]);

            foreach ($quote->lines as $i => $line) {
                Ticket::create([
                    'order_id' => $order->id,
                    'party_id' => $party->id,
                    'ticket_type' => $ticketName,
                    'owner_participant_id' => $buyer->id,
                    'holder_participant_id' => $i === 0 ? $buyer->id : null,   // runda 53: primul bilet pe numele cumpărătorului, restul libere
                    'holder_phone' => null,
                    'list_price' => $line['list'],
                    'discount_amount' => $line['discount'],
                    'price' => $line['price'],
                    'discount_code_id' => $line['discount'] > 0 ? $quote->code?->id : null,
                    'valid_until' => Party::enterUntilAt($line['valid_until']),
                    'combo_label' => $line['combo'],
                    'combo_free' => $line['free'],
                    'status' => $card ? Ticket::PENDING : Ticket::VALID,
                ]);
            }

            if ($withCredits) {
                CreditLedger::pay($wallet, (float) $quote->total, Order::class, $order->id, null, $at, CreditTransaction::SOURCE_APP, Str::limit('Bilete · '.$party->name, 255, ''));
                $buyer->credit_balance = $wallet->credit_balance;
            }

            ActivityLogger::log($card ? 'tickets.reserved' : 'tickets.ordered', sprintf(
                $card ? '%s a rezervat %d %s „%s” la „%s” în așteptarea plății cu cardul (total %s lei%s%s%s).' : '%s a cumpărat %d %s „%s” la „%s” (total %s lei%s%s%s).',
                $buyer->name, $count, $count === 1 ? 'bilet' : 'bilete', $ticketName, $party->name,
                number_format($quote->total, 2, ',', '.'), $quote->code ? ', cod '.$quote->code->code : '', $combo ? ', combo '.$combo : '',
                $withCredits ? ', plătit cu credite' : ''
            ), actor: null);

            return $order->load('tickets');
        });
    }

    // ---- Plata cu cardul (runda 65) -------------------------------------------

    /**
     * Rezervările cu cardul al căror termen a trecut: biletele devin `void` (locurile se eliberează), comanda `card_expired`.
     * Rulează înainte de orice comandă nouă și la afișarea petrecerii; plata sosită totuși mai târziu se tratează în completeCard().
     */
    public static function expireStalePending(): int
    {
        $orders = Order::query()->where('payment_status', Order::PAY_CARD_PENDING)->where('payment_expires_at', '<', now())->get();
        foreach ($orders as $order) {
            self::releaseCard($order, Order::PAY_CARD_EXPIRED);
        }

        return $orders->count();
    }

    /** Eliberează o comandă încă în așteptare: biletele rezervate devin `void`, comanda primește statusul dat. Idempotent. */
    public static function releaseCard(Order $order, string $status = Order::PAY_CARD_FAILED): void
    {
        DB::transaction(function () use ($order, $status) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->payment_status !== Order::PAY_CARD_PENDING) {
                return;
            }
            Ticket::query()->where('order_id', $locked->id)->where('status', Ticket::PENDING)->update(['status' => Ticket::VOID]);
            $locked->update(['payment_status' => $status]);
        });
        $order->refresh();
    }

    /**
     * Plata cu cardul a reușit (apelată DOAR de webhook-ul semnat). Idempotent.
     * - comandă în așteptare: biletele devin valabile;
     * - comandă expirată/eșuată (plata a venit târziu): dacă locurile mai sunt libere, biletele se reactivează; altfel suma intră în portofel
     *   ca credite (nu avem rambursări), iar comanda rămâne anulată.
     *
     * @return string statusul final al comenzii
     */
    public static function completeCard(Order $order, ?string $providerRef = null): string
    {
        return DB::transaction(function () use ($order, $providerRef) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->payment_status, [Order::PAY_CARD, Order::PAY_CARD_CREDITED], true)) {
                return $locked->payment_status;   // webhook repetat
            }

            $party = Party::query()->whereKey($locked->party_id)->lockForUpdate()->firstOrFail();
            $tickets = Ticket::query()->where('order_id', $locked->id)->get();
            $mark = ['payment_ref' => $providerRef ?? $locked->payment_ref, 'paid_at' => now()];

            if ($locked->payment_status === Order::PAY_CARD_PENDING) {
                Ticket::query()->where('order_id', $locked->id)->where('status', Ticket::PENDING)->update(['status' => Ticket::VALID]);
                $locked->update($mark + ['payment_status' => Order::PAY_CARD]);
                ActivityLogger::log('tickets.paid_card', sprintf('Comanda de %s lei la „%s” a fost plătită cu cardul.', PaymentRows::money((float) $locked->total), $party->name), actor: null);

                return Order::PAY_CARD;
            }

            // Plată târzie: biletele fuseseră eliberate. Le reactivăm doar dacă încap din nou.
            $fits = $tickets->groupBy('ticket_type')->every(fn ($group, $type) => (self::available($party, (string) $type) ?? PHP_INT_MAX) >= $group->count())
                && ($party->tickets_for_sale === null || self::soldTotal($party) + $tickets->count() <= (int) $party->tickets_for_sale);

            if ($fits) {
                Ticket::query()->where('order_id', $locked->id)->where('status', Ticket::VOID)->update(['status' => Ticket::VALID]);
                $locked->update($mark + ['payment_status' => Order::PAY_CARD]);
                ActivityLogger::log('tickets.paid_card', sprintf('Plată cu cardul sosită după expirare: biletele comenzii de %s lei la „%s” au fost reactivate.', PaymentRows::money((float) $locked->total), $party->name), actor: null);

                return Order::PAY_CARD;
            }

            $buyer = Participant::query()->findOrFail($locked->participant_id);
            CreditLedger::load($buyer, (float) $locked->total, CreditTransaction::SOURCE_APP, null,
                Str::limit('Plată bilete returnată în portofel · '.$party->name, 255, ''), Order::class, $locked->id);
            $locked->update($mark + ['payment_status' => Order::PAY_CARD_CREDITED]);
            ActivityLogger::log('tickets.paid_card_credited', sprintf('Plată cu cardul sosită după expirare, fără locuri libere: %s lei au intrat în portofelul lui %s.', PaymentRows::money((float) $locked->total), $buyer->label()), actor: null);

            return Order::PAY_CARD_CREDITED;
        });
    }

    /** La ștergerea contului: rezervările cu cardul ale participantului se eliberează. */
    public static function releaseFor(Participant $buyer): void
    {
        Order::query()->where('participant_id', $buyer->id)->where('payment_status', Order::PAY_CARD_PENDING)->get()
            ->each(fn (Order $o) => self::releaseCard($o, Order::PAY_CARD_FAILED));
    }
}
