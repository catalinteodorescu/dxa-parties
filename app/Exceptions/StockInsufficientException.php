<?php

namespace App\Exceptions;

use App\Models\StockItem;
use Exception;

/**
 * Aruncata de StockItem::recordExit() cand o iesire ar duce stocul sub 0 si
 * $allowNegative e false. Livewire prinde exceptia, afiseaza avertismentul cu
 * numele produsului/cantitatea disponibila si cere confirmare explicita
 * (re-apel cu $allowNegative = true) inainte de a continua — blocaj SOFT,
 * nu blocaj dur.
 */
class StockInsufficientException extends Exception
{
    public function __construct(
        public readonly StockItem $stockItem,
        public readonly float $requestedQty,
    ) {
        parent::__construct(sprintf(
            'Stoc insuficient pentru "%s": disponibil %s %s, se încearcă consum %s %s.',
            $stockItem->name,
            rtrim(rtrim(number_format((float) $stockItem->stock_qty, 3, '.', ''), '0'), '.'),
            $stockItem->unit,
            rtrim(rtrim(number_format($requestedQty, 3, '.', ''), '0'), '.'),
            $stockItem->unit,
        ));
    }
}
