<?php

namespace App\Livewire\Bar;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (PWA Bar). Login-ul aplicației de bar: același cont (telefon + parolă) ca în panoul admin,
 * dar cere bifa „Aplicație bar" (superadmin-ul are implicit acces).
 */
#[Layout('layouts.bar-guest')]
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
                'Încercare de autentificare eșuată în aplicația de bar pentru '.$this->phone.'.',
                Admin::where('phone', $this->phone)->first(),
                actor: null,
            );

            $this->password = '';
            $this->addError('phone', 'Telefon sau parolă incorectă.');

            return;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            ActivityLogger::log('admin.login.blocked_inactive', 'Cont dezactivat a încercat autentificarea în aplicația de bar.', $admin, actor: null);

            Auth::guard('admin')->logout();
            $this->password = '';
            $this->addError('phone', 'Acest cont este dezactivat.');

            return;
        }

        if (! $admin->canAccess(Admin::APP_BAR)) {
            ActivityLogger::log('admin.login.blocked_no_access', 'A încercat să intre în aplicația de bar fără acces.', $admin, actor: null);

            Auth::guard('admin')->logout();
            $this->password = '';
            $this->addError('phone', 'Contul tău nu are acces la această aplicație.');

            return;
        }

        request()->session()->regenerate();

        ActivityLogger::log('admin.login.success', 'S-a autentificat în aplicația de bar.', actor: $admin);

        $this->redirectRoute('bar.party', navigate: true);
    }

    public function render()
    {
        return view('livewire.bar.login');
    }
}
