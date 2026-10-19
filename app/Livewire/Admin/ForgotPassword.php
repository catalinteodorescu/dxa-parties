<?php

namespace App\Livewire\Admin;

use App\Contracts\SmsSender;
use App\Models\Admin;
use App\Services\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\RateLimiter;
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

        // DXA: runda 81 — cel mult 3 cereri pe oră per număr și 20 pe oră per IP (răspunsul rămâne același, ca să nu se afle ce numere există)
        $limited = RateLimiter::tooManyAttempts('admin-reset:'.sha1($this->phone), 3) || RateLimiter::tooManyAttempts('admin-reset-ip:'.request()->ip(), 20);
        RateLimiter::hit('admin-reset:'.sha1($this->phone), 3600);
        RateLimiter::hit('admin-reset-ip:'.request()->ip(), 3600);

        if ($admin && $admin->is_active && ! $limited) {
            $url = URL::temporarySignedRoute(
                'admin.reset-password',
                now()->addMinutes(60),
                ['admin' => $admin->id]
            );

            try {
                $sms->send($admin->phone, "Resetează-ți parola aici: {$url}");
            } catch (DomainException) {
                // limita anti-abuz: același răspuns neutru ca la un număr necunoscut
            }

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
