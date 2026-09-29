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

    // DXA: adaugat (Utilizatori - acces pe aplicație): aplicațiile în care noul utilizator se poate autentifica.
    public bool $accessAdmin = true;

    public bool $accessBar = false;

    public bool $accessReception = false;

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

        if (! $this->accessAdmin && ! $this->accessBar && ! $this->accessReception) {
            $this->addError('accessAdmin', 'Alege cel puțin o aplicație.');

            return;
        }

        $admin = Admin::create([
            'name' => null,
            'phone' => $validated['phone'],
            'password' => Str::random(40),
            'access_admin' => $this->accessAdmin,
            'access_bar' => $this->accessBar,
            'access_reception' => $this->accessReception,
        ]);

        $url = URL::temporarySignedRoute(
            'admin.invite.complete',
            now()->addDays(7),
            ['admin' => $admin->id]
        );

        $sms->send($admin->phone, "Ai fost adăugat în echipa DXA. Completează-ți contul aici: {$url}");

        ActivityLogger::log('admin.created', 'A invitat un utilizator nou ('.$admin->phone.').', $admin);

        session()->flash('status', 'Invitația a fost trimisă către '.$admin->phone.'.');

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.create');
    }
}
