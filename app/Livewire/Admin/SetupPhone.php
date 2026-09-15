<?php

namespace App\Livewire\Admin;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class SetupPhone extends Component
{
    public string $phone = '';

    public function save(): void
    {
        $admin = Auth::guard('admin')->user();

        $validated = $this->validate([
            'phone' => [
                'required',
                'regex:/^07[0-9]{8}$/',
                Rule::unique('admins', 'phone')->ignore($admin->id),
            ],
        ], [
            'phone.regex' => 'Numărul trebuie să fie de forma 07XXXXXXXX.',
        ]);

        $admin->update([
            'phone' => $validated['phone'],
            'phone_needs_setup' => false,
        ]);

        ActivityLogger::log(
            'admin.phone.setup',
            'A completat telefonul de login: '.$validated['phone'].'.',
            actor: $admin,
        );

        $this->redirectRoute('admin.dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.setup-phone');
    }
}
