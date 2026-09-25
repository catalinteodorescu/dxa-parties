<?php

namespace App\Livewire\Admin\Parties;

use App\Models\Party;
use App\Services\ActivityLogger;
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
    public string $state = 'all';       // all | live | upcoming | past | inactive | draft

    #[Url]
    public string $kind = 'all';        // all | basic | festival

    #[Url]
    public string $audience = 'all';    // all | public | auth

    public function updated($name): void
    {
        // Orice schimbare de filtru/căutare readuce la prima pagină.
        if (in_array($name, ['search', 'state', 'kind', 'audience'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state', 'kind', 'audience');
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $party = Party::findOrFail($id);
        $party->update(['is_active' => ! $party->is_active]);

        ActivityLogger::log(
            'party.toggled',
            ($party->is_active ? 'A făcut vizibilă' : 'A ascuns').' petrecerea „'.$party->name.'".',
        );
    }

    public function publish(int $id): void
    {
        $party = Party::findOrFail($id);
        $party->update(['status' => 'published']);

        ActivityLogger::log('party.published', 'A publicat petrecerea „'.$party->name.'".');

        session()->flash('status', 'Petrecerea a fost publicată.');
    }

    public function duplicate(int $id): void
    {
        $original = Party::findOrFail($id);

        $copy = $original->replicate(['created_by']);
        $copy->name = Str::limit($original->name, 110, '').' (copie)';
        $copy->status = 'draft';
        $copy->created_by = Auth::guard('admin')->id();

        // Copiem si fisierul imaginii principale, ca stergerea unuia sa nu-l afecteze pe celalalt.
        // (Pozele invitatilor din JSON vor fi tratate la modulul lor, cand upload-ul lor exista.)
        if ($original->image_path && Storage::disk('public')->exists($original->image_path)) {
            $ext = pathinfo($original->image_path, PATHINFO_EXTENSION) ?: 'jpg';
            $newPath = 'parties/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->copy($original->image_path, $newPath);
            $copy->image_path = $newPath;
        }

        $copy->save();

        ActivityLogger::log('party.duplicated', 'A duplicat petrecerea „'.$original->name.'" (ciornă).');

        $this->redirectRoute('admin.parties.edit', ['party' => $copy->id], navigate: true);
    }

    public function delete(int $id): void
    {
        $party = Party::findOrFail($id);

        // DXA: adaugat (Recepție): intrările sunt evidențe de încasări, deci petrecerea nu se șterge cât le are.
        if ($party->entries()->exists() || $party->tokenTransactions()->exists()) {
            session()->flash('error', 'Petrecerea are intrări sau vânzări de tokeni înregistrate la Recepție și nu poate fi ștearsă.');

            return;
        }

        if ($party->image_path) {
            Storage::disk('public')->delete($party->image_path);
        }

        $name = $party->name;
        $party->delete();

        ActivityLogger::log('party.deleted', 'A șters petrecerea „'.$name.'".');

        session()->flash('status', 'Petrecerea a fost ștearsă.');
    }

    public function render()
    {
        $now = now();

        $query = Party::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->kind !== 'all', fn ($q) => $q->where('kind', $this->kind))
            ->when($this->audience === 'public', fn ($q) => $q->where('audience', 'all'))
            ->when($this->audience === 'auth', fn ($q) => $q->where('audience', 'auth'));

        // Filtrare dupa starea calculata.
        $query->when($this->state === 'draft', fn ($q) => $q->where('status', 'draft'));

        $query->when($this->state === 'inactive', fn ($q) => $q
            ->where('status', 'published')->where('is_active', false));

        $query->when($this->state === 'upcoming', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->whereNotNull('starts_at')->where('starts_at', '>', $now));

        $query->when($this->state === 'live', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->where(fn ($s) => $s->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($s) => $s->whereNull('ends_at')->orWhere('ends_at', '>=', $now)));

        $query->when($this->state === 'past', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->whereNotNull('ends_at')->where('ends_at', '<', $now));

        // Sortare: viitoare / in desfasurare intai (cea mai apropiata sus),
        // apoi trecutele (cele mai recente sus).
        $query->orderByRaw('CASE WHEN ends_at IS NOT NULL AND ends_at < ? THEN 1 ELSE 0 END asc', [$now])
            ->orderByRaw('CASE WHEN (ends_at IS NULL OR ends_at >= ?) THEN starts_at END asc', [$now])
            ->orderByRaw('CASE WHEN (ends_at IS NOT NULL AND ends_at < ?) THEN starts_at END desc', [$now]);

        return view('livewire.admin.parties.index', [
            'parties' => $query->paginate(10),
            'currentAdmin' => Auth::guard('admin')->user(),
        ]);
    }
}
