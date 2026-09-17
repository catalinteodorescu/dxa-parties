<?php

namespace App\Livewire\Admin\Announcements;

use App\Models\Announcement;
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
    public string $state = 'all';       // all | live | scheduled | expired | inactive | draft

    #[Url]
    public string $placement = 'all';   // all | carousel | list

    #[Url]
    public string $audience = 'all';    // all | public | auth

    public function updated($name): void
    {
        // Orice schimbare de filtru/căutare readuce la prima pagină.
        if (in_array($name, ['search', 'state', 'placement', 'audience'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state', 'placement', 'audience');
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update(['is_active' => ! $announcement->is_active]);

        ActivityLogger::log(
            'announcement.toggled',
            ($announcement->is_active ? 'A făcut vizibil' : 'A ascuns').' anunțul „'.$announcement->title.'".',
        );
    }

    public function publish(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update(['status' => 'published']);

        ActivityLogger::log('announcement.published', 'A publicat anunțul „'.$announcement->title.'".');

        session()->flash('status', 'Anunțul a fost publicat.');
    }

    public function duplicate(int $id): void
    {
        $original = Announcement::findOrFail($id);

        $copy = $original->replicate(['created_by']);
        $copy->title = Str::limit($original->title, 110, '').' (copie)';
        $copy->status = 'draft';
        $copy->created_by = Auth::guard('admin')->id();

        // Copiem si fisierul imaginii, ca stergerea unuia sa nu-l afecteze pe celalalt.
        if ($original->image_path && Storage::disk('public')->exists($original->image_path)) {
            $ext = pathinfo($original->image_path, PATHINFO_EXTENSION) ?: 'jpg';
            $newPath = 'announcements/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->copy($original->image_path, $newPath);
            $copy->image_path = $newPath;
        }

        $copy->save();

        ActivityLogger::log('announcement.duplicated', 'A duplicat anunțul „'.$original->title.'" (ciornă).');

        $this->redirectRoute('admin.announcements.edit', ['announcement' => $copy->id], navigate: true);
    }

    public function delete(int $id): void
    {
        $announcement = Announcement::findOrFail($id);

        if ($announcement->image_path) {
            Storage::disk('public')->delete($announcement->image_path);
        }

        $title = $announcement->title;
        $announcement->delete();

        ActivityLogger::log('announcement.deleted', 'A sters anuntul „'.$title.'".');

        session()->flash('status', 'Anunțul a fost șters.');
    }

    public function render()
    {
        $now = now();

        $query = Announcement::query()
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->placement === 'carousel', fn ($q) => $q->where('in_carousel', true))
            ->when($this->placement === 'list', fn ($q) => $q->where('in_list', true))
            ->when($this->audience === 'public', fn ($q) => $q->where('audience', 'all'))
            ->when($this->audience === 'auth', fn ($q) => $q->where('audience', 'auth'));

        // Filtrare dupa starea calculata.
        $query->when($this->state === 'draft', fn ($q) => $q->where('status', 'draft'));

        $query->when($this->state === 'inactive', fn ($q) => $q
            ->where('status', 'published')->where('is_active', false));

        $query->when($this->state === 'scheduled', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->whereNotNull('starts_at')->where('starts_at', '>', $now));

        $query->when($this->state === 'expired', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->whereNotNull('ends_at')->where('ends_at', '<', $now));

        $query->when($this->state === 'live', fn ($q) => $q
            ->where('status', 'published')->where('is_active', true)
            ->where(fn ($s) => $s->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($s) => $s->whereNull('ends_at')->orWhere('ends_at', '>=', $now)));

        return view('livewire.admin.announcements.index', [
            'announcements' => $query->orderByDesc('id')->paginate(10),
            'currentAdmin' => Auth::guard('admin')->user(),
        ]);
    }
}
