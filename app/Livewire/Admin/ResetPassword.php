<?php

namespace App\Livewire\Admin;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ResetPassword extends Component
{
    public int $adminId;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(Admin $admin): void
    {
        abort_if(! $admin->is_active, 403);

        $this->adminId = $admin->id;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $admin = Admin::findOrFail($this->adminId);

        $admin->update([
            'password' => $validated['password'],
        ]);

        ActivityLogger::log(
            'admin.password.reset_completed',
            'Parola a fost resetată prin link SMS.',
            $admin,
            actor: null,
        );

        session()->flash('status', 'Parola a fost schimbată. Te poți loga acum.');

        $this->redirectRoute('admin.login', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.reset-password');
    }
}
