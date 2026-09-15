<?php

namespace App\Livewire\Admin\Users;

use App\Contracts\SmsSender;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Create extends Component
{
    public string $phone = '';

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->isSuperAdmin(), 403);
    }

    public function save(SmsSender $sms): void
    {
        abort_unless(Auth::guard('admin')->user()->isSuperAdmin(), 403);

        $validated = $this->validate([
            'phone' => [
                'required',
                'regex:/^07[0-9]{8}$/',
                'unique:admins,phone',
            ],
        ], [
            'phone.regex' => 'Numărul trebuie să fie de forma 07XXXXXXXX.',
        ]);

        $admin = Admin::create([
            'name' => null,
            'phone' => $validated['phone'],
            'password' => Str::random(40),
        ]);

        $url = URL::temporarySignedRoute(
            'admin.invite.complete',
            now()->addDays(7),
            ['admin' => $admin->id]
        );

        $sms->send($admin->phone, "Ai fost adăugat ca admin. Completează-ți contul aici: {$url}");

        ActivityLogger::log('admin.created', 'A invitat un admin nou ('.$admin->phone.').', $admin);

        session()->flash('status', 'Invitația a fost trimisă către '.$admin->phone.'.');

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.create');
    }
}
