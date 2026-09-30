<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Participant;
use App\Models\Party;
use App\Models\ReceptionSession;
use App\Support\PaymentMethods;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DXA: adaugat (Portofelul de credite). Singura cale de a încărca, plăti, refunda sau ajusta credite
 * (1 credit = 1 leu, curs fix). Soldul participantului (`participants.credit_balance`) e doar un cache:
 * se recalculează AICI, niciodată prin update direct în altă parte — exclude rândurile anulate (vezi recalc()).
 *
 * Etapa 1: încărcare/ajustare/refund manuale din admin (fișa participantului) + soldul afișat.
 * Etapa 2 (curentă): vânzare de credite la recepție — vezi sell()/cancel(); intră în ACELAȘI flux/raport ca
 * tokenii (party_id + reception_session_id, aceeași sesiune, aceeași închidere de casă).
 * Etapa 3: debitare automată la plată (bar + intrare) — vezi pay().
 */
class CreditLedger
{
    public const MAX_LOAD = 5000;

    public const MAX_ADJUST = 5000;

    public const MAX_SALE = 5000;

    // ---- Citire ------------------------------------------------------

    public static function balance(Participant $participant): float
    {
        return (float) $participant->credit_balance;
    }

    /** Suma soldurilor tuturor participanților (folosită de gardul „Oprit”). */
    public static function totalOutstanding(): float
    {
        return (float) Participant::query()->sum('credit_balance');
    }

    /** Gardul de dezactivare (PaymentMethods::CREDIT): nu se oprește cât există solduri > 0. */
    public static function offBlockReason(): ?string
    {
        $total = self::totalOutstanding();

        return $total > 0
            ? 'Participanții mai au în total '.number_format($total, 2, ',', '.').' lei în credite. Așteaptă să fie folosite sau refundează-le manual din fișele lor înainte de a opri creditele.'
            : null;
    }

    /**
     * Creditele vândute la recepție pentru o petrecere (număr și lei), sau null dacă nu s-a vândut niciun credit.
     *
     * @return object{sold_count: int, sold_amount: float}|null
     */
    public static function partyTotals(Party $party): ?object
    {
        $sold = CreditTransaction::query()->active()
            ->where('type', CreditTransaction::LOAD)->where('source', CreditTransaction::SOURCE_RECEPTION)
            ->where('party_id', $party->id);

        $count = (clone $sold)->count();
        if ($count === 0) {
            return null;
        }

        return (object) [
            'sold_count' => $count,
            'sold_amount' => round((float) (clone $sold)->sum('amount'), 2),
        ];
    }

    // ---- Scriere -------------------------------------------------------

    /**
     * Încarcă credite unui participant (amount > 0). $source: participant_app / reception / manual.
     * La sursa `manual`, motivul e obligatoriu (e o corecție/stoc inițial, nu o vânzare).
     */
    public static function load(
        Participant $participant,
        float $amount,
        string $source,
        ?int $adminId = null,
        ?string $note = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?Carbon $at = null,
    ): CreditTransaction {
        if ($amount <= 0 || $amount > self::MAX_LOAD) {
            throw new DomainException('Suma de încărcat trebuie să fie între 0 și '.number_format(self::MAX_LOAD, 0, ',', '.').' lei.');
        }
        if ($source === CreditTransaction::SOURCE_MANUAL && trim((string) $note) === '') {
            throw new DomainException('Spune de ce încarci credite manual (motiv obligatoriu).');
        }

        $tx = self::write($participant, CreditTransaction::LOAD, $amount, $source, $adminId, $note, $referenceType, $referenceId, $at);

        ActivityLogger::log('credits.loaded', sprintf(
            'A încărcat %s lei credite lui %s (sursă: %s)%s.',
            PaymentRows::money($amount),
            $participant->label(),
            CreditTransaction::SOURCE_LABELS[$source] ?? $source,
            $note ? ' — '.$note : ''
        ));

        return $tx;
    }

    /**
     * Debitează credite la o plată (bar/intrare), automat, fără aprobarea participantului.
     * Aruncă excepție dacă soldul nu ajunge. $referenceType/$referenceId leagă rândul de vânzare/intrare.
     */
    public static function pay(
        Participant $participant,
        float $amount,
        string $referenceType,
        int $referenceId,
        ?int $adminId = null,
        ?Carbon $at = null,
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new DomainException('Suma de plată cu credite trebuie să fie mai mare ca 0.');
        }
        if (self::balance($participant) < $amount) {
            throw new DomainException('Soldul de credite ('.number_format(self::balance($participant), 2, ',', '.').' lei) nu ajunge pentru '.PaymentRows::money($amount).' lei.');
        }

        return self::write($participant, CreditTransaction::PAYMENT, -$amount, CreditTransaction::SOURCE_BAR, $adminId, null, $referenceType, $referenceId, $at);
    }

    /**
     * Refund manual (amount > 0 = suma dată înapoi participantului), motiv obligatoriu — nu există refund automat.
     * Scade din sold (creditele refundate ies din portofel); nu poate refunda mai mult decât soldul.
     */
    public static function refund(Participant $participant, float $amount, string $reason, ?int $adminId = null, ?Carbon $at = null): CreditTransaction
    {
        $reason = trim($reason);
        if ($amount <= 0) {
            throw new DomainException('Suma de refund trebuie să fie mai mare ca 0.');
        }
        if ($reason === '') {
            throw new DomainException('Spune de ce refundezi credite (motiv obligatoriu).');
        }
        if (self::balance($participant) < $amount) {
            throw new DomainException('Soldul de credite ('.number_format(self::balance($participant), 2, ',', '.').' lei) nu ajunge pentru un refund de '.PaymentRows::money($amount).' lei.');
        }

        $tx = self::write($participant, CreditTransaction::REFUND, -$amount, CreditTransaction::SOURCE_MANUAL, $adminId, Str::limit($reason, 255, ''), null, null, $at);

        ActivityLogger::log('credits.refunded', sprintf(
            'A refundat %s lei credite lui %s: %s.',
            PaymentRows::money($amount),
            $participant->label(),
            $reason
        ));

        return $tx;
    }

    /** Ajustare manuală, cu semn (motiv obligatoriu). Nu poate duce soldul sub zero. */
    public static function adjust(Participant $participant, float $amount, string $reason, ?int $adminId = null, ?Carbon $at = null): CreditTransaction
    {
        $reason = trim($reason);
        if ($amount === 0.0 || abs($amount) > self::MAX_ADJUST) {
            throw new DomainException('Ajustarea trebuie să fie diferită de 0 și cel mult '.number_format(self::MAX_ADJUST, 0, ',', '.').' lei.');
        }
        if ($reason === '') {
            throw new DomainException('Spune de ce ajustezi creditele (motiv obligatoriu).');
        }
        if ($amount < 0 && self::balance($participant) + $amount < 0) {
            throw new DomainException('Ajustarea ar duce soldul sub zero ('.number_format(self::balance($participant), 2, ',', '.').' lei acum).');
        }

        $tx = self::write($participant, CreditTransaction::ADJUSTMENT, $amount, CreditTransaction::SOURCE_MANUAL, $adminId, Str::limit($reason, 255, ''), null, null, $at);

        ActivityLogger::log('credits.adjusted', sprintf(
            'A ajustat creditele lui %s cu %+.2f lei: %s.',
            $participant->label(),
            $amount,
            $reason
        ));

        return $tx;
    }

    /**
     * Vinde credite unui participant la recepție. Plata se introduce pe total (poate fi mixtă), fără credite
     * (nu se cumpără credite cu credite). La fel ca vânzarea de tokeni: se leagă de sesiunea de recepție a
     * petrecerii (se deschide singură la prima înregistrare) — aceeași sesiune/raport ca tokenii.
     *
     * @param  array<int, array{method?: string, amount?: mixed}>  $payments
     * @param  bool  $enforceState  false doar pentru seedere/importuri (altfel: doar petreceri viitoare sau în desfășurare)
     */
    public static function sell(
        Party $party,
        Participant $participant,
        float $amount,
        array $payments,
        ?int $adminId = null,
        ?Carbon $at = null,
        bool $enforceState = true,
    ): CreditTransaction {
        if ($amount <= 0 || $amount > self::MAX_SALE) {
            throw new DomainException('Suma de credite de vândut trebuie să fie între 0 și '.number_format(self::MAX_SALE, 0, ',', '.').' lei.');
        }
        if (! PaymentMethods::creditsPurchasable()) {
            throw new DomainException('Creditele nu se mai vând (starea lor din Setări nu permite cumpărarea).');
        }
        if ($enforceState && ! $party->acceptsReceptionRecords()) {
            throw new DomainException('Petrecerea nu primește vânzări (nu e publicată, sau s-a încheiat și casa e închisă).');
        }

        $totalCents = PaymentRows::cents($amount);
        $allowed = array_diff_key(PaymentMethods::forEntry($party), [PaymentMethods::CREDIT => true]);
        $rows = PaymentRows::normalize($allowed, $payments, $totalCents, 'la cumpărarea de credite', 'Nu se înregistrează nicio plată.');

        $onlyExisting = $enforceState && $party->receptionOnlyExistingSession();

        $tx = DB::transaction(function () use ($party, $participant, $amount, $rows, $adminId, $at, $onlyExisting) {
            // Sesiunea de recepție deschisă a petrecerii (se deschide singură la prima înregistrare).
            $session = ReceptionSession::openFor($party->id, $adminId, onlyExisting: $onlyExisting);

            $tx = CreditTransaction::create([
                'participant_id' => $participant->id,
                'type' => CreditTransaction::LOAD,
                'amount' => $amount,
                'source' => CreditTransaction::SOURCE_RECEPTION,
                'party_id' => $party->id,
                'reception_session_id' => $session->id,
                'occurred_at' => $at ?? now(),
                'created_by' => $adminId,
            ]);

            foreach ($rows as [$method, $cents]) {
                $tx->payments()->create(['method' => $method, 'amount' => $cents / 100]);
            }

            self::recalc($participant);

            return $tx;
        });

        ActivityLogger::log('credits.sold', sprintf(
            'A vândut %s lei credite lui %s la „%s” (%s lei).',
            PaymentRows::money($amount),
            $participant->label(),
            $party->name,
            PaymentRows::money($totalCents / 100)
        ));

        return $tx;
    }

    /** Anulează o vânzare de credite (motiv obligatoriu), doar dacă sesiunea ei nu a fost închisă și creditele n-au fost deja cheltuite. */
    public static function cancel(int $id, string $reason, ?int $adminId = null): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Spune de ce anulezi vânzarea (motiv obligatoriu).');
        }

        $tx = CreditTransaction::query()->active()
            ->where('type', CreditTransaction::LOAD)->where('source', CreditTransaction::SOURCE_RECEPTION)
            ->find($id)
            ?? throw new DomainException('Vânzarea nu există sau e deja anulată.');

        if ($blocked = ReceptionSession::closedBlockReason([$tx->reception_session_id], 'vânzarea de credite')) {
            throw new DomainException($blocked);
        }

        $participant = Participant::query()->findOrFail($tx->participant_id);
        if (self::balance($participant) < (float) $tx->amount) {
            throw new DomainException('Creditele au fost deja folosite, deci vânzarea nu se poate anula.');
        }

        DB::transaction(function () use ($tx, $reason, $adminId, $participant) {
            $tx->update([
                'cancelled_at' => now(),
                'cancelled_by' => $adminId,
                'cancel_reason' => Str::limit($reason, 255, ''),
            ]);

            self::recalc($participant);
        });

        ActivityLogger::log('credits.sale_cancelled', sprintf(
            'A anulat vânzarea de %s lei credite lui %s: %s.',
            PaymentRows::money((float) $tx->amount),
            $participant->label(),
            $reason
        ));
    }

    /**
     * Returnează creditele plătite pentru o vânzare la bar / o intrare care se anulează: marchează rândurile
     * `payment` legate de ele ca anulate (ledgerul rămâne imuabil, nu se șterge nimic) și recalculează soldul.
     * Apelată din Sale::cancel() și EntryRecorder::cancelBatch(). Returnează suma totală redată participanților.
     *
     * @param  array<int, int>  $referenceIds
     */
    public static function reversePayments(string $referenceType, array $referenceIds, string $reason, ?int $adminId = null): float
    {
        if ($referenceIds === []) {
            return 0.0;
        }

        $payments = CreditTransaction::query()->active()
            ->where('type', CreditTransaction::PAYMENT)
            ->where('reference_type', $referenceType)
            ->whereIn('reference_id', $referenceIds)
            ->get();

        if ($payments->isEmpty()) {
            return 0.0;
        }

        $returned = 0.0;

        DB::transaction(function () use ($payments, $reason, $adminId, &$returned) {
            foreach ($payments as $tx) {
                $tx->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $adminId,
                    'cancel_reason' => Str::limit('Anulare: '.trim($reason), 255, ''),
                ]);
                $returned += abs((float) $tx->amount);
            }

            foreach ($payments->pluck('participant_id')->unique() as $participantId) {
                self::recalc(Participant::query()->findOrFail($participantId));
            }
        });

        ActivityLogger::log('credits.payment_reversed', sprintf(
            'Au fost returnați %s lei credite în %d portofel(e) prin anularea plății: %s.',
            PaymentRows::money($returned),
            $payments->pluck('participant_id')->unique()->count(),
            $reason
        ));

        return round($returned, 2);
    }

    // ---- Intern ----------------------------------------------------------

    private static function write(
        Participant $participant,
        string $type,
        float $amount,
        string $source,
        ?int $adminId,
        ?string $note,
        ?string $referenceType,
        ?int $referenceId,
        ?Carbon $at,
    ): CreditTransaction {
        return DB::transaction(function () use ($participant, $type, $amount, $source, $adminId, $note, $referenceType, $referenceId, $at) {
            $tx = CreditTransaction::create([
                'participant_id' => $participant->id,
                'type' => $type,
                'amount' => $amount,
                'source' => $source,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'occurred_at' => $at ?? now(),
                'created_by' => $adminId,
            ]);

            self::recalc($participant);

            return $tx;
        });
    }

    /**
     * Recalculează soldul participantului din ledger (SUM(amount), fără rândurile anulate) și îl salvează pe
     * cache-ul `credit_balance` — cu lockForUpdate cât ține tranzacția, ca două scrieri simultane pe același
     * participant să nu piardă una din ele. Apelată DOAR din interiorul unui DB::transaction().
     */
    private static function recalc(Participant $participant): float
    {
        $locked = Participant::query()->whereKey($participant->id)->lockForUpdate()->first();
        $balance = (float) CreditTransaction::query()->where('participant_id', $participant->id)->whereNull('cancelled_at')->sum('amount');
        // credit_balance nu e în $fillable (e doar cache, niciodată mass-assignable), deci forceFill aici.
        $locked->forceFill(['credit_balance' => $balance])->save();
        $participant->credit_balance = $balance;

        return $balance;
    }
}
