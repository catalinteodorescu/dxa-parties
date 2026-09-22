<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * O raportare = o sesiune (de obicei inainte/dupa o petrecere, nu neaparat
 * zilnic) in care se completeaza:
 *  - intrari  (aprovizionare pe stock_items, cu pret platit - optional, vezi
 *              addEntry())
 *  - vanzari  (articole de meniu vandute, la pretul fix al produsului -
 *              se "explodeaza" automat prin reteta in consum de stock_items)
 *  - pierderi (consum/pierdere manuala pe stock_items, fara venit asociat)
 *
 * Status 'draft': NICIO scriere reala - liniile stau in stock_report_lines
 * (staging), editabile liber. Status 'finalized': miscarile reale exista
 * deja (create de finalize()), totul e blocat - fara editare/stergere.
 *
 * Fiecare metoda addEntry/addSale/addLoss scrie prin StockItem (singurul loc
 * care tine cache-ul stock_qty/avg_cost sincronizat) si e infasurata intr-o
 * tranzactie. In noul flux sunt apelate DOAR din finalize(), niciodata la
 * salvarea unei linii de draft.
 */
class StockReport extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'finalized' => 'Finalizat',
    ];

    protected $fillable = [
        'date',
        'status',
        'finalized_at',
        'finalized_by',
        'party_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'finalized_by');
    }

    /** Linii de staging (draft) - vezi StockReportLine. Golite la finalizare. */
    public function lines(): HasMany
    {
        return $this->hasMany(StockReportLine::class, 'report_id');
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

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    // ------------------------------------------------------------------

    /**
     * Inregistreaza o intrare (aprovizionare). Apelata DOAR din finalize(),
     * o data pt. fiecare linie 'entry' din staging.
     *
     * $unitCost e nullable - daca nu se stie pretul de achizitie la aceasta
     * intrare, CMP-ul produsului ramane neschimbat (doar cantitatea creste),
     * vezi StockItem::applyIncrease().
     *
     * Daca $requisitionItem e dat (intrarea a fost adusa dintr-un necesar
     * deschis), creste qty_received pe acea linie si recalculeaza statusul
     * necesarului (open → fulfilled cand toate liniile sunt acoperite).
     */
    public function addEntry(StockItem $stockItem, float $qty, ?float $unitCost, ?int $adminId, ?string $note = null, ?StockRequisitionItem $requisitionItem = null): StockMovement
    {
        return DB::transaction(function () use ($stockItem, $qty, $unitCost, $adminId, $note, $requisitionItem) {
            $movement = $stockItem->recordEntry($qty, $unitCost, $this->id, $adminId, $note, $requisitionItem?->id);

            if ($requisitionItem) {
                $requisitionItem->qty_received = (float) $requisitionItem->qty_received + $qty;
                $requisitionItem->save();
                $requisitionItem->requisition->refreshStatus();
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
     * $allowNegative se propaga la fiecare iesire din reteta - in noul flux
     * de Raportare e mereu true la finalizare (avertizam vizual dinainte in
     * formular, fara sa blocam la finalizare).
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

    /**
     * Finalizare: itereaza liniile din staging (in ordinea creata), creeaza
     * miscarile reale (addEntry/addSale/addLoss, cu allowNegative: true la
     * iesiri), marcheaza raportul finalized si goleste stock_report_lines.
     * Totul intr-o singura tranzactie - ireversibil.
     */
    public function finalize(int $adminId): void
    {
        DB::transaction(function () use ($adminId) {
            $lines = $this->lines()->with(['stockItem', 'menuItem', 'requisitionItem.requisition'])
                ->orderBy('id')
                ->get();

            foreach ($lines as $line) {
                match ($line->kind) {
                    'entry' => $this->addEntry(
                        $line->stockItem,
                        (float) $line->qty,
                        $line->unit_cost !== null ? (float) $line->unit_cost : null,
                        $adminId,
                        requisitionItem: $line->requisitionItem,
                    ),
                    'sale' => $this->addSale($line->menuItem, (float) $line->qty, $adminId, allowNegative: true),
                    'loss' => $this->addLoss($line->stockItem, (float) $line->qty, $adminId, (string) $line->note, allowNegative: true),
                };
            }

            $this->lines()->delete();

            $this->status = 'finalized';
            $this->finalized_at = now();
            $this->finalized_by = $adminId;
            $this->save();
        });
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
