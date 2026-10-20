<?php

namespace App\Livewire\Reception;

use App\Http\Middleware\ApplyAppWorker;
use App\Livewire\Concerns\ListsNotifications;
use App\Services\Push\Inbox;
use App\Services\Push\StaffNotifier;
use App\Support\ReceptionApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Runda 91. „Notificări” în aplicația reception a personalului: alertele primite ca push de contul cu care s-a făcut login, cu citit / necitit, și panoul de activare. */
#[Layout('layouts.reception')]
class Notifications extends Component
{
    use ListsNotifications;

    public const APP = 'reception';

    protected function inboxQuery(): Builder
    {
        return Inbox::forStaff((int) (ApplyAppWorker::loginId() ?? Auth::guard('admin')->id()), self::APP);
    }

    protected function fallbackUrl(): string
    {
        return route(ReceptionApp::ROUTE_PREFIX.'.home', [], false);
    }

    public function render()
    {
        return view('livewire.staff.notifications', $this->inboxData() + ['pushEnabled' => StaffNotifier::enabled(), 'pwa' => ReceptionApp::class, 'app' => self::APP]);
    }
}
