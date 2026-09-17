<?php

namespace App\Livewire\Admin\Announcements;

use App\Models\Announcement;
use App\Services\ActivityLogger;
use App\Support\HandlesImageUploads;
use Illuminate\Support\Carbon;
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

    public ?Announcement $announcement = null;

    // Campuri
    public string $title = '';
    public ?string $body = null;
    public ?string $url = null;
    public ?string $url_label = null;
    public string $audience = 'all';
    public bool $in_carousel = false;
    public bool $in_list = true;
    public bool $is_active = true;
    public string $status = 'published';
    public ?string $starts_at = null;
    public ?string $ends_at = null;

    // Imagine
    public $image = null;                 // upload temporar nou
    public ?string $existingImage = null; // path-ul imaginii deja salvate (la editare)
    public bool $removeImage = false;

    public function mount(?Announcement $announcement = null): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        if ($announcement && $announcement->exists) {
            $this->announcement = $announcement;

            $this->title = $announcement->title;
            $this->body = $announcement->body;
            $this->url = $announcement->url;
            $this->url_label = $announcement->url_label;
            $this->audience = $announcement->audience;
            $this->in_carousel = $announcement->in_carousel;
            $this->in_list = $announcement->in_list;
            $this->is_active = $announcement->is_active;
            $this->status = $announcement->status;
            $this->starts_at = $announcement->starts_at?->format('Y-m-d\TH:i');
            $this->ends_at = $announcement->ends_at?->format('Y-m-d\TH:i');
            $this->existingImage = $announcement->image_path;
        } else {
            $this->starts_at = now()->format('Y-m-d\TH:i');
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:2048'],
            'url_label' => ['nullable', 'string', 'max:40'],
            'audience' => ['required', 'in:all,auth'],
            'in_carousel' => ['boolean'],
            'in_list' => ['boolean'],
            'is_active' => ['boolean'],
            'status' => ['required', 'in:draft,published'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => ['nullable', 'image', 'max:8192'], // 8 MB (se redimensionează la salvare)
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Titlul este obligatoriu.',
            'url.url' => 'Adresa URL nu pare validă (începe cu https://).',
            'ends_at.after' => 'Data de sfârșit trebuie să fie după data de început.',
            'image.image' => 'Fișierul trebuie să fie o imagine.',
            'image.max' => 'Imaginea nu poate depăși 8 MB.',
        ];
    }

    public function clearImage(): void
    {
        $this->reset('image');
        $this->removeImage = true;
    }

    public function save(): void
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        $data = $this->validate();

        // Cel putin o plasare trebuie bifata.
        if (! $this->in_carousel && ! $this->in_list) {
            $this->addError('in_list', 'Alege cel puțin o plasare: carusel sau zona de anunțuri.');

            return;
        }

        $isEditing = $this->announcement && $this->announcement->exists;

        // Gestionare imagine.
        $imagePath = $this->existingImage;

        if ($this->removeImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->storeUploadedImage($this->image, 'announcements');
        }

        $payload = [
            'title' => $data['title'],
            'body' => $data['body'] ?: null,
            'url' => $data['url'] ?: null,
            'url_label' => $data['url_label'] ?: null,
            'audience' => $data['audience'],
            'in_carousel' => $this->in_carousel,
            'in_list' => $this->in_list,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'starts_at' => $this->starts_at ? Carbon::parse($this->starts_at) : null,
            'ends_at' => $this->ends_at ? Carbon::parse($this->ends_at) : null,
            'image_path' => $imagePath,
        ];

        if ($isEditing) {
            $this->announcement->update($payload);

            ActivityLogger::log('announcement.updated', 'A modificat anunțul „'.$this->announcement->title.'\".');

            session()->flash('status', 'Anunțul a fost actualizat.');
        } else {
            $payload['created_by'] = Auth::guard('admin')->id();

            $announcement = Announcement::create($payload);

            ActivityLogger::log('announcement.created', 'A creat anunțul „'.$announcement->title.'\".');

            session()->flash('status', 'Anunțul a fost creat.');
        }

        $this->redirectRoute('admin.announcements.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.announcements.form');
    }
}
