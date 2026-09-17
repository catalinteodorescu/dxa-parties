<?php

namespace App\Livewire\Admin\MenuItems;

use App\Models\MenuCategory;
use App\Models\MenuItem;
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
        }
    }

    public function usesTokens(): bool
    {
        return (bool) Settings::get('uses_tokens');
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
            $rules['tokens'] = ['required', 'numeric', 'min:0', 'max:99999.99'];
            $rules['price'] = ['nullable', 'numeric', 'min:0'];
        } else {
            $rules['price'] = ['required', 'numeric', 'min:0', 'max:99999.99'];
            $rules['tokens'] = ['nullable'];
        }

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
            'tokens.numeric' => 'Prețul în tokeni trebuie să fie un număr.',
            'image.image' => 'Fișierul trebuie să fie o imagine.',
            'image.max' => 'Imaginea nu poate depăși 8 MB.',
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

            session()->flash('status', 'Produsul a fost actualizat.');
        } else {
            $payload['created_by'] = Auth::guard('admin')->id();

            $item = MenuItem::create($payload);

            ActivityLogger::log('menu.item_created', 'A adăugat produsul „'.$item->name.'".');

            session()->flash('status', 'Produsul a fost creat.');
        }

        $this->redirectRoute('admin.menu-items.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.menu-items.form', [
            'categories' => MenuCategory::ordered()->get(),
            'usesTokens' => $this->usesTokens(),
            'tokenRate' => Settings::get('token_rate'),
        ]);
    }
}
