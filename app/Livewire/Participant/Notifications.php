<?php

namespace App\Livewire\Participant;

use App\Livewire\Concerns\ListsNotifications;
use App\Services\Push\Inbox;
use App\Services\Push\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Runda 91. „Notificări” în aplicația participanților: ce s-a trimis ca push, cu citit / necitit, și panoul de activare. */
#[Layout('layouts.participant', ['title' => 'Notificări'])]
class Notifications extends Component
{
    use ListsNotifications;

    protected function inboxQuery(): Builder
    {
        return Inbox::forParticipant((int) auth('participant')->id());
    }

    protected function fallbackUrl(): string
    {
        return route('app.home', [], false);
    }

    public function render()
    {
        return view('livewire.participant.notifications', $this->inboxData() + ['pushEnabled' => Notifier::enabled()]);
    }
}
