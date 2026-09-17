<?php

namespace App\Livewire\Admin\MenuCategories;

use App\Models\MenuCategory;
use App\Services\ActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Index extends Component
{
    public bool $modalOpen = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                function ($attribute, $value, $fail) {
                    $exists = MenuCategory::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                        ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                        ->exists();

                    if ($exists) {
                        $fail('Există deja o categorie cu acest nume.');
                    }
                },
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Numele categoriei este obligatoriu.',
        ];
    }

    public function openCreate(): void
    {
        $this->reset('editingId', 'name');
        $this->is_active = true;
        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $category = MenuCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->is_active = $category->is_active;
        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $category = MenuCategory::findOrFail($this->editingId);
            $category->update([
                'name' => $this->name,
                'is_active' => $this->is_active,
            ]);

            ActivityLogger::log('menu.category_updated', 'A modificat categoria de meniu „'.$category->name.'".');
        } else {
            $nextOrder = (int) (MenuCategory::max('sort_order') ?? 0) + 1;

            $category = MenuCategory::create([
                'name' => $this->name,
                'sort_order' => $nextOrder,
                'is_active' => $this->is_active,
            ]);

            ActivityLogger::log('menu.category_created', 'A adăugat categoria de meniu „'.$category->name.'".');
        }

        $this->modalOpen = false;
        session()->flash('status', $this->editingId ? 'Categoria a fost actualizată.' : 'Categoria a fost adăugată.');
    }

    public function toggleActive(int $id): void
    {
        $category = MenuCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        ActivityLogger::log(
            'menu.category_toggled',
            ($category->is_active ? 'A făcut vizibilă' : 'A ascuns').' categoria de meniu „'.$category->name.'".',
        );
    }

    public function moveUp(int $id): void
    {
        $this->swapOrder($id, 'up');
    }

    public function moveDown(int $id): void
    {
        $this->swapOrder($id, 'down');
    }

    private function swapOrder(int $id, string $direction): void
    {
        $category = MenuCategory::findOrFail($id);

        $neighbor = $direction === 'up'
            ? MenuCategory::where('sort_order', '<', $category->sort_order)->orderByDesc('sort_order')->first()
            : MenuCategory::where('sort_order', '>', $category->sort_order)->orderBy('sort_order')->first();

        if (! $neighbor) {
            return;
        }

        [$a, $b] = [$category->sort_order, $neighbor->sort_order];
        $category->update(['sort_order' => $b]);
        $neighbor->update(['sort_order' => $a]);
    }

    /**
     * Reordonare prin drag & drop: muta categoria $draggedId chiar inaintea
     * lui $targetId, apoi renumeroteaza sort_order pentru toate categoriile
     * (0, 1, 2, ...) ca sa ramana un ordin curat, fara goluri.
     */
    public function reorder(int $draggedId, int $targetId): void
    {
        if ($draggedId === $targetId) {
            return;
        }

        $ids = MenuCategory::ordered()->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($id) => $id !== $draggedId));

        $targetIndex = array_search($targetId, $ids, true);
        if ($targetIndex === false) {
            return;
        }

        array_splice($ids, $targetIndex, 0, [$draggedId]);

        foreach ($ids as $index => $id) {
            MenuCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        ActivityLogger::log('menu.categories_reordered', 'A reordonat categoriile de meniu.');
    }

    public function delete(int $id): void
    {
        $category = MenuCategory::findOrFail($id);

        if ($category->items()->exists()) {
            session()->flash('error', 'Categoria „'.$category->name.'" are produse asociate — mută sau șterge întâi produsele.');

            return;
        }

        $name = $category->name;
        $category->delete();

        ActivityLogger::log('menu.category_deleted', 'A șters categoria de meniu „'.$name.'".');
        session()->flash('status', 'Categoria a fost ștearsă.');
    }

    public function render()
    {
        return view('livewire.admin.menu-categories.index', [
            // Fara paginare aici, intentionat: reordonarea prin drag & drop
            // are nevoie de toata lista vizibila deodata. Nomenclatorul e
            // gandit sa ramana mic (cateva zeci de categorii cel mult).
            'categories' => MenuCategory::ordered()->withCount('items')->get(),
        ]);
    }
}
