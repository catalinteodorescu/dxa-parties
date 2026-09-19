<?php

namespace App\Models;

use App\Exceptions\StockInsufficientException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * O raportare = o sesiune (de obicei inainte/dupa o petrecere, nu neaparat
 * zilnic) in care se completeaza:
 *  - intrari  (aprovizionare pe stock_items, cu pret platit)
 *  - vanzari  (articole de meniu vandute, la pretul fix al produsului -
 *              se "explodeaza" automat prin reteta in consum de stock_items)
 *  - pierderi (consum/pierdere manuala pe stock_items, fara venit asociat)
 *
 * Fiecare metoda de mai jos scrie prin StockItem (singurul loc care tine
 * cache-ul stock_qty/avg_cost sincronizat) si e infasurata intr-o tranzactie.
 */
class StockReport extends Model
{
    protected $fillable = [
        'date',
        'requisition_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'requisition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'report_id')->where('type', 'in');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(StockReportSale::class, 'report_id');
    }

    /** Iesirile fara sale_id = pierderi/consum manual (nu vanzari de meniu). */
    public function losses(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'report_id')
            ->where('type', 'out')
            ->whereNull('sale_id');
    }

    // ------------------------------------------------------------------

    /**
     * Inregistreaza o intrare (aprovizionare). Daca raportarea e legata de un
     * necesar (requisition_id), actualizeaza automat qty_received pe linia
     * corespunzatoare produsului (daca exista) si recalculeaza statusul
     * necesarului (open → fulfilled cand toate liniile sunt acoperite).
     */
    public function addEntry(StockItem $stockItem, float $qty, float $unitCost, ?int $adminId, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($stockItem, $qty, $unitCost, $adminId, $note) {
            $movement = $stockItem->recordEntry($qty, $unitCost, $this->id, $adminId, $note);

            if ($this->requisition_id) {
                $reqItem = StockRequisitionItem::where('stock_requisition_id', $this->requisition_id)
                    ->where('stock_item_id', $stockItem->id)
                    ->first();

                if ($reqItem) {
                    $reqItem->qty_received = (float) $reqItem->qty_received + $qty;
                    $reqItem->save();
                    $this->requisition->refreshStatus();
                }
            }

            return $movement;
        });
    }

    /**
     * Inregistreaza vanzarea unui articol de meniu: creeaza linia de venit
     * (stock_report_sales, cu pretul fix al produsului si costul snapshot
     * calculat din reteta) si "explodeaza" reteta in cate o iesire (out) pt.
     * fiecare stock_item consumat, legata de aceasta vanzare prin sale_id.
     *
     * $allowNegative se propaga la fiecare iesire din reteta - daca vreun
     * ingredient nu are stoc suficient si $allowNegative e false, arunca
     * StockInsufficientException (blocaj soft: Livewire prinde si cere
     * confirmare, apoi re-apeleaza cu allowNegative = true).
     */
    public function addSale(MenuItem $menuItem, float $qty, ?int $adminId, bool $allowNegative = false): StockReportSale
    {
        return DB::transaction(function () use ($menuItem, $qty, $adminId, $allowNegative) {
            $unitCost = $menuItem->costPerUnit();

            $sale = $this->sales()->create([
                'menu_item_id' => $menuItem->id,
                'qty' => $qty,
                'unit_price' => $menuItem->price,
                'total_price' => round($qty * (float) $menuItem->price, 2),
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
            ]);

            foreach ($menuItem->recipeLines as $line) {
                $line->stockItem->recordExit(
                    qty: $qty * (float) $line->qty,
                    reportId: $this->id,
                    saleId: $sale->id,
                    adminId: $adminId,
                    allowNegative: $allowNegative,
                );
            }

            return $sale;
        });
    }

    /** Consum/pierdere manuala pe un stock_item (fara venit) — ex. stricaciune, testare reteta. */
    public function addLoss(StockItem $stockItem, float $qty, ?int $adminId, string $reason, bool $allowNegative = false): StockMovement
    {
        return $stockItem->recordExit(
            qty: $qty,
            reportId: $this->id,
            saleId: null,
            adminId: $adminId,
            note: $reason,
            allowNegative: $allowNegative,
        );
    }

    public function totalRevenue(): float
    {
        return round((float) $this->sales()->sum('total_price'), 2);
    }

    /** Costul total al raportarii = costul vanzarilor + costul pierderilor (cand e cunoscut). */
    public function totalCost(): float
    {
        $salesCost = (float) $this->sales()->sum('total_cost');
        $lossesCost = (float) $this->losses()->sum('total_cost');

        return round($salesCost + $lossesCost, 2);
    }

    public function totalProfit(): float
    {
        return round($this->totalRevenue() - $this->totalCost(), 2);
    }
}
