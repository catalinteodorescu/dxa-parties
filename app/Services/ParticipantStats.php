<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\SalePayment;
use App\Models\TokenTransaction;

/**
 * DXA: adaugat (Participanți - cheltuieli). Ce a făcut un participant, pe petreceri: intrări, cheltuit la bar, tokeni cumpărați.
 *
 * Definiții (aceleași peste tot):
 *  - intrări: intrările valabile (neanulate), cu suma plătită la intrare;
 *  - cheltuit la bar: suma plăților vânzărilor FINALIZATE (neanulate) făcute pe numele participantului, fără „beneficiu”
 *    (cadourile nu sunt cheltuială); include și plățile cu tokeni/credite, la valoarea lor în lei;
 *  - tokeni cumpărați: vânzările de tokeni neanulate pe numele participantului (număr și lei).
 * „Cheltuit la bar” și „tokeni cumpărați” sunt metrici separate: tokenii cumpărați se cheltuie ulterior la bar, deci nu se adună.
 */
class ParticipantStats
{
    /**
     * Fișa unui participant: totaluri și defalcare pe petreceri (cele mai recente primele; „fără petrecere” la urmă).
     *
     * @return object{entries: int, entries_amount: float, entries_paid: int, bar_spent: float, bar_sales: int, tokens: int, tokens_amount: float, parties: \Illuminate\Support\Collection}
     */
    public static function for(Participant $participant): object
    {
        $id = $participant->id;

        $entries = PartyEntry::query()->active()->where('participant_id', $id)
            ->groupBy('party_id')
            ->selectRaw('party_id, COUNT(*) as n, SUM(CASE WHEN price_paid > 0 THEN 1 ELSE 0 END) as paid, SUM(price_paid) as amount')
            ->get()->keyBy(fn ($r) => (string) $r->party_id);

        $bar = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->leftJoin('sales_groups', 'sales_groups.id', '=', 'sales.sales_group_id')
            ->where('sales.customer_id', $id)
            ->where('sales.status', 'completed')
            ->where('sale_payments.method', '!=', 'benefit')
            ->groupBy('sales_groups.party_id')
            ->selectRaw('sales_groups.party_id as party_id, SUM(sale_payments.amount) as spent, COUNT(DISTINCT sales.id) as sales')
            ->get()->keyBy(fn ($r) => (string) $r->party_id);

        $tokens = TokenTransaction::query()->active()->where('type', TokenTransaction::SOLD)->where('participant_id', $id)
            ->groupBy('party_id')
            ->selectRaw('party_id, SUM(tokens) as t, SUM(amount) as amount')
            ->get()->keyBy(fn ($r) => (string) $r->party_id);

        $keys = $entries->keys()->merge($bar->keys())->merge($tokens->keys())->unique()->values();
        $parties = Party::query()->whereIn('id', $keys->filter(fn ($k) => $k !== '')->map(fn ($k) => (int) $k)->all())
            ->get(['id', 'name', 'start_date'])->keyBy(fn ($p) => (string) $p->id);

        $rows = $keys->map(function ($k) use ($entries, $bar, $tokens, $parties) {
            $party = $parties->get($k);

            return (object) [
                'party_id' => $k === '' ? null : (int) $k,
                'party' => $k === '' ? 'Fără petrecere' : ($party?->name ?? 'Petrecere ștearsă'),
                'date' => $party?->start_date,
                'entries' => (int) ($entries->get($k)->n ?? 0),
                'entries_amount' => round((float) ($entries->get($k)->amount ?? 0), 2),
                'bar_spent' => round((float) ($bar->get($k)->spent ?? 0), 2),
                'bar_sales' => (int) ($bar->get($k)->sales ?? 0),
                'tokens' => (int) ($tokens->get($k)->t ?? 0),
                'tokens_amount' => round((float) ($tokens->get($k)->amount ?? 0), 2),
            ];
        })->sortByDesc(fn ($r) => $r->date?->timestamp ?? PHP_INT_MIN)->values();

        return (object) [
            'entries' => (int) $rows->sum('entries'),
            'entries_amount' => round((float) $rows->sum('entries_amount'), 2),
            'entries_paid' => (int) $entries->sum('paid'),
            'bar_spent' => round((float) $rows->sum('bar_spent'), 2),
            'bar_sales' => (int) $rows->sum('bar_sales'),
            'tokens' => (int) $rows->sum('tokens'),
            'tokens_amount' => round((float) $rows->sum('tokens_amount'), 2),
            'parties' => $rows,
        ];
    }
}
