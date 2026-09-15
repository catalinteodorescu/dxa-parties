<?php

namespace App\Livewire\Admin;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * True cât timp adminul inițial (seed-ul, login temporar "admin")
     * nu și-a completat încă setup-ul de telefon. În starea asta,
     * formularul nu cere un telefon, ci literalmente "admin".
     */
    public bool $isBootstrapLogin = false;

    public function mount(): void
    {
        $this->isBootstrapLogin = Admin::where('phone', 'admin')
            ->where('phone_needs_setup', true)
            ->exists();
    }

    public function login(): void
    {
        $credentials = $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $this->remember)) {
            $attempted = Admin::where('phone', $this->phone)->first();

            ActivityLogger::log(
                'admin.login.failed',
                'Încercare de autentificare eșuată pentru '.$this->phone.'.',
                $attempted,
                actor: null,
            );

            $this->password = '';
            $this->addError('phone', 'Telefon sau parolă incorectă.');

            return;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            ActivityLogger::log(
                'admin.login.blocked_inactive',
                'Cont dezactivat a încercat autentificarea.',
                $admin,
                actor: null,
            );

            Auth::guard('admin')->logout();
            $this->password = '';
            $this->addError('phone', 'Acest cont este dezactivat.');

            return;
        }

        request()->session()->regenerate();

        ActivityLogger::log('admin.login.success', 'S-a autentificat.', actor: $admin);

        // Dacă adminul trebuie să-și seteze telefonul, middleware-ul
        // admin.phone_setup îl redirecționează automat de acolo.
        $this->redirectRoute('admin.dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.login');
    }
}
