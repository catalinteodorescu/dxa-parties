<?php

namespace App\Services;

use App\Models\PartyEntry;
use App\Models\PartyEntryPayment;
use App\Models\ReceptionSession;
use App\Models\TokenTransaction;
use App\Models\TokenTransactionPayment;
use App\Support\PaymentMethods;

/**
 * DXA: adaugat (Recepție - raportări). Totalurile unei sesiuni de recepție, calculate din înregistrările ei NEANULATE:
 * intrări (pe tip de bilet), vânzări de tokeni și încasările pe metodă (intrări + tokeni). Folosit de draftul
 * raportării (live) și la finalizare (de unde se îngheață în snapshot).
 *
 * `methods` = încasări pe metodă, [cheie => lei], în ordinea metodelor din Setări. „Beneficiu” nu apare (nu e plată).
 * `cash_entries` / `cash_tokens` = partea de cash din intrări, respectiv din vânzările de tokeni.
 */
class ReceptionSummary
{
    public static function for(ReceptionSession $session): object
    {
        $entries = PartyEntry::query()->where('reception_session_id', $session->id)
            ->get(['id', 'ticket_type', 'price_paid', 'cancelled_at']);
        $active = $entries->whereNull('cancelled_at');

        $tickets = $active->groupBy('ticket_type')->map(fn ($g, $name) => [
            'name' => (string) $name,
            'count' => $g->count(),
            'revenue' => round((float) $g->sum('price_paid'), 2),
        ])->values()->all();

        $sales = TokenTransaction::query()->where('reception_session_id', $session->id)
            ->where('type', TokenTransaction::SOLD)
            ->get(['id', 'tokens', 'amount', 'cancelled_at']);
        $activeSales = $sales->whereNull('cancelled_at');

        $entryPay = PartyEntryPayment::query()
            ->join('party_entries', 'party_entries.id', '=', 'party_entry_payments.party_entry_id')
            ->where('party_entries.reception_session_id', $session->id)
            ->whereNull('party_entries.cancelled_at')
            ->groupBy('party_entry_payments.method')
            ->selectRaw('party_entry_payments.method as method, SUM(party_entry_payments.amount) as amount')
            ->pluck('amount', 'method');

        $tokenPay = TokenTransactionPayment::query()
            ->join('token_transactions', 'token_transactions.id', '=', 'token_transaction_payments.token_transaction_id')
            ->where('token_transactions.reception_session_id', $session->id)
            ->where('token_transactions.type', TokenTransaction::SOLD)
            ->whereNull('token_transactions.cancelled_at')
            ->groupBy('token_transaction_payments.method')
            ->selectRaw('token_transaction_payments.method as method, SUM(token_transaction_payments.amount) as amount')
            ->pluck('amount', 'method');

        $methods = [];
        foreach ([$entryPay, $tokenPay] as $rows) {
            foreach ($rows as $method => $amount) {
                $methods[$method] = round(($methods[$method] ?? 0) + (float) $amount, 2);
            }
        }
        // Ordinea din Setări (cash, card, ...; custom la urmă).
        $order = array_flip(PaymentMethods::all()->pluck('key')->all());
        uksort($methods, fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return (object) [
            'entries_count' => $active->count(),
            'entries_free' => $active->filter(fn ($e) => (float) $e->price_paid <= 0)->count(),
            'entries_revenue' => round((float) $active->sum('price_paid'), 2),
            'entries_cancelled' => $entries->count() - $active->count(),
            'tickets' => $tickets,
            'tokens_sold' => (int) $activeSales->sum('tokens'),
            'tokens_amount' => round((float) $activeSales->sum('amount'), 2),
            'token_sales' => $activeSales->count(),
            'token_sales_cancelled' => $sales->count() - $activeSales->count(),
            'methods' => $methods,
            'cash_entries' => round((float) ($entryPay[PaymentMethods::CASH] ?? 0), 2),
            'cash_tokens' => round((float) ($tokenPay[PaymentMethods::CASH] ?? 0), 2),
        ];
    }
}
