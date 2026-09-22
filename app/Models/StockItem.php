<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produs de stoc (materie prima/ingredient), independent de meniul de vanzare
 * (menu_items). Un stock_item poate fi vandut ca atare printr-o reteta cu o
 * singura linie (ex. bere la sticla) sau consumat in cocktailuri prin retete
 * cu mai multe linii (ex. vodca+suc+sirop pt. un cocktail).
 *
 * `stock_qty` si `avg_cost` sunt CACHE, recalculate la fiecare miscare din
 * stock_movements (vezi recordInitialStock/recordEntry/recordExit/recordCostAdjustment) -
 * nu se editeaza direct din formular.
 */
class StockItem extends Model
{
    protected $fillable = [
        'name',
        'unit',
        'stock_qty',
        'avg_cost',
        'min_stock',
        'package_label',
        'package_qty',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:3',
            'avg_cost' => 'decimal:4',
            'min_stock' => 'decimal:3',
            'package_qty' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(MenuItemRecipe::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(StockRequisitionItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /** Stoc curent la sau sub pragul de alerta configurat. Fara min_stock setat = fara alerta. */
    public function isBelowMinStock(): bool
    {
        return $this->min_stock !== null && (float) $this->stock_qty <= (float) $this->min_stock;
    }

    /**
     * Cantitatea sugerata la generarea unui necesar pentru un produs aflat la/sub prag:
     * chiar PRAGUL MINIM, nu doar cat lipseste pana la el. Dupa aprovizionare stocul
     * urca peste prag cu o marja egala cu pragul, deci o petrecere poate consuma din
     * el fara sa coboare iar sub prag. Ex.: prag 3, stoc 2 -> se cere 3 (ajungi la 5),
     * nu 1 (ai fi ramas fix la prag). 0 daca produsul nu are prag setat.
     */
    public function suggestedRequisitionQty(): float
    {
        if ($this->min_stock === null) {
            return 0.0;
        }

        return max(0.0, (float) $this->min_stock);
    }

    // ------------------------------------------------------------------
    // Ambalaj de referinta (informativ) - vezi migrarea 2024_06_28. Nu
    // schimba unitatea de baza si nu urmareste sticle individuale, doar
    // imparte cantitatea totala la marimea ambalajului declarat, pt. o
    // evidenta intuitiva in "sticle" pe langa ml/l exacti.
    // ------------------------------------------------------------------

    public function hasPackage(): bool
    {
        return $this->package_label !== null && $this->package_qty !== null && (float) $this->package_qty > 0;
    }

    /** Cate ambalaje (sticle) echivalente are stocul curent. Null daca nu are ambalaj definit. */
    public function packageCount(): ?float
    {
        if (! $this->hasPackage()) {
            return null;
        }

        return round((float) $this->stock_qty / (float) $this->package_qty, 2);
    }

    /** Text gata de afisat, ex. "≈ 2,43 sticle de 700ml". Null daca nu are ambalaj definit. */
    public function packageDisplay(): ?string
    {
        return $this->packageDisplayFor((float) $this->stock_qty);
    }

    /**
     * DXA: adaugat (Bar - necesare) — la fel ca packageDisplay(), dar pentru o
     * cantitate oarecare (ex. cat cer intr-un necesar, cat intra intr-o
     * raportare), nu doar pentru stocul curent. Null daca nu are ambalaj definit.
     */
    public function packageDisplayFor(float $qty): ?string
    {
        if (! $this->hasPackage()) {
            return null;
        }

        $count = number_format(round($qty / (float) $this->package_qty, 2), 2, ',', '.');

        return '≈ '.$count.' '.$this->package_label;
    }

    /** La fel ca packageDisplay(), dar pentru pragul de alertă min_stock. Null daca nu are ambalaj definit sau min_stock. */
    public function minStockPackageDisplay(): ?string
    {
        if (! $this->hasPackage() || $this->min_stock === null) {
            return null;
        }

        $count = number_format((float) $this->min_stock / (float) $this->package_qty, 2, ',', '.');

        return '≈ '.$count.' '.$this->package_label;
    }

    /**
     * Costul e cunoscut doar dupa o miscare cu pret (stoc initial cu cost, intrare,
     * sau corectie manuala) - pana atunci ramane null si orice reteta care il
     * foloseste nu poate fi valorizata (vezi MenuItem::isAvailable()).
     */
    public function hasKnownCost(): bool
    {
        return $this->avg_cost !== null;
    }

    /**
     * Valoarea curenta a stocului (stoc x cost mediu). Null daca nu are cost cunoscut.
     */
    public function stockValue(): ?float
    {
        if (! $this->hasKnownCost()) {
            return null;
        }

        return round((float) $this->stock_qty * (float) $this->avg_cost, 2);
    }

    // ------------------------------------------------------------------
    // Miscari de stoc - singurul loc care scrie in stock_movements si
    // actualizeaza cache-ul stock_qty/avg_cost. Nu manipula aceste coloane
    // direct din alta parte a aplicatiei.
    // ------------------------------------------------------------------

    /**
     * Stoc initial, o singura data, la crearea produsului de stoc daca exista
     * deja cantitate fizica. Costul e optional (poate ramane necunoscut pana
     * la o corectie manuala sau prima intrare reala).
     */
    public function recordInitialStock(float $qty, ?float $unitCost, ?int $adminId, ?string $note = null): StockMovement
    {
        $movement = $this->movements()->create([
            'type' => 'initial',
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
            'note' => $note,
            'created_by' => $adminId,
        ]);

        $this->applyIncrease($qty, $unitCost);

        return $movement;
    }

    /** Intrare (aprovizionare) prin raportare. Recalculeaza CMP. */
    public function recordEntry(float $qty, float $unitCost, ?int $reportId, ?int $adminId, ?string $note = null): StockMovement
    {
        $movement = $this->movements()->create([
            'report_id' => $reportId,
            'type' => 'in',
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => round($qty * $unitCost, 2),
            'note' => $note,
            'created_by' => $adminId,
        ]);

        $this->applyIncrease($qty, $unitCost);

        return $movement;
    }

    /**
     * Iesire (vanzare prin reteta sau consum/pierdere manuala) la costul mediu
     * curent - ieșirile NU modifica avg_cost, doar scad cantitatea.
     *
     * $allowNegative controleaza blocajul soft: implicit false -> arunca
     * StockInsufficientException daca stocul ar deveni negativ, ca apelantul
     * (Livewire) sa poata afisa avertismentul si sa ceara confirmare explicita
     * inainte de a re-apela cu true.
     */
    public function recordExit(float $qty, ?int $reportId, ?int $saleId, ?int $adminId, ?string $note = null, bool $allowNegative = false): StockMovement
    {
        $unitCost = $this->avg_cost !== null ? (float) $this->avg_cost : null;

        if (! $allowNegative && (float) $this->stock_qty - $qty < 0) {
            throw new \App\Exceptions\StockInsufficientException($this, $qty);
        }

        $movement = $this->movements()->create([
            'report_id' => $reportId,
            'sale_id' => $saleId,
            'type' => 'out',
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
            'note' => $note,
            'created_by' => $adminId,
        ]);

        $this->stock_qty = (float) $this->stock_qty - $qty;
        $this->save();

        return $movement;
    }

    /**
     * Corectie manuala de CANTITATE, oricand disponibila (ex. ai gresit stocul
     * initial la creare, sau un inventar fizic descopera o diferenta). Setezi
     * direct valoarea corecta - nu adaugi/scazi manual delta. Nu schimba
     * avg_cost. Motivul e obligatoriu, pt. audit. Miscarea rezultata e tot
     * tip "adjustment" (ca la cost), dar cu qty = delta (poate fi negativ),
     * ca sa se vada in istoric ce s-a schimbat, nu doar cat a ajuns sa fie.
     */
    public function recordQuantityAdjustment(float $newQty, int $adminId, string $reason): StockMovement
    {
        $delta = $newQty - (float) $this->stock_qty;

        $movement = $this->movements()->create([
            'type' => 'adjustment',
            'qty' => $delta,
            'unit_cost' => null,
            'total_cost' => null,
            'note' => $reason,
            'created_by' => $adminId,
        ]);

        $this->stock_qty = $newQty;
        $this->save();

        return $movement;
    }

    /**
     * Corectie manuala de cost, oricand disponibila (ex. produs cu stoc initial
     * fara pret, sau alinierea cu o factura primita mai tarziu). Nu schimba
     * cantitatea - doar avg_cost. Motivul e obligatoriu, pt. audit.
     */
    public function recordCostAdjustment(float $newUnitCost, int $adminId, string $reason): StockMovement
    {
        $movement = $this->movements()->create([
            'type' => 'adjustment',
            'qty' => 0,
            'unit_cost' => $newUnitCost,
            'total_cost' => null,
            'note' => $reason,
            'created_by' => $adminId,
        ]);

        $this->avg_cost = $newUnitCost;
        $this->save();

        return $movement;
    }

    /** CMP: recalculeaza avg_cost ponderat cu stocul existent, apoi aduna cantitatea. */
    protected function applyIncrease(float $qty, ?float $unitCost): void
    {
        $currentQty = (float) $this->stock_qty;
        $currentAvg = $this->avg_cost !== null ? (float) $this->avg_cost : null;

        if ($unitCost !== null) {
            $existingValue = $currentAvg !== null ? $currentQty * $currentAvg : 0.0;
            $newValue = $existingValue + ($qty * $unitCost);
            $newQty = $currentQty + $qty;

            $this->avg_cost = $newQty > 0 ? round($newValue / $newQty, 4) : $unitCost;
        }
        // Daca unitCost e null (ex. stoc initial fara pret), avg_cost ramane
        // neschimbat (null daca era null) - cantitatea tot se aduna.

        $this->stock_qty = $currentQty + $qty;
        $this->save();
    }
}
