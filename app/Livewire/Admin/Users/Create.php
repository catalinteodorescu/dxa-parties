<?php

namespace App\Livewire\Admin\Users;

use App\Contracts\SmsSender;
use App\Livewire\Admin\Users\Concerns\EditsPermissions;
use App\Models\Admin;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Create extends Component
{
    use EditsPermissions; // DXA: runda 46 — matricea de permisiuni + copiere de la alt utilizator

    public string $phone = '';

    // DXA: adaugat (Utilizatori - acces pe aplicație): aplicațiile în care noul utilizator se poate autentifica.
    public bool $accessAdmin = true;

    public bool $accessBar = false;

    public bool $accessReception = false;

    public function mount(): void
    {
        abort_unless(Auth::guard('admin')->user()->permits('users', 'edit'), 403);

        $this->fillPermissions(Permissions::none());
    }

    public function save(SmsSender $sms): void
    {
        $current = Auth::guard('admin')->user();

        abort_unless($current->permits('users', 'edit'), 403);

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
            // Doar superadmin-ul setează permisiunile; un cont creat de altcineva pornește fără niciuna.
            'permissions' => $current->isSuperAdmin() ? $this->permissionsPayload() : Permissions::none(),
        ]);

        $url = URL::temporarySignedRoute(
            'admin.invite.complete',
            now()->addDays(7),
            ['admin' => $admin->id]
        );

        $sms->send($admin->phone, "Ai fost adăugat în echipa DXA. Completează-ți contul aici: {$url}");

        ActivityLogger::log('admin.created', 'A invitat un utilizator nou ('.$admin->phone.').', $admin);

        if ($current->isSuperAdmin() && ($summary = Permissions::describeChange(Permissions::none(), $admin->permissions)) !== '') {
            ActivityLogger::log('admin.permissions.updated', 'A setat permisiunile lui '.ActivityLogger::label($admin).': '.$summary.'.', $admin);
        }

        session()->flash('status', 'Invitația a fost trimisă către '.$admin->phone.'.');

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.users.create', [
            'isSuper' => Auth::guard('admin')->user()->isSuperAdmin(),
            'copySources' => $this->copySources(),
        ]);
    }
}
