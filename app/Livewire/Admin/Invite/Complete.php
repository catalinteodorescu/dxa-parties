<?php

namespace App\Livewire\Admin\Invite;

use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Complete extends Component
{
    public int $adminId;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(Admin $admin): void
    {
        $this->adminId = $admin->id;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $admin = Admin::findOrFail($this->adminId);

        $admin->update([
            'name' => $validated['name'],
            'password' => $validated['password'],
            'activated_at' => now(),
        ]);

        // DXA: adaugat (Utilizatori - acces pe aplicație): un cont fără acces la panou (ex. doar barman) își
        // activează contul, dar nu intră în panoul web — se întoarce la login cu un mesaj.
        if (! $admin->canAccess(Admin::APP_ADMIN)) {
            ActivityLogger::log('admin.invite.completed', 'Și-a completat contul și a fost activat.', actor: $admin);

            session()->flash('status', 'Contul tău a fost activat. Nu ai acces la panoul de administrare — folosește aplicația la care ai acces.');

            $this->redirectRoute('admin.login', navigate: true);

            return;
        }

        Auth::guard('admin')->login($admin);

        request()->session()->regenerate();

        ActivityLogger::log('admin.invite.completed', 'Și-a completat contul și a fost activat.', actor: $admin);

        $this->redirectRoute('admin.dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.invite.complete');
    }
}
