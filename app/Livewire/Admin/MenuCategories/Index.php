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
     * Reordonare prin drag & drop (wire:sort): primeste categoria mutata si POZITIA
     * (index de la 0) pe care a ajuns in lista, apoi renumeroteaza sort_order pentru
     * toate categoriile (0, 1, 2, ...) ca sa ramana un ordin curat, fara goluri.
     * Pozitia ultimului element (= numarul de categorii - 1) e valida, deci se poate
     * duce o categorie si pe ultimul loc.
     */
    public function reorder(int $id, int $position): void
    {
        $before = array_map('intval', MenuCategory::ordered()->pluck('id')->all());

        if (! in_array($id, $before, true)) {
            return;
        }

        $ids = array_values(array_filter($before, fn ($x) => $x !== $id));
        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$id]);

        if ($ids === $before) {
            return; // eliberat pe acelasi loc: nimic de salvat / de logat
        }

        foreach ($ids as $index => $categoryId) {
            MenuCategory::where('id', $categoryId)->update(['sort_order' => $index]);
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
