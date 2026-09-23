<?php

namespace App\Livewire\Admin\MenuItems;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\StockItem; // DXA: adaugat (Bar - stocuri: reteta)
use App\Services\ActivityLogger;
use App\Support\HandlesImageUploads;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use HandlesImageUploads;
    use WithFileUploads;

    public ?MenuItem $menuItem = null;

    // Campuri
    public string $name = '';
    public ?int $menu_category_id = null;
    public ?string $quantity = null;
    public ?string $price = null;
    public ?string $tokens = null;
    public ?string $description = null;
    public bool $is_active = true;

    // Imagine
    public $image = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    // Adaugare rapida categorie (popup langa select)
    public bool $newCategoryModalOpen = false;
    public string $newCategoryName = '';

    // DXA: adaugat (Bar - stocuri: reteta)
    // Reteta: [['stock_item_id' => int|null, 'qty' => string], ...]. Ramane
    // mereu un rand gol la coada pt. adaugare rapida — vezi syncRecipeRows().
    public array $recipe = [];

    // Adaugare rapida stock_item (popup langa selectul din reteta) - name+unit
    // + stoc initial optional (cant. + cost, cu acelasi toggle unitar/total ca
    // la Bar → Stocuri). min_stock ramane de completat ulterior, din Stocuri
    // (acelasi principiu ca la popup-ul de categorie: rapid, minimal).
    public bool $newStockItemModalOpen = false;

    // Vezi explicatia din Stocks\Index::$formNonce - forteaza resetarea
    // calculatoarelor Alpine la fiecare deschidere a popup-ului.
    public int $newStockItemNonce = 0;

    public ?int $newStockItemTargetIndex = null;

    public string $newStockItemName = '';

    public string $newStockItemUnit = 'buc';

    // DXA: adaugat — la fel ca la ecranul principal Bar → Stocuri: poti
    // introduce direct cantitatea + costul pe care il ai deja, ca sa nu
    // trebuiasca sa parasesti formularul de meniu si sa revii aici mai tarziu.
    public bool $newStockItemHasInitialStock = false;

    public string $newStockItemInitialQty = '';

    public string $newStockItemInitialCost = '';

    public string $newStockItemInitialCostMode = 'unit'; // unit | total

    // Acelasi principiu ca la Bar → Stocuri: ambalaj de referinta, pur informativ.
    public string $newStockItemPackageLabel = '';

    public string $newStockItemPackageQty = '';

    public function mount(?MenuItem $menuItem = null): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        if ($menuItem && $menuItem->exists) {
            $this->menuItem = $menuItem;

            $this->name = $menuItem->name;
            $this->menu_category_id = $menuItem->menu_category_id;
            $this->quantity = $menuItem->quantity;
            $this->price = (string) $menuItem->price;
            $this->tokens = $menuItem->tokens !== null ? (string) $menuItem->tokens : null;
            $this->description = $menuItem->description;
            $this->is_active = $menuItem->is_active;
            $this->existingImage = $menuItem->image_path;

            // DXA: adaugat (Bar - stocuri: reteta)
            $this->recipe = $menuItem->recipeLines()->with('stockItem')->get()
                ->map(fn ($line) => ['stock_item_id' => $line->stock_item_id, 'qty' => (string) $line->qty])
                ->all();
        }

        $this->syncRecipeRows();
    }

    /** Pastreaza mereu un rand gol la coada retetei, pt. adaugare rapida. */
    private function syncRecipeRows(): void
    {
        $last = end($this->recipe);

        if ($last === false || ! empty($last['stock_item_id'])) {
            $this->recipe[] = ['stock_item_id' => null, 'qty' => ''];
        }
    }

    public function usesTokens(): bool
    {
        return (bool) Settings::get('uses_tokens');
    }

    // DXA: adaugat (Bar - stocuri: reteta)
    public function updated($name): void
    {
        if (preg_match('/^recipe\.\d+\.stock_item_id$/', $name)) {
            $this->syncRecipeRows();
        }
    }

    public function removeRecipeLine(int $i): void
    {
        unset($this->recipe[$i]);
        $this->recipe = array_values($this->recipe);
        $this->syncRecipeRows();
    }

    public function updatedTokens($value): void
    {
        if (! $this->usesTokens()) {
            return;
        }

        $rate = (float) Settings::get('token_rate');
        $this->price = is_numeric($value) ? number_format(((float) $value) * $rate, 2, '.', '') : null;
    }

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'menu_category_id' => ['required', 'exists:menu_categories,id'],
            'quantity' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:8192'],
        ];

        if ($this->usesTokens()) {
            $rules['tokens'] = ['required', 'integer', 'min:0', 'max:99999'];
            $rules['price'] = ['nullable', 'numeric', 'min:0'];
        } else {
            $rules['price'] = ['required', 'numeric', 'min:0', 'max:99999.99'];
            $rules['tokens'] = ['nullable'];
        }

        // DXA: adaugat (Bar - stocuri: reteta) — randurile complet goale (randul
        // de coada pt. adaugare rapida) sunt permise; daca ai ales un ingredient,
        // cantitatea devine obligatorie si invers.
        $rules['recipe.*.stock_item_id'] = ['nullable', 'required_with:recipe.*.qty', 'exists:stock_items,id'];
        $rules['recipe.*.qty'] = ['nullable', 'required_with:recipe.*.stock_item_id', 'numeric', 'min:0.001'];

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Numele produsului este obligatoriu.',
            'menu_category_id.required' => 'Alege o categorie.',
            'menu_category_id.exists' => 'Categoria aleasă nu mai există.',
            'price.required' => 'Prețul este obligatoriu.',
            'price.numeric' => 'Prețul trebuie să fie un număr.',
            'tokens.required' => 'Prețul în tokeni este obligatoriu.',
            'tokens.integer' => 'Prețul în tokeni trebuie să fie un număr întreg.',
            'image.image' => 'Fișierul trebuie să fie o imagine.',
            'image.max' => 'Imaginea nu poate depăși 8 MB.',
            'recipe.*.stock_item_id.required_with' => 'Alege ingredientul.',
            'recipe.*.qty.required_with' => 'Completează cantitatea.',
            'recipe.*.qty.numeric' => 'Cantitatea trebuie să fie un număr.',
        ];
    }

    public function clearImage(): void
    {
        $this->reset('image');
        $this->removeImage = true;
    }

    public function openNewCategoryModal(): void
    {
        $this->newCategoryName = '';
        $this->resetValidation('newCategoryName');
        $this->newCategoryModalOpen = true;
    }

    public function closeNewCategoryModal(): void
    {
        $this->newCategoryModalOpen = false;
    }

    public function saveNewCategory(): void
    {
        $this->validate([
            'newCategoryName' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $exists = MenuCategory::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->exists();
                    if ($exists) {
                        $fail('Există deja o categorie cu acest nume.');
                    }
                },
            ],
        ], [
            'newCategoryName.required' => 'Numele categoriei este obligatoriu.',
        ]);

        $nextOrder = (int) (MenuCategory::max('sort_order') ?? 0) + 1;

        $category = MenuCategory::create([
            'name' => $this->newCategoryName,
            'sort_order' => $nextOrder,
            'is_active' => true,
        ]);

        ActivityLogger::log('menu.category_created', 'A adăugat categoria de meniu „'.$category->name.'" (din formularul de produs).');

        $this->menu_category_id = $category->id;
        $this->newCategoryModalOpen = false;
    }

    // DXA: adaugat (Bar - stocuri: reteta)
    public function openNewStockItemModal(int $targetIndex): void
    {
        $this->newStockItemTargetIndex = $targetIndex;
        $this->newStockItemName = '';
        $this->newStockItemUnit = 'buc';
        $this->newStockItemHasInitialStock = false;
        $this->newStockItemInitialQty = '';
        $this->newStockItemInitialCost = '';
        $this->newStockItemInitialCostMode = 'unit';
        $this->newStockItemPackageLabel = '';
        $this->newStockItemPackageQty = '';
        $this->newStockItemNonce++;
        $this->resetValidation();
        $this->newStockItemModalOpen = true;
    }

    public function closeNewStockItemModal(): void
    {
        $this->newStockItemModalOpen = false;
    }

    public function saveNewStockItem(): void
    {
        $rules = [
            'newStockItemName' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $exists = StockItem::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->exists();
                    if ($exists) {
                        $fail('Există deja un produs de stoc cu acest nume.');
                    }
                },
            ],
            'newStockItemUnit' => ['required', 'string', 'in:buc,ml,l,kg,g'],
            'newStockItemInitialQty' => [$this->newStockItemHasInitialStock ? 'required' : 'nullable', 'numeric', 'min:0.001'],
            'newStockItemInitialCost' => ['nullable', 'numeric', 'min:0'],
            'newStockItemPackageLabel' => ['nullable', 'string', 'max:60', 'required_with:newStockItemPackageQty'],
            'newStockItemPackageQty' => ['nullable', 'numeric', 'min:0.001', 'required_with:newStockItemPackageLabel'],
        ];

        $this->validate($rules, [
            'newStockItemName.required' => 'Numele produsului de stoc este obligatoriu.',
            'newStockItemInitialQty.required' => 'Completează cantitatea inițială.',
            'newStockItemPackageLabel.required_with' => 'Completează și denumirea ambalajului.',
            'newStockItemPackageQty.required_with' => 'Completează și cantitatea per ambalaj.',
        ]);

        $adminId = Auth::guard('admin')->id();

        $stockItem = StockItem::create([
            'name' => $this->newStockItemName,
            'unit' => $this->newStockItemUnit,
            'package_label' => $this->newStockItemPackageLabel !== '' ? $this->newStockItemPackageLabel : null,
            'package_qty' => $this->newStockItemPackageQty !== '' ? (float) $this->newStockItemPackageQty : null,
            'is_active' => true,
            'created_by' => $adminId,
        ]);

        if ($this->newStockItemHasInitialStock && $this->newStockItemInitialQty !== '') {
            $qty = (float) $this->newStockItemInitialQty;
            $unitCost = null;

            if ($this->newStockItemInitialCost !== '') {
                $unitCost = $this->newStockItemInitialCostMode === 'total'
                    ? round(((float) $this->newStockItemInitialCost) / $qty, 4)
                    : (float) $this->newStockItemInitialCost;
            }

            $stockItem->recordInitialStock($qty, $unitCost, $adminId, 'Stoc inițial la creare (din formularul de rețetă).');
        }

        ActivityLogger::log('stock.item_created', 'A adăugat produsul de stoc „'.$stockItem->name.'" (din formularul de rețetă).');

        if ($this->newStockItemTargetIndex !== null) {
            $this->recipe[$this->newStockItemTargetIndex]['stock_item_id'] = $stockItem->id;
            $this->syncRecipeRows();
        }

        $this->newStockItemModalOpen = false;
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $data = $this->validate();

        $isEditing = $this->menuItem && $this->menuItem->exists;

        // Tokenii sunt sursa de adevar cand scoala foloseste tokeni — recalculam
        // pretul in lei chiar inainte de salvare, ca sa fie mereu consistent
        // (indiferent daca hook-ul updatedTokens a apucat sa ruleze sau nu).
        if ($this->usesTokens()) {
            $rate = (float) Settings::get('token_rate');
            $data['price'] = round(((float) $data['tokens']) * $rate, 2);
        } else {
            $data['tokens'] = null;
        }

        $imagePath = $this->existingImage;

        if ($this->removeImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->storeUploadedImage($this->image, 'menu-items');
        }

        $payload = [
            'name' => $data['name'],
            'menu_category_id' => $data['menu_category_id'],
            'quantity' => $data['quantity'] ?: null,
            'price' => $data['price'],
            'tokens' => $data['tokens'] ?: null,
            'description' => $data['description'] ?: null,
            'is_active' => $this->is_active,
            'image_path' => $imagePath,
        ];

        if ($isEditing) {
            $this->menuItem->update($payload);

            ActivityLogger::log('menu.item_updated', 'A modificat produsul „'.$this->menuItem->name.'".');

            $this->syncRecipe($this->menuItem);

            session()->flash('status', 'Produsul a fost actualizat.');
        } else {
            $payload['created_by'] = Auth::guard('admin')->id();

            $item = MenuItem::create($payload);

            ActivityLogger::log('menu.item_created', 'A adăugat produsul „'.$item->name.'".');

            $this->syncRecipe($item);

            session()->flash('status', 'Produsul a fost creat.');
        }

        $this->redirectRoute('admin.menu-items.index', navigate: true);
    }

    /**
     * DXA: adaugat (Bar - stocuri: reteta) — sterge toate liniile vechi si
     * recreeaza doar cele completate (stock_item_id + qty). Simplu si sigur:
     * o reteta are cateva linii cel mult, nu justifica un diff linie cu linie.
     */
    private function syncRecipe(MenuItem $menuItem): void
    {
        $menuItem->recipeLines()->delete();

        foreach ($this->recipe as $line) {
            if (empty($line['stock_item_id']) || $line['qty'] === '' || $line['qty'] === null) {
                continue;
            }

            $menuItem->recipeLines()->create([
                'stock_item_id' => $line['stock_item_id'],
                'qty' => (float) $line['qty'],
            ]);
        }
    }

    public function render()
    {
        $stockItems = StockItem::active()->ordered()->get();
        $recipeCost = $this->recipeCostPreview($stockItems);
        $price = $this->price !== null && $this->price !== '' ? (float) $this->price : null;
        $marginAmount = ($recipeCost !== null && $price !== null) ? round($price - $recipeCost, 2) : null;
        $marginPercent = ($marginAmount !== null && $price > 0) ? round($marginAmount / $price * 100, 1) : null;

        return view('livewire.admin.menu-items.form', [
            'categories' => MenuCategory::ordered()->get(),
            'usesTokens' => $this->usesTokens(),
            'tokenRate' => Settings::get('token_rate'),
            // DXA: adaugat (Bar - stocuri: reteta)
            'stockItems' => $stockItems,
            // DXA: adaugat (Bar - meniu: marja) - cost/marja calculate live din
            // liniile de reteta completate in formular (nu neaparat cele
            // salvate in DB - reflecta editarile curente, chiar nesalvate).
            'recipeCost' => $recipeCost,
            'marginAmount' => $marginAmount,
            'marginPercent' => $marginPercent,
        ]);
    }

    /**
     * Cost estimat per unitate, calculat live din liniile de rețetă
     * completate in formular (stock_item_id + qty), nu din MenuItem::
     * costPerUnit() - acela citeste din DB, ceea ce ar ignora modificari
     * nesalvate ale retetei. Null daca reteta e goala (nicio linie
     * completata) sau vreun ingredient din ea are cost necunoscut.
     */
    private function recipeCostPreview($stockItems): ?float
    {
        $total = 0.0;
        $hasLine = false;

        foreach ($this->recipe as $line) {
            if (empty($line['stock_item_id']) || $line['qty'] === '' || $line['qty'] === null) {
                continue;
            }

            $hasLine = true;
            $stockItem = $stockItems->firstWhere('id', (int) $line['stock_item_id']);

            if (! $stockItem || ! $stockItem->hasKnownCost()) {
                return null;
            }

            $total += (float) $line['qty'] * (float) $stockItem->avg_cost;
        }

        return $hasLine ? round($total, 4) : null;
    }
}
