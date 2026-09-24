<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\SalesAggregator;
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
        'sales_group_id',
        'include_loose_sales',
        'opening_float',
        'counted_cash',
        'counted_tokens',
        'expected_cash',
        'expected_tokens',
        'stock_aligned',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finalized_at' => 'datetime',
            'include_loose_sales' => 'boolean',
            'opening_float' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_tokens' => 'integer',
            'expected_tokens' => 'integer',
            'stock_aligned' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** Grupul de vanzari (bar) ales in aceasta raportare; se inchide la finalizare. */
    public function salesGroup(): BelongsTo
    {
        return $this->belongsTo(SalesGroup::class, 'sales_group_id');
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

    /** Foaia de inventar a numaratorii de final de seara (o linie per produs de stoc numarat). */
    public function counts(): HasMany
    {
        return $this->hasMany(StockReportCount::class, 'report_id');
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

    /**
     * DXA: adaugat — numarul raportarii, ca la facturi: "22 / 23.09.2026" (id-ul din DB + data raportarii).
     * Id-ul e atribuit la prima salvare a draftului si nu se schimba; un draft sters lasa un gol in numerotare.
     */
    public function number(): string
    {
        return $this->id.' / '.$this->date->format('d.m.Y');
    }

    /** "Raportare nr. 22 / 23.09.2026" */
    public function title(): string
    {
        return 'Raportare nr. '.$this->number();
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
    public function addSale(MenuItem $menuItem, float $qty, ?int $adminId, bool $allowNegative = false, ?array $groupSnapshot = null): StockReportSale
    {
        return DB::transaction(function () use ($menuItem, $qty, $adminId, $allowNegative, $groupSnapshot) {
            if ($groupSnapshot !== null) {
                // Vanzare adusa din vanzarile aplicatiei (sesiune sau vanzari simple): pretul si costul sunt cele SNAPSHOT din
                // vanzarile grupului (agregate pe produs), nu cele curente din meniu.
                $totalPrice = round((float) $groupSnapshot['total_price'], 2);
                $totalCost = $groupSnapshot['total_cost'] !== null ? round((float) $groupSnapshot['total_cost'], 2) : null;

                $sale = $this->sales()->create([
                    'menu_item_id' => $menuItem->id,
                    'sales_group_id' => $groupSnapshot['sales_group_id'] ?? null,
                    'is_loose' => (bool) ($groupSnapshot['is_loose'] ?? false),
                    'qty' => $qty,
                    'unit_price' => $qty > 0 ? round($totalPrice / $qty, 2) : $menuItem->price,
                    'total_price' => $totalPrice,
                    'unit_cost' => ($totalCost !== null && $qty > 0) ? round($totalCost / $qty, 4) : null,
                    'total_cost' => $totalCost,
                ]);
            } else {
                $unitCost = $menuItem->costPerUnit();

                $sale = $this->sales()->create([
                    'menu_item_id' => $menuItem->id,
                    'qty' => $qty,
                    'unit_price' => $menuItem->price,
                    'total_price' => round($qty * (float) $menuItem->price, 2),
                    'unit_cost' => $unitCost,
                    'total_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
                ]);
            }

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

    /** Are raportarea vreo numaratoare (cash, tokeni sau inventar)? */
    public function hasClosingCount(): bool
    {
        return $this->counted_cash !== null
            || $this->counted_tokens !== null
            || $this->counts()->exists();
    }

    /**
     * Rezumatul numaratorii unei raportari FINALIZATE (sursa unica pentru ecran, PDF, lista si dashboard).
     * null = raportare nefinalizata sau fara numaratoare.
     *
     * Diferentele sunt numarat − asteptat: negativ = lipsa, pozitiv = surplus.
     *
     * @return object{cash_diff:?float, tokens_diff:?int, counted_items:int, diff_items:int, inventory_value:float, inventory_cost_unknown:bool, clean:bool}|null
     */
    public function closingSummary(): ?object
    {
        if (! $this->isFinalized()) {
            return null;
        }

        $counts = $this->relationLoaded('counts') ? $this->counts : $this->counts()->get();

        if ($this->counted_cash === null && $this->counted_tokens === null && $counts->isEmpty()) {
            return null;
        }

        $cashDiff = ($this->counted_cash !== null && $this->expected_cash !== null)
            ? round((float) $this->counted_cash - (float) $this->expected_cash, 2)
            : null;

        $tokensDiff = ($this->counted_tokens !== null && $this->expected_tokens !== null)
            ? (int) $this->counted_tokens - (int) $this->expected_tokens
            : null;

        $withDiff = $counts->filter(fn (StockReportCount $c) => $c->hasDifference());

        return (object) [
            'cash_diff' => $cashDiff,
            'tokens_diff' => $tokensDiff,
            'counted_items' => $counts->count(),
            'diff_items' => $withDiff->count(),
            'inventory_value' => round((float) $withDiff->sum(fn (StockReportCount $c) => $c->diffValue() ?? 0.0), 2),
            'inventory_cost_unknown' => $withDiff->contains(fn (StockReportCount $c) => $c->diffValue() === null),
            'clean' => ($cashDiff === null || $cashDiff == 0.0)
                && ($tokensDiff === null || $tokensDiff === 0)
                && $withDiff->isEmpty(),
        ];
    }

    /**
     * Finalizare: itereaza liniile din staging (in ordinea creata), creeaza
     * miscarile reale (addEntry/addSale/addLoss, cu allowNegative: true la
     * iesiri), marcheaza raportul finalized si goleste stock_report_lines.
     * Totul intr-o singura tranzactie - ireversibil.
     */
    public function finalize(int $adminId, bool $alignStock = false): void
    {
        DB::transaction(function () use ($adminId, $alignStock) {
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

            // Vanzarile inregistrate in aplicatie / admin se posteaza ca vanzari ale raportarii,
            // agregate pe produs, cu pretul si costul de la momentul vanzarii, si primesc
            // report_id (deci nu se mai pot anula si nu mai apar ca neraportate).
            //  - sesiunea de vanzari aleasa: se inchide in aceeasi tranzactie, deci nicio
            //    vanzare noua nu mai poate intra in ea (vezi SaleRecorder);
            //  - vanzarile simple (fara sesiune), daca e bifata includerea lor.
            if ($this->sales_group_id) {
                $group = SalesGroup::query()->lockForUpdate()->find($this->sales_group_id);

                if ($group && $group->isOpen()) {
                    $saleIds = $group->saleIds();

                    $this->postRegisteredSales($saleIds, $adminId, ['sales_group_id' => $group->id, 'is_loose' => false]);
                    $group->close();
                }
            }

            if ($this->include_loose_sales) {
                $saleIds = static::looseSalesQuery()->lockForUpdate()->pluck('id')->all();

                $this->postRegisteredSales($saleIds, $adminId, ['sales_group_id' => null, 'is_loose' => true]);
            }

            // Numaratoarea de final de seara (cash / tokeni / inventar): snapshot al valorilor
            // asteptate + (optional) alinierea stocului la cantitatile numarate.
            $this->finalizeClosingCount($adminId, $alignStock);

            $this->lines()->delete();

            $this->status = 'finalized';
            $this->finalized_at = now();
            $this->finalized_by = $adminId;
            $this->save();
        });
    }

    /**
     * Numaratoarea de final de seara, la finalizare (in tranzactia din finalize(), DUPA ce miscarile
     * si vanzarile raportarii au fost postate):
     *  - cash/tokeni: valoarea asteptata = fondul de casa + incasarile din vanzarile postate de aceasta
     *    raportare (respectiv tokenii incasati); se salveaza doar pentru ce a fost numarat;
     *  - inventar: asteptat = stocul teoretic REAL de acum (dupa postare), nu o estimare din formular.
     *    Daca $alignStock: lipsa se posteaza ca pierdere ("Diferenta la numaratoare", intra in cost si
     *    profit), surplusul ca ajustare de cantitate.
     */
    private function finalizeClosingCount(int $adminId, bool $alignStock): void
    {
        if ($this->counted_cash !== null || $this->counted_tokens !== null) {
            $payments = SalesAggregator::payments(
                Sale::query()->where('report_id', $this->id)->pluck('id')->all()
            );

            if ($this->counted_cash !== null) {
                $this->expected_cash = round((float) $this->opening_float + (float) ($payments['cash']['amount'] ?? 0), 2);
            }

            if ($this->counted_tokens !== null) {
                $this->expected_tokens = (int) ($payments['token']['tokens'] ?? 0);
            }
        }

        $counts = $this->counts()->get();

        foreach ($counts as $count) {
            $item = StockItem::query()->find($count->stock_item_id);

            if (! $item) {
                continue;
            }

            $expected = (float) $item->stock_qty;
            $counted = (float) $count->counted_qty;
            $diff = round($counted - $expected, 3);

            $count->expected_qty = $expected;
            $count->diff_qty = $diff;
            $count->unit_cost = $item->avg_cost;
            $count->save();

            if (! $alignStock || abs($diff) < StockReportCount::EPSILON) {
                continue;
            }

            if ($diff < 0) {
                $item->recordExit(
                    qty: abs($diff),
                    reportId: $this->id,
                    saleId: null,
                    adminId: $adminId,
                    note: 'Diferență la numărătoare',
                    allowNegative: true,
                );
            } else {
                $item->recordQuantityAdjustment($counted, $adminId, 'Diferență la numărătoare', $this->id);
            }
        }

        $this->stock_aligned = $alignStock && $counts->isNotEmpty();
    }

    /** Vanzarile simple: fara sesiune si inca neraportate (finalizate sau anulate). */
    public static function looseSalesQuery()
    {
        return Sale::query()->whereNull('sales_group_id')->whereNull('report_id');
    }

    /** Sunt vanzari inregistrate (in sesiunea aleasa / cele simple bifate) de adus in aceasta raportare? */
    public function hasSalesToPost(): bool
    {
        if ($this->sales_group_id && Sale::query()->where('sales_group_id', $this->sales_group_id)->exists()) {
            return true;
        }

        return $this->include_loose_sales && static::looseSalesQuery()->where('status', 'completed')->exists();
    }

    /**
     * Posteaza vanzarile date (id-uri): cele finalizate, agregate pe produs, devin vanzari ale
     * raportarii (cu consum de stoc prin reteta); TOATE (si cele anulate) primesc report_id.
     */
    private function postRegisteredSales(array $saleIds, int $adminId, array $origin): void
    {
        if ($saleIds === []) {
            return;
        }

        foreach (SalesAggregator::lines($saleIds) as $agg) {
            $this->addSale(
                $agg->menuItem,
                $agg->qty,
                $adminId,
                allowNegative: true,
                groupSnapshot: $origin + [
                    'total_price' => $agg->revenue,
                    'total_cost' => $agg->cost,
                ],
            );
        }

        Sale::query()->whereIn('id', $saleIds)->update(['report_id' => $this->id]);
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
