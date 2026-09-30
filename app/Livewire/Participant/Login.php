<?php

namespace App\Livewire\Participant;

use App\Services\ParticipantAccounts;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Login: telefon + parolă (guard `participant`). Aceeași eroare pentru orice eșec.
 */
#[Layout('layouts.participant-guest', ['title' => 'Intră în cont'])]
class Login extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = true;

    public string $error = '';

    public function login(): void
    {
        $this->error = '';
        $this->validate(['phone' => ['required', 'string', 'max:40'], 'password' => ['required', 'string', 'max:200']], [
            'phone.required' => 'Scrie telefonul.',
            'password.required' => 'Scrie parola.',
        ]);

        try {
            ParticipantAccounts::attempt($this->phone, $this->password, $this->remember, request()->ip());
        } catch (DomainException $e) {
            $this->password = '';
            $this->error = $e->getMessage();

            return;
        }

        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('app.home')), navigate: true);
    }

    public function render()
    {
        return view('livewire.participant.login');
    }
}
