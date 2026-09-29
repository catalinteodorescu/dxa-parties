<?php

namespace App\Livewire\Reception;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Recepție). Login-ul aplicației de recepție: același cont (telefon + parolă) ca în panoul admin,
 * dar cere bifa „Aplicație recepție" (superadmin-ul are implicit acces).
 */
#[Layout('layouts.reception-guest')]
class Login extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = true;

    public function login(): void
    {
        $credentials = $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $this->remember)) {
            ActivityLogger::log(
                'admin.login.failed',
                'Încercare de autentificare eșuată în aplicația de recepție pentru '.$this->phone.'.',
                Admin::where('phone', $this->phone)->first(),
                actor: null,
            );

            $this->password = '';
            $this->addError('phone', 'Telefon sau parolă incorectă.');

            return;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            ActivityLogger::log('admin.login.blocked_inactive', 'Cont dezactivat a încercat autentificarea în aplicația de recepție.', $admin, actor: null);

            Auth::guard('admin')->logout();
            $this->password = '';
            $this->addError('phone', 'Acest cont este dezactivat.');

            return;
        }

        if (! $admin->canAccess(Admin::APP_RECEPTION)) {
            ActivityLogger::log('admin.login.blocked_no_access', 'A încercat să intre în aplicația de recepție fără acces.', $admin, actor: null);

            Auth::guard('admin')->logout();
            $this->password = '';
            $this->addError('phone', 'Contul tău nu are acces la această aplicație.');

            return;
        }

        request()->session()->regenerate();

        ActivityLogger::log('admin.login.success', 'S-a autentificat în aplicația de recepție.', actor: $admin);

        $this->redirectRoute('receptie.party', navigate: true);
    }

    public function render()
    {
        return view('livewire.reception.login');
    }
}
