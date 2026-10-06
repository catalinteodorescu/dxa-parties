<?php

namespace App\Livewire\Participant;

use App\Contracts\SmsSender;
use App\Services\ParticipantAccounts;
use App\Support\ParticipantAppSettings;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Pasul 1 al înregistrării: nume + telefon + parolă, apoi codul de 6 cifre prin SMS.
 * Participantul se creează / se leagă abia la confirmarea codului (Verify).
 */
#[Layout('layouts.participant-guest', ['title' => 'Cont nou'])]
class Register extends Component
{
    public const VERIFY_SESSION_KEY = 'app.verify_phone';

    public string $name = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(): void
    {
        $this->phone = (string) request()->query('telefon', '');   // din linkul SMS „ți-a trimis un bilet” (runda 39)

        if (! ParticipantAppSettings::registrationOpen()) {
            $this->error = 'Înregistrarea conturilor noi este închisă momentan.';
        }
    }

    public function register(SmsSender $sms): void
    {
        $this->error = '';
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'confirmed'],
        ], [
            'name.required' => 'Scrie numele tău.',
            'phone.required' => 'Scrie telefonul.',
            'password.required' => 'Alege o parolă.',
            'password.confirmed' => 'Parolele nu coincid.',
        ]);

        try {
            $phone = ParticipantAccounts::startRegistration($this->name, $this->phone, $this->password, $sms);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session([self::VERIFY_SESSION_KEY => $phone]);

        $this->redirectRoute('app.verify', navigate: true);
    }

    public function render()
    {
        return view('livewire.participant.register');
    }
}
