<?php

namespace App\Livewire\Participant;

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 35). Pagina unui anunț: titlu, data publicării, imaginea și textul întreg (+ butonul de link, dacă există).
 * Doar anunțurile vizibile acum (publicate, active, în interval; „doar logați” cer cont) — altfel 404.
 */
#[Layout('layouts.participant')]
class AnnouncementShow extends Component
{
    public int $announcementId;

    public function mount(Announcement $announcement): void
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';
        abort_unless(Announcement::query()->visible($audience)->whereKey($announcement->id)->exists(), 404);

        $this->announcementId = $announcement->id;
    }

    public function render()
    {
        $announcement = Announcement::findOrFail($this->announcementId);

        return view('livewire.participant.announcement-show', ['announcement' => $announcement])->title($announcement->title);
    }
}
