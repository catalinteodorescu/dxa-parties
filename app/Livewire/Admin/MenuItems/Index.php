<?php

namespace App\Livewire\Admin\MenuItems;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\ActivityLogger;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = 'all'; // all | {menu_category_id}

    #[Url]
    public string $state = 'all';    // all | active | inactive

    // DXA: adaugat (Meniu bar - afisare) - un singur camp pentru modul de afisare
    // SI sortare; preferinta de afisare, nu filtru, deci NU e resetata de clearFilters().
    #[Url]
    public string $view = 'grouped';

    /** cheie => [eticheta, coloana, directie]. 'grouped' = pe categorii (in ordinea din Setari), nume A-Z in categorie. */
    public const VIEWS = [
        'grouped' => ['Pe categorii', 'name', 'asc'],
        'name_asc' => ['Listă · Nume A → Z', 'name', 'asc'],
        'name_desc' => ['Listă · Nume Z → A', 'name', 'desc'],
        'price_asc' => ['Listă · Preț crescător', 'price', 'asc'],
        'price_desc' => ['Listă · Preț descrescător', 'price', 'desc'],
        'newest' => ['Listă · Cele mai noi', 'created_at', 'desc'],
    ];

    public function updated($name): void
    {
        if (in_array($name, ['search', 'category', 'state', 'view'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'state');
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        ActivityLogger::log(
            'menu.item_toggled',
            ($item->is_active ? 'A făcut vizibil' : 'A ascuns').' produsul „'.$item->name.'".',
        );
    }

    public function duplicate(int $id): void
    {
        $original = MenuItem::findOrFail($id);

        $copy = $original->replicate(['created_by']);
        $copy->name = $original->name.' (copie)';
        $copy->is_active = false;
        $copy->created_by = Auth::guard('admin')->id();

        if ($original->image_path && Storage::disk('public')->exists($original->image_path)) {
            $ext = pathinfo($original->image_path, PATHINFO_EXTENSION) ?: 'jpg';
            $newPath = 'menu-items/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->copy($original->image_path, $newPath);
            $copy->image_path = $newPath;
        }

        $copy->save();

        ActivityLogger::log('menu.item_duplicated', 'A duplicat produsul „'.$original->name.'" (ascuns).');

        $this->redirectRoute('admin.menu-items.edit', ['menuItem' => $copy->id], navigate: true);
    }

    public function delete(int $id): void
    {
        $item = MenuItem::findOrFail($id);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $name = $item->name;
        $item->delete();

        ActivityLogger::log('menu.item_deleted', 'A șters produsul „'.$name.'".');
        session()->flash('status', 'Produsul a fost șters.');
    }

    public function render()
    {
        $viewKey = array_key_exists($this->view, self::VIEWS) ? $this->view : 'grouped';
        $grouped = $viewKey === 'grouped';
        [, $sortColumn, $sortDirection] = self::VIEWS[$viewKey];

        $query = MenuItem::query()
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->select('menu_items.*')
            ->with(['category', 'recipeLines.stockItem'])
            ->when($this->search !== '', fn ($q) => $q->where('menu_items.name', 'like', '%'.$this->search.'%'))
            ->when($this->category !== 'all', fn ($q) => $q->where('menu_items.menu_category_id', $this->category))
            ->when($this->state === 'active', fn ($q) => $q->where('menu_items.is_active', true))
            ->when($this->state === 'inactive', fn ($q) => $q->where('menu_items.is_active', false))
            // Grupat: categoriile in ordinea din Setari, iar produsele in interiorul lor dupa nume.
            ->when($grouped, fn ($q) => $q
                ->orderBy('menu_categories.sort_order')
                ->orderBy('menu_categories.name'))
            ->orderBy('menu_items.'.$sortColumn, $sortDirection)
            ->orderBy('menu_items.id');

        return view('livewire.admin.menu-items.index', [
            'items' => $query->paginate(15),
            'categories' => MenuCategory::ordered()->get(),
            'usesTokens' => (bool) Settings::get('uses_tokens'),
            'grouped' => $grouped,
            'viewOptions' => array_map(fn ($v) => $v[0], self::VIEWS),
        ]);
    }
}
