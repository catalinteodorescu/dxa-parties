<?php

namespace App\Livewire\Admin\Settings;

use App\Models\MenuCategory;
use App\Services\ActivityLogger;
use Livewire\Component;

/**
 * DXA: adaugat (Setari - categorii meniu bar).
 *
 * Panou inclus in pagina de Setari: redenumire inline, vizibil/ascuns, stergere
 * si reordonare (drag & drop). Actiunile se salveaza pe loc (nu depind de
 * butonul „Salveaza setarile" al formularului de setari). Categoriile NU se
 * creeaza aici, ci din butonul „+" de langa selectul din formularul de produs.
 */
class MenuCategories extends Component
{
    /** @var array<int, string> id categorie => nume din input (editabil inline) */
    public array $names = [];

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->names = MenuCategory::ordered()->pluck('name', 'id')->all();
    }

    public function rename(int $id): void
    {
        $this->message = $this->error = null;

        $category = MenuCategory::findOrFail($id);
        $name = trim((string) ($this->names[$id] ?? ''));

        if ($name === '') {
            $this->names[$id] = $category->name;
            $this->error = 'Numele categoriei este obligatoriu.';

            return;
        }

        if (mb_strlen($name) > 120) {
            $this->error = 'Numele categoriei poate avea cel mult 120 de caractere.';

            return;
        }

        $duplicate = MenuCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            $this->names[$id] = $category->name;
            $this->error = 'Există deja o categorie cu numele „'.$name.'".';

            return;
        }

        $this->names[$id] = $name;

        if ($name === $category->name) {
            return; // nimic schimbat: nu salvam, nu logam
        }

        $old = $category->name;
        $category->update(['name' => $name]);

        ActivityLogger::log('menu.category_updated', 'A redenumit categoria de meniu „'.$old.'" în „'.$name.'".');
        $this->message = 'Categoria a fost redenumită.';
    }

    public function toggleActive(int $id): void
    {
        $this->message = $this->error = null;

        $category = MenuCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        ActivityLogger::log(
            'menu.category_toggled',
            ($category->is_active ? 'A făcut vizibilă' : 'A ascuns').' categoria de meniu „'.$category->name.'".',
        );
    }

    public function delete(int $id): void
    {
        $this->message = $this->error = null;

        $category = MenuCategory::findOrFail($id);
        $count = $category->items()->count();

        if ($count > 0) {
            $this->error = 'Categoria „'.$category->name.'" are '.$count.' '.($count === 1 ? 'produs asociat' : 'produse asociate').' — mută sau șterge întâi produsele.';

            return;
        }

        $name = $category->name;
        $category->delete();
        unset($this->names[$id]);

        ActivityLogger::log('menu.category_deleted', 'A șters categoria de meniu „'.$name.'".');
        $this->message = 'Categoria a fost ștearsă.';
    }

    public function moveUp(int $id): void
    {
        $this->move($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->move($id, 1);
    }

    private function move(int $id, int $delta): void
    {
        $ids = array_map('intval', MenuCategory::ordered()->pluck('id')->all());
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $this->reorder($id, max(0, min($index + $delta, count($ids) - 1)));
    }

    /**
     * Drag & drop (wire:sort): primeste categoria mutata si pozitia (index de la 0)
     * pe care a ajuns, apoi renumeroteaza sort_order (0, 1, 2, ...) fara goluri.
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
            return;
        }

        foreach ($ids as $index => $categoryId) {
            MenuCategory::where('id', $categoryId)->update(['sort_order' => $index]);
        }

        ActivityLogger::log('menu.categories_reordered', 'A reordonat categoriile de meniu.');
    }

    public function render()
    {
        return view('livewire.admin.settings.menu-categories', [
            'categories' => MenuCategory::ordered()->withCount('items')->get(),
        ]);
    }
}
