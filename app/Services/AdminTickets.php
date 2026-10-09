<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Ticket;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 66). Interogarea paginii „Bilete” din admin: căutare (telefon, nume, cod de bilet, #comandă) și filtre
 * (petrecere, status, mod de plată, perioadă). Doar citire.
 */
class AdminTickets
{
    public const STATUS_LABELS = [
        Ticket::VALID => 'Valabil',
        Ticket::USED => 'Folosit',
        Ticket::PENDING => 'Rezervat (așteaptă plata)',
        Ticket::VOID => 'Anulat',
    ];

    /** Filtrele de plată: cheie => etichetă. „card” cuprinde toate stările plății cu cardul. */
    public const PAYMENT_LABELS = [
        'at_entry' => 'La intrare',
        'credits' => 'Credite',
        'card' => 'Card',
        'free' => 'Gratuit',
    ];

    public static function query(string $search = '', string $party = '', string $status = '', string $payment = '', string $from = '', string $to = ''): Builder
    {
        $q = trim($search);

        return Ticket::query()
            ->with(['party', 'owner', 'holder', 'order'])
            ->when($party !== '', fn (Builder $b) => $b->where('party_id', (int) $party))
            ->when(isset(self::STATUS_LABELS[$status]), fn (Builder $b) => $b->where('status', $status))
            ->when($payment === 'free', fn (Builder $b) => $b->where('price', 0))
            ->when(in_array($payment, ['at_entry', 'credits'], true), fn (Builder $b) => $b->where('price', '>', 0)
                ->whereHas('order', fn (Builder $o) => $o->where('payment_status', $payment)))
            ->when($payment === 'card', fn (Builder $b) => $b->whereHas('order', fn (Builder $o) => $o->where('payment_status', 'like', 'card%')))
            ->when($from !== '', fn (Builder $b) => $b->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn (Builder $b) => $b->where('created_at', '<=', $to.' 23:59:59'))
            ->when($q !== '', fn (Builder $b) => self::search($b, $q))
            ->orderByDesc('id');
    }

    private static function search(Builder $b, string $q): Builder
    {
        $like = fn (string $v) => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v).'%';

        // „#123” = numărul comenzii.
        if (preg_match('/^#\s*(\d{1,12})$/', $q, $m)) {
            return $b->where('order_id', (int) $m[1]);
        }

        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $phonePart = $digits !== '' ? ltrim(str_starts_with($digits, '0') ? substr($digits, 1) : $digits, '0') : '';
        $hex = strtolower(str_replace('-', '', $q));
        $isCode = preg_match('/^[0-9a-f]{4,32}$/', $hex) === 1;

        return $b->where(function (Builder $w) use ($q, $like, $phonePart, $hex, $isCode) {
            if ($isCode) {
                $w->orWhere('uuid', 'like', $hex.'%')->orWhere('uuid', 'like', substr($hex, 0, 8).'-'.substr($hex, 8).'%');
            }

            $people = Participant::query()->select('id')->where(function ($p) use ($q, $like, $phonePart) {
                $p->where('name', 'like', $like($q));
                if (mb_strlen($phonePart) >= 3) {
                    $p->orWhere('phone', 'like', '%'.$phonePart.'%');
                }
            });
            $w->orWhereIn('owner_participant_id', (clone $people))
                ->orWhereIn('holder_participant_id', (clone $people))
                ->orWhereIn('order_id', Order::query()->select('id')->whereIn('participant_id', (clone $people)));
            if (mb_strlen($phonePart) >= 3) {
                $w->orWhere('holder_phone', 'like', '%'.$phonePart.'%');
            }
        });
    }

    /** Eticheta modului de plată al unui bilet (după comandă și preț). */
    public static function paymentLabel(Ticket $t): string
    {
        if ((float) $t->price <= 0) {
            return 'Gratuit';
        }

        return match ($t->order?->payment_status ?? Order::PAY_AT_ENTRY) {
            Order::PAY_AT_ENTRY => 'La intrare',
            Order::PAY_CREDITS => 'Credite',
            Order::PAY_PAID => 'Achitat',
            Order::PAY_CARD => 'Card',
            Order::PAY_CARD_PENDING => 'Card · așteaptă plata',
            Order::PAY_CARD_EXPIRED => 'Card · rezervare expirată',
            Order::PAY_CARD_FAILED => 'Card · plată eșuată',
            Order::PAY_CARD_CREDITED => 'Card · returnat în portofel',
            default => (string) $t->order?->payment_status,
        };
    }

    /** Se mai poate anula? Doar biletele valabile (nefolosite, plătite sau de plătit la intrare). */
    public static function canCancel(Ticket $t): bool
    {
        return $t->status === Ticket::VALID;
    }

    /**
     * Cât se poate returna în credite la anulare: prețul plătit, doar dacă comanda e achitată (credite / card / achitat) și cumpărătorul are încă cont.
     * La „de plătit la intrare”, bilet gratuit sau cont anonimizat → 0.
     */
    public static function refundable(Ticket $t): float
    {
        $order = $t->order;
        if (! $order || (float) $t->price <= 0 || ! in_array($order->payment_status, [Order::PAY_CREDITS, Order::PAY_CARD, Order::PAY_PAID], true)) {
            return 0.0;
        }
        $buyer = $order->participant;

        return $buyer && ! $buyer->isAnonymized() ? round((float) $t->price, 2) : 0.0;
    }

    /**
     * Anulează un bilet valabil (motiv obligatoriu): locul se eliberează, iar dacă $refund, prețul plătit intră în portofelul cumpărătorului ca credite
     * (singura formă de rambursare; banii de pe card nu se returnează). Idempotent: un bilet anulat nu se mai anulează. Întoarce suma returnată.
     */
    public static function cancel(Ticket $ticket, string $reason, bool $refund, ?int $adminId = null): float
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new DomainException('Spune de ce anulezi biletul (motiv obligatoriu).');
        }

        return DB::transaction(function () use ($ticket, $reason, $refund, $adminId) {
            $t = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! self::canCancel($t)) {
                throw new DomainException($t->status === Ticket::USED ? 'Biletul a fost deja folosit la intrare.' : 'Biletul nu mai e valabil, nu se poate anula.');
            }
            $t->load(['order.participant', 'party']);

            $amount = $refund ? self::refundable($t) : 0.0;
            $t->update([
                'status' => Ticket::VOID, 'cancelled_at' => now(), 'cancelled_by' => $adminId,
                'cancel_reason' => Str::limit($reason, 250, ''), 'refunded_amount' => $amount,
            ]);

            if ($amount > 0) {
                CreditLedger::load($t->order->participant, $amount, CreditTransaction::SOURCE_MANUAL, $adminId,
                    Str::limit('Bilet anulat · '.($t->party?->name ?? 'petrecere').' · '.$reason, 255, ''), Ticket::class, $t->id);
            }

            ActivityLogger::log('tickets.cancelled', sprintf(
                'A anulat biletul %s (%s, „%s”): %s%s.',
                $t->shortCode(), $t->ticket_type, $t->party?->name ?? 'petrecere ștearsă', $reason,
                $amount > 0 ? '. Returnat în credite: '.PaymentRows::money($amount).' lei' : ($refund ? '. Nimic de returnat' : '. Fără rambursare')
            ));

            return $amount;
        });
    }
}
