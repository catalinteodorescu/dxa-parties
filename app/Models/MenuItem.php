<?php

namespace App\Models;

use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_category_id',
        'name',
        'quantity',
        'price',
        'tokens',
        'image_path',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tokens' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * Pretul in tokeni. Daca scoala foloseste tokeni (Settings::get('uses_tokens')),
     * coloana `tokens` e sursa de adevar. Altfel, e doar un echivalent calculat
     * din pretul in lei si curs — util daca politica se schimba mai tarziu.
     */
    public function tokenPrice(): float
    {
        if ($this->tokens !== null) {
            return (float) $this->tokens;
        }

        $rate = (float) Settings::get('token_rate');

        return $rate > 0 ? round(((float) $this->price) / $rate, 1) : 0.0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Reteta acestui articol de meniu: ce stock_items consuma si in ce
     * cantitate la o unitate vanduta. Un articol "simplu" (ex. bere la
     * sticla, vanduta ca atare) tot are o reteta — o singura linie, qty 1.
     * Un articol fara nicio linie NU e urmarit la stoc (nu are echivalent
     * "track_stock" — absenta liniilor de reteta E semnalul).
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(MenuItemRecipe::class);
    }

    /** Are cel putin o linie de reteta => e legat de gestiunea de stoc. */
    public function isTracked(): bool
    {
        return $this->recipeLines->isNotEmpty();
    }

    /**
     * Costul unei unitati vandute, calculat din reteta (suma qty_reteta x
     * avg_cost al fiecarui ingredient). Null daca articolul nu e urmarit la
     * stoc, sau daca orice ingredient din reteta are inca cost necunoscut
     * (avg_cost null pe stock_item).
     */
    public function costPerUnit(): ?float
    {
        if (! $this->isTracked()) {
            return null;
        }

        $total = 0.0;

        foreach ($this->recipeLines as $line) {
            if (! $line->stockItem->hasKnownCost()) {
                return null;
            }

            $total += (float) $line->qty * (float) $line->stockItem->avg_cost;
        }

        return round($total, 4);
    }

    /**
     * Marja absoluta (lei) = pret de vanzare - cost din reteta. Null daca
     * costul e necunoscut (vezi costPerUnit()).
     */
    public function marginAmount(): ?float
    {
        $cost = $this->costPerUnit();

        if ($cost === null) {
            return null;
        }

        return round((float) $this->price - $cost, 2);
    }

    /**
     * Marja procentuala = marja absoluta / pret de vanzare * 100 (cat din
     * pretul platit de client ramane profit, dupa scaderea costului de
     * reteta). Null daca lipseste costul sau pretul e 0 (nu se poate imparti).
     */
    public function marginPercent(): ?float
    {
        $margin = $this->marginAmount();
        $price = (float) $this->price;

        if ($margin === null || $price <= 0) {
            return null;
        }

        return round($margin / $price * 100, 1);
    }

    /**
     * Disponibil spre vanzare: fie nu e urmarit la stoc (mereu disponibil),
     * fie toate ingredientele din reteta au cost cunoscut SI stoc suficient
     * pt. cel putin o unitate.
     */
    public function isAvailable(): bool
    {
        return $this->unavailabilityReason() === null;
    }

    /**
     * Motivul indisponibilitatii, pt. mesaj clar in UI ("Cost necunoscut
     * pentru: Sirop de mure" / "Stoc insuficient pentru: Vodcă"). Null daca
     * articolul e disponibil.
     */
    public function unavailabilityReason(): ?string
    {
        if (! $this->isTracked()) {
            return null;
        }

        $unknownCost = [];
        $outOfStock = [];

        foreach ($this->recipeLines as $line) {
            $stockItem = $line->stockItem;

            if (! $stockItem->hasKnownCost()) {
                $unknownCost[] = $stockItem->name;

                continue;
            }

            if ((float) $stockItem->stock_qty < (float) $line->qty) {
                $outOfStock[] = $stockItem->name;
            }
        }

        if ($unknownCost) {
            return 'Cost necunoscut pentru: '.implode(', ', $unknownCost);
        }

        if ($outOfStock) {
            return 'Stoc insuficient pentru: '.implode(', ', $outOfStock);
        }

        return null;
    }
}
