<?php

namespace App\Services;

use App\Models\SalesGroup;
use App\Support\PaymentMethods;

/**
 * DXA: adaugat (PWA Bar). Totalurile unei sesiuni de vânzări de la bar, din vânzările ei NEANULATE: număr de bonuri,
 * încasat (venit) și încasările pe metodă. Folosit de ecranul „Acasă” din aplicația de bar și de raportare.
 *
 * `methods` = [cheie => ['amount' => lei, 'tokens' => ?int]] în ordinea metodelor din Setări. `cash_sales` = partea de cash.
 */
class BarSummary
{
    public static function for(SalesGroup $group): object
    {
        $payments = $group->paymentTotals();   // method => [amount, tokens] (doar vânzări finalizate)

        $order = array_flip(PaymentMethods::all()->pluck('key')->all());
        uksort($payments, fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        $methods = [];
        foreach ($payments as $key => $p) {
            $methods[$key] = [
                'amount' => round((float) ($p['amount'] ?? 0), 2),
                'tokens' => $key === PaymentMethods::TOKEN ? (int) ($p['tokens'] ?? 0) : null,
            ];
        }

        return (object) [
            'sales_count' => $group->completedCount(),
            'cancelled_count' => $group->cancelledCount(),
            'revenue' => $group->revenue(),
            'methods' => $methods,
            'cash_sales' => round((float) ($methods[PaymentMethods::CASH]['amount'] ?? 0), 2),
        ];
    }
}
