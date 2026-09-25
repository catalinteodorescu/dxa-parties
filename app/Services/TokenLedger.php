<?php

namespace App\Services;

use App\Models\Party;
use App\Models\ReceptionSession;
use App\Models\SalePayment;
use App\Models\TokenTransaction;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Recepție - tokeni). Singura cale de a vinde, ajusta, casa și anula tokeni (admin acum, PWA mai târziu).
 *
 * Tokeni în circulație = SUM(ledger neanulat) − tokenii încasați la bar (sale_payments, vânzări finalizate).
 *  - vânzare la recepție: +tokens, cu cursul înghețat și plata (mixtă, pe total; fără credite, fără tokeni);
 *  - ajustare (stoc inițial / corecție): tokens cu semn, motiv obligatoriu;
 *  - casare: aduce circulația la 0 (motiv obligatoriu); permite oprirea tokenilor.
 * Circulația poate ieși negativă cât timp nu s-a înregistrat „stocul inițial” (tokeni vânduți înainte de acest modul):
 * o ajustare pozitivă o corectează. Nu se șterge nimic: doar vânzările se pot anula (cu motiv), dacă tokenii nu au fost folosiți.
 */
class TokenLedger
{
    public const MAX_SALE = 1000;

    public const MAX_ADJUST = 100000;

    // ---- Citire ----------------------------------------------------------

    /** Tokeni emiși net (vânzări + ajustări − casări), fără mișcările anulate. */
    public static function issued(): int
    {
        return (int) TokenTransaction::query()->active()->sum('tokens');
    }

    /** Tokeni încasați la bar (toate vânzările finalizate), opțional doar ale sesiunilor unei petreceri. */
    public static function collected(?Party $party = null): int
    {
        $q = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')
            ->where('sale_payments.method', PaymentMethods::TOKEN);

        if ($party) {
            $q->join('sales_groups', 'sales_groups.id', '=', 'sales.sales_group_id')->where('sales_groups.party_id', $party->id);
        }

        return (int) $q->sum('sale_payments.tokens');
    }

    public static function circulation(): int
    {
        return self::issued() - self::collected();
    }

    /**
     * Tokenii unei petreceri: vânduți la recepție (număr și lei) și încasați la bar în sesiunile ei.
     * null dacă nu există nimic. Diferența nu e neapărat o eroare: tokenii sunt permanenți.
     *
     * @return object{sold: int, sold_amount: float, collected: int, diff: int}|null
     */
    public static function partyTotals(Party $party): ?object
    {
        $sold = TokenTransaction::query()->active()->where('type', TokenTransaction::SOLD)->where('party_id', $party->id);
        $soldTokens = (int) (clone $sold)->sum('tokens');
        $collected = self::collected($party);

        if ($soldTokens === 0 && $collected === 0) {
            return null;
        }

        return (object) [
            'sold' => $soldTokens,
            'sold_amount' => round((float) (clone $sold)->sum('amount'), 2),
            'collected' => $collected,
            'diff' => $soldTokens - $collected,
        ];
    }

    /** Gardul de „Oprit” (PaymentMethods): tokenii nu se opresc cât mai sunt în circulație. */
    public static function offBlockReason(): ?string
    {
        $circ = self::circulation();

        return $circ > 0
            ? 'Mai sunt '.number_format($circ, 0, ',', '.').' tokeni în circulație. Casează-i din Recepție › Tokeni sau așteaptă să fie folosiți; pentru retragere treptată alege „Doar încasare”.'
            : null;
    }

    // ---- Scriere ---------------------------------------------------------

    /**
     * Vinde tokeni la recepție. Plata se introduce pe total (poate fi mixtă), fără credite.
     *
     * @param  array<int, array{method?: string, amount?: mixed}>  $payments
     * @param  bool  $enforceState  false doar pentru seedere/importuri (altfel: doar petreceri viitoare sau în desfășurare)
     * @param  int|null  $participantId  participantul care cumpără (opțional; anonim dacă lipsește)
     */
    public static function sell(
        Party $party,
        int $tokens,
        array $payments,
        ?int $adminId = null,
        ?Carbon $at = null,
        bool $enforceState = true,
        ?int $participantId = null,
    ): TokenTransaction {
        if ($tokens < 1 || $tokens > self::MAX_SALE) {
            throw new DomainException('Numărul de tokeni trebuie să fie între 1 și '.self::MAX_SALE.'.');
        }
        if (! PaymentMethods::tokensSellable()) {
            throw new DomainException('Tokenii nu se mai vând (starea lor din Setări nu permite vânzarea).');
        }
        if ($enforceState && ! in_array($party->state(), ['upcoming', 'live'], true)) {
            throw new DomainException('Petrecerea nu primește vânzări (nu e publicată sau s-a încheiat).');
        }

        $participant = $participantId ? ParticipantRegistry::usable($participantId) : null;

        $rate = PaymentMethods::tokenRate();
        if ($rate <= 0) {
            throw new DomainException('Cursul token → lei nu e setat (Setări › Metode de plată).');
        }

        $totalCents = PaymentRows::cents($tokens * $rate);
        $allowed = array_diff_key(PaymentMethods::forEntry($party), [PaymentMethods::CREDIT => true]);
        $rows = PaymentRows::normalize($allowed, $payments, $totalCents, 'la cumpărarea de tokeni', 'Nu se înregistrează nicio plată.');

        $tx = DB::transaction(function () use ($party, $tokens, $rate, $totalCents, $rows, $adminId, $at, $participant) {
            // Sesiunea de recepție deschisă a petrecerii (se deschide singură la prima înregistrare).
            $session = ReceptionSession::openFor($party->id, $adminId);

            $tx = TokenTransaction::create([
                'type' => TokenTransaction::SOLD,
                'party_id' => $party->id,
                'reception_session_id' => $session->id,
                'tokens' => $tokens,
                'token_rate' => $rate,
                'amount' => $totalCents / 100,
                'occurred_at' => $at ?? now(),
                'participant_id' => $participant?->id,
                'created_by' => $adminId,
            ]);

            foreach ($rows as [$method, $cents]) {
                $tx->payments()->create(['method' => $method, 'amount' => $cents / 100]);
            }

            return $tx;
        });

        ActivityLogger::log('tokens.sold', sprintf(
            'A vândut %s tokeni (%s lei) la „%s”%s.',
            number_format($tokens, 0, ',', '.'),
            PaymentRows::money($totalCents / 100),
            $party->name,
            $participant ? ' — participant: '.$participant->name : ''
        ));

        return $tx;
    }

    /** Ajustare (stoc inițial / corecție), cu semn. Motiv obligatoriu; nu poate duce circulația sub zero. */
    public static function adjust(int $tokens, string $reason, ?int $adminId = null, ?Carbon $at = null): TokenTransaction
    {
        $reason = trim($reason);

        if ($tokens === 0 || abs($tokens) > self::MAX_ADJUST) {
            throw new DomainException('Ajustarea trebuie să fie diferită de 0 și cel mult '.number_format(self::MAX_ADJUST, 0, ',', '.').' tokeni.');
        }
        if ($reason === '') {
            throw new DomainException('Spune de ce ajustezi tokenii (motiv obligatoriu).');
        }
        if ($tokens < 0 && self::circulation() + $tokens < 0) {
            throw new DomainException('Ajustarea ar duce circulația sub zero ('.number_format(self::circulation(), 0, ',', '.').' tokeni acum).');
        }

        $tx = TokenTransaction::create([
            'type' => TokenTransaction::ADJUSTMENT,
            'tokens' => $tokens,
            'note' => Str::limit($reason, 255, ''),
            'occurred_at' => $at ?? now(),
            'created_by' => $adminId,
        ]);

        ActivityLogger::log('tokens.adjusted', sprintf('A ajustat tokenii cu %+d: %s.', $tokens, $reason));

        return $tx;
    }

    /** Casează tot ce a rămas în circulație (motiv obligatoriu). Circulația devine 0. */
    public static function writeOff(string $reason, ?int $adminId = null, ?Carbon $at = null): TokenTransaction
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Spune de ce casezi tokenii (motiv obligatoriu).');
        }

        $circulation = self::circulation();
        if ($circulation <= 0) {
            throw new DomainException('Nu sunt tokeni în circulație de casat.');
        }

        $tx = TokenTransaction::create([
            'type' => TokenTransaction::WRITE_OFF,
            'tokens' => -$circulation,
            'note' => Str::limit($reason, 255, ''),
            'occurred_at' => $at ?? now(),
            'created_by' => $adminId,
        ]);

        ActivityLogger::log('tokens.written_off', sprintf('A casat %s tokeni: %s.', number_format($circulation, 0, ',', '.'), $reason));

        return $tx;
    }

    /** Anulează o vânzare de tokeni (motiv obligatoriu), doar dacă tokenii ei nu au fost deja folosiți la bar. */
    public static function cancel(int $id, string $reason, ?int $adminId = null): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Spune de ce anulezi vânzarea (motiv obligatoriu).');
        }

        $tx = TokenTransaction::query()->active()->where('type', TokenTransaction::SOLD)->find($id)
            ?? throw new DomainException('Vânzarea nu există sau e deja anulată.');

        if ($blocked = ReceptionSession::closedBlockReason([$tx->reception_session_id], 'vânzarea de tokeni')) {
            throw new DomainException($blocked);
        }

        if (self::circulation() - $tx->tokens < 0) {
            throw new DomainException('Tokenii au fost deja folosiți la bar (sau lipsește stocul inițial din Recepție › Tokeni), deci vânzarea nu se poate anula.');
        }

        $tx->update([
            'cancelled_at' => now(),
            'cancelled_by' => $adminId,
            'cancel_reason' => Str::limit($reason, 255, ''),
        ]);

        ActivityLogger::log('tokens.cancelled', sprintf(
            'A anulat vânzarea de %s tokeni (%s lei): %s.',
            number_format($tx->tokens, 0, ',', '.'),
            PaymentRows::money((float) $tx->amount),
            $reason
        ));
    }
}
