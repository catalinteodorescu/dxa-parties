<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\SaleLine;
use App\Models\SalePayment;
use Illuminate\Support\Collection;

/**
 * Agregari peste un set de vanzari (dat ca lista de id-uri): pe produs, pe metoda de
 * plata. Folosit atat pentru sesiunile de vanzari, cat si pentru vanzarile simple
 * (fara sesiune), la previzualizarea din Raportare si la finalizarea ei.
 */
class SalesAggregator
{
    /**
     * Vanzarile FINALIZATE agregate pe produs:
     * [menu_item_id, menuItem, qty, revenue, cost|null]. cost = null daca vreo linie are cost necunoscut.
     */
    public static function lines(array $saleIds): Collection
    {
        return self::byProduct($saleIds);
    }

    private static function byProduct(array $saleIds): Collection
    {
        if ($saleIds === []) {
            return collect();
        }

        $rows = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sales.id', $saleIds)
            ->where('sales.status', 'completed')
            ->groupBy('sale_lines.menu_item_id')
            ->selectRaw('sale_lines.menu_item_id as menu_item_id, SUM(sale_lines.qty) as qty, SUM(sale_lines.total_price) as revenue, SUM(sale_lines.total_cost) as cost, SUM(CASE WHEN sale_lines.total_cost IS NULL THEN 1 ELSE 0 END) as unknown_cost')
            ->get();

        $menuItems = MenuItem::with('recipeLines.stockItem')
            ->whereIn('id', $rows->pluck('menu_item_id')->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($r) => (object) [
                'menu_item_id' => (int) $r->menu_item_id,
                'menuItem' => $menuItems->get((int) $r->menu_item_id),
                'qty' => (float) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
                'cost' => (int) $r->unknown_cost > 0 ? null : round((float) $r->cost, 2),
            ])
            ->filter(fn ($r) => $r->menuItem !== null)
            ->sortBy(fn ($r) => mb_strtolower($r->menuItem->name))
            ->values();
    }

    /** Totaluri pe metoda de plata (doar vanzari finalizate): method => [amount, tokens]. */
    public static function payments(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->whereIn('sales.id', $saleIds)
            ->where('sales.status', 'completed')
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, SUM(sale_payments.amount) as amount, SUM(sale_payments.tokens) as tokens')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->method => ['amount' => round((float) $r->amount, 2), 'tokens' => (int) $r->tokens]])
            ->all();
    }
}
