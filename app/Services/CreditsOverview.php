<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\Participant;
use App\Models\PartyEntry;
use App\Models\Sale;
use App\Models\SalesGroup;
use App\Support\PaymentMethods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * DXA: adaugat (Credite - pagina „Credite"). Citire agregată peste ledgerul `credit_transactions`, doar afișare —
 * scrierea rămâne exclusiv în App\Services\CreditLedger. Toate cifrele exclud mișcările anulate.
 *
 * Plățile cu credite au mereu sursa `bar` (și cele de la intrare), deci „cheltuit" se împarte după
 * reference_type (vânzare la bar vs. intrare), nu după sursă.
 */
class CreditsOverview
{
    public const TOP_LIMIT = 10;

    /** Pagina/linkul „Credite" apar cât creditele sunt active în Setări sau există deja mișcări. */
    public static function visible(): bool
    {
        return PaymentMethods::isEnabled(PaymentMethods::CREDIT) || CreditTransaction::query()->exists();
    }

    /**
     * Sumar all-time. `active` = soldul însumat al participanților (cât se datorează în credite nefolosite);
     * identitate: loaded − spent − refunded + adjusted = active.
     *
     * @return object{active: float, loaded: float, spent: float, spent_bar: float, spent_entry: float, spent_tickets: float, refunded: float, adjusted: float}
     */
    public static function summary(): object
    {
        $sum = fn (string $type, ?string $referenceType = null) => (float) CreditTransaction::query()->active()
            ->where('type', $type)
            ->when($referenceType, fn ($q) => $q->where('reference_type', $referenceType))
            ->sum('amount');

        $spentBar = -$sum(CreditTransaction::PAYMENT, Sale::class);
        $spentEntry = -$sum(CreditTransaction::PAYMENT, PartyEntry::class);
        $spentTickets = -$sum(CreditTransaction::PAYMENT, Order::class);   // runda 50: bilete cumpărate online

        return (object) [
            'active' => round(CreditLedger::totalOutstanding(), 2),
            'loaded' => round($sum(CreditTransaction::LOAD), 2),
            'spent' => round(-$sum(CreditTransaction::PAYMENT), 2),
            'spent_bar' => round($spentBar, 2),
            'spent_entry' => round($spentEntry, 2),
            'spent_tickets' => round($spentTickets, 2),
            'refunded' => round(-$sum(CreditTransaction::REFUND), 2),
            'adjusted' => round($sum(CreditTransaction::ADJUSTMENT), 2),
        ];
    }

    /**
     * Creditele încărcate, pe sursă (cheie => lei), în ordinea etichetelor; doar sursele posibile la încărcare.
     *
     * @return array<string, float>
     */
    public static function loadedBySource(): array
    {
        $rows = CreditTransaction::query()->active()->where('type', CreditTransaction::LOAD)
            ->selectRaw('source, SUM(amount) as total')->groupBy('source')->pluck('total', 'source');

        $out = [];
        foreach ([CreditTransaction::SOURCE_APP, CreditTransaction::SOURCE_RECEPTION, CreditTransaction::SOURCE_MANUAL] as $source) {
            $out[$source] = round((float) ($rows[$source] ?? 0), 2);
        }

        return $out;
    }

    /**
     * Participanții cu cel mai mare sold (> 0), fără cei anonimizați, plus cât din totalul activ dețin.
     *
     * @return array{rows: Collection<int, Participant>, sum: float, share: float}
     */
    public static function topBalances(): array
    {
        $rows = Participant::query()
            ->whereNull('anonymized_at')
            ->where('credit_balance', '>', 0)
            ->orderByDesc('credit_balance')->orderBy('name')
            ->limit(self::TOP_LIMIT)
            ->get();

        $sum = round((float) $rows->sum('credit_balance'), 2);
        $total = CreditLedger::totalOutstanding();

        return [
            'rows' => $rows,
            'sum' => $sum,
            'share' => $total > 0 ? round($sum / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Istoricul mișcărilor (inclusiv cele anulate, marcate în listă), cele mai noi întâi.
     * Filtrul de petrecere prinde: încărcările de la recepție (party_id), plățile la bar (prin sesiunea de vânzări
     * a vânzării) și plățile la intrare (prin petrecerea intrării).
     */
    public static function history(string $type = '', string $source = '', string $partyId = ''): Builder
    {
        return CreditTransaction::query()
            ->with([
                'participant:id,name,phone,anonymized_at',
                'party:id,name',
                'createdBy:id,name',
                'reference' => fn (MorphTo $m) => $m->morphWith([
                    Sale::class => ['group.party:id,name'],
                    PartyEntry::class => ['party:id,name'],
                ]),
            ])
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->when($partyId !== '', fn ($q) => $q->where(function ($w) use ($partyId) {
                $w->where('party_id', $partyId)
                    ->orWhere(fn ($x) => $x->where('reference_type', Sale::class)
                        ->whereIn('reference_id', Sale::query()->select('sales.id')
                            ->whereIn('sales_group_id', SalesGroup::query()->select('id')->where('party_id', $partyId))))
                    ->orWhere(fn ($x) => $x->where('reference_type', PartyEntry::class)
                        ->whereIn('reference_id', PartyEntry::query()->select('party_entries.id')->where('party_id', $partyId)));
            }))
            ->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
