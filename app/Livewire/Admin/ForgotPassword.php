<?php

namespace App\Livewire\Admin;

use App\Contracts\SmsSender;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ForgotPassword extends Component
{
    public string $phone = '';

    public bool $sent = false;

    public function send(SmsSender $sms): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ]);

        $admin = Admin::where('phone', $this->phone)->first();

        if ($admin && $admin->is_active) {
            $url = URL::temporarySignedRoute(
                'admin.reset-password',
                now()->addMinutes(60),
                ['admin' => $admin->id]
            );

            $sms->send($admin->phone, "Resetează-ți parola aici: {$url}");

            ActivityLogger::log(
                'admin.password.reset_requested',
                'A cerut resetarea parolei prin SMS.',
                $admin,
                actor: null,
            );
        } else {
            ActivityLogger::log(
                'admin.password.reset_requested_unknown',
                'S-a cerut resetarea parolei pentru un număr necunoscut sau inactiv ('.$this->phone.').',
                actor: null,
            );
        }

        // Mesaj identic indiferent dacă numărul există, e activ etc.
        // (nu vrem să dezvăluim starea contului din motive de securitate).
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.admin.forgot-password');
    }
}
