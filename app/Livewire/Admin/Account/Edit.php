<?php

namespace App\Livewire\Admin\Account;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Edit extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $profileUpdated = false;

    public bool $passwordUpdated = false;

    public function mount(): void
    {
        $admin = Auth::guard('admin')->user();

        $this->name = $admin->name ?? '';
        $this->phone = $admin->phone;
    }

    public function updateProfile(): void
    {
        $admin = Auth::guard('admin')->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'regex:/^07[0-9]{8}$/',
                Rule::unique('admins', 'phone')->ignore($admin->id),
            ],
        ], [
            'phone.regex' => 'Numărul trebuie să fie de forma 07XXXXXXXX.',
        ]);

        $admin->update($validated);

        ActivityLogger::log('admin.profile.updated', 'Și-a actualizat numele și telefonul.', actor: $admin);

        $this->profileUpdated = true;
        $this->passwordUpdated = false;
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $admin = Auth::guard('admin')->user();

        $admin->update([
            'password' => $validated['password'],
        ]);

        ActivityLogger::log('admin.password.changed', 'Și-a schimbat parola.', actor: $admin);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->passwordUpdated = true;
        $this->profileUpdated = false;
    }

    public function render()
    {
        return view('livewire.admin.account.edit');
    }
}
