<?php

namespace App\Services;

use App\Models\CreditTopup;
use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (runda 51). Încărcarea de credite din aplicație (cu cardul), până la pagina de plată.
 *
 * Fluxul: quote() (cât plătește / cât primește, cu bonus) → start() creează o încărcare `pending` cu sumele ÎNGHEȚATE → participantul e
 * trimis la plată (PaymentGateway) → la plata reușită furnizorul apelează complete(): creditele intră în ledger (suma + bonus, un singur rând,
 * sursa participant_app) și încărcarea devine `paid`. complete() e idempotent (un webhook repetat nu dublează creditele) și funcționează și
 * pe o încărcare expirată/anulată, fiindcă banii au fost luați; fail()/cancel() nu ating ledgerul.
 */
class CreditTopups
{
    /** Cât timp rămâne valabilă o încărcare neplătită (minute). */
    public const TTL_MINUTES = 30;

    /**
     * Calculează o încărcare fără să scrie nimic.
     *
     * @return object{amount: float, percent: float, bonus: float, credited: float, fee: float, to_pay: float, next: ?array{min: float, percent: float, missing: float}}
     *
     * @throws DomainException
     */
    public static function quote(float $amount): object
    {
        if (! PaymentMethods::creditsPurchasable()) {
            throw new DomainException('Încărcarea de credite nu este disponibilă acum.');
        }
        $amount = round($amount, 2);
        if ($amount < CreditBonus::min() || $amount > CreditBonus::max()) {
            throw new DomainException('Poți încărca între '.PaymentRows::money(CreditBonus::min()).' și '.PaymentRows::money(CreditBonus::max()).' lei.');
        }

        $bonus = CreditBonus::bonusFor($amount);
        $next = CreditBonus::nextTier($amount);
        $fee = 0.0;   // comisionul Stripe îl suportă școala: participantul plătește exact suma încărcată

        return (object) [
            'amount' => $amount,
            'percent' => CreditBonus::percentFor($amount),
            'bonus' => $bonus,
            'credited' => round($amount + $bonus, 2),
            'fee' => $fee,
            'to_pay' => round($amount + $fee, 2),
            'next' => $next ? $next + ['missing' => round($next['min'] - $amount, 2)] : null,
        ];
    }

    /**
     * Începe o încărcare: o creează `pending`, cu suma/bonusul/taxa înghețate; încărcările neplătite anterioare ale participantului se anulează.
     *
     * @throws DomainException
     */
    public static function start(Participant $participant, float $amount): CreditTopup
    {
        if (! $participant->hasAccount()) {
            throw new DomainException('Ai nevoie de un cont activ ca să încarci credite.');
        }
        $quote = self::quote($amount);

        return DB::transaction(function () use ($participant, $quote) {
            self::expireStale();
            CreditTopup::query()->where('participant_id', $participant->id)->where('status', CreditTopup::PENDING)
                ->update(['status' => CreditTopup::CANCELLED]);

            $topup = CreditTopup::create([
                'uuid' => (string) Str::uuid(),
                'participant_id' => $participant->id,
                'amount' => $quote->amount,
                'bonus' => $quote->bonus,
                'fee' => $quote->fee,
                'status' => CreditTopup::PENDING,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);

            ActivityLogger::log('credits.topup_started', sprintf(
                '%s a început o încărcare de %s lei credite%s (în așteptarea plății).',
                $participant->label(), PaymentRows::money($quote->amount),
                $quote->bonus > 0 ? ' + bonus '.PaymentRows::money($quote->bonus).' lei' : ''
            ), actor: null);

            return $topup;
        });
    }

    /**
     * Plata a reușit: creditează participantul (suma + bonus, un singur rând în ledger). Idempotent.
     *
     * @return CreditTransaction rândul din ledger (cel existent, dacă încărcarea era deja plătită)
     */
    public static function complete(CreditTopup $topup, ?string $providerRef = null, ?string $provider = null): CreditTransaction
    {
        return DB::transaction(function () use ($topup, $providerRef, $provider) {
            $locked = CreditTopup::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CreditTopup::PAID && $locked->credit_transaction_id) {
                return CreditTransaction::query()->findOrFail($locked->credit_transaction_id);
            }

            $participant = Participant::query()->findOrFail($locked->participant_id);
            $tx = CreditLedger::load(
                $participant, $locked->credited(), CreditTransaction::SOURCE_APP, null,
                'Încărcare cu cardul', CreditTopup::class, $locked->id, null, (float) $locked->bonus
            );

            $locked->update([
                'status' => CreditTopup::PAID,
                'paid_at' => now(),
                'credit_transaction_id' => $tx->id,
                'provider' => $provider ?? $locked->provider,
                'provider_ref' => $providerRef ?? $locked->provider_ref,
            ]);
            $topup->refresh();

            return $tx;
        });
    }

    /** Mesajul de după o încărcare plătită (aceleași cuvinte pentru plata simulată și pentru întoarcerea de la Stripe). */
    public static function paidMessage(CreditTopup $topup): string
    {
        return (float) $topup->bonus > 0
            ? 'Ai încărcat '.PaymentRows::money((float) $topup->amount).' lei credite și ai primit bonus '.PaymentRows::money((float) $topup->bonus).' lei.'
            : 'Ai încărcat '.PaymentRows::money((float) $topup->amount).' lei credite.';
    }

    /** Plata a eșuat: se marchează, fără efect în ledger (doar dacă încă nu e plătită). */
    public static function fail(CreditTopup $topup): void
    {
        CreditTopup::query()->whereKey($topup->id)->where('status', CreditTopup::PENDING)->update(['status' => CreditTopup::FAILED]);
        $topup->refresh();
    }

    /** Participantul renunță: doar o încărcare încă neplătită se anulează. */
    public static function cancel(CreditTopup $topup): void
    {
        CreditTopup::query()->whereKey($topup->id)->where('status', CreditTopup::PENDING)->update(['status' => CreditTopup::CANCELLED]);
        $topup->refresh();
    }

    /** Încărcările neplătite al căror termen a trecut devin `expired`. */
    public static function expireStale(): int
    {
        return CreditTopup::query()->where('status', CreditTopup::PENDING)->where('expires_at', '<', now())
            ->update(['status' => CreditTopup::EXPIRED]);
    }
}
