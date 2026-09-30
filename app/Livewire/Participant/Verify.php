<?php

namespace App\Livewire\Participant;

use App\Contracts\SmsSender;
use App\Services\ParticipantAccounts;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Pasul 2 al înregistrării: codul de 6 cifre primit prin SMS. La succes contul e
 * activ (nou sau legat de participantul creat la Recepție) și utilizatorul intră direct în aplicație.
 */
#[Layout('layouts.participant-guest', ['title' => 'Confirmă telefonul'])]
class Verify extends Component
{
    public string $phone = '';

    public string $code = '';

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        $phone = (string) session(Register::VERIFY_SESSION_KEY, '');

        if ($phone === '' || ! ParticipantAccounts::hasPending($phone)) {
            session()->forget(Register::VERIFY_SESSION_KEY);
            $this->redirectRoute('app.register', navigate: true);

            return;
        }

        $this->phone = $phone;
    }

    public function verify(): void
    {
        $this->error = $this->notice = '';
        $this->validate(['code' => ['required', 'string', 'max:12']], ['code.required' => 'Scrie codul din SMS.']);

        try {
            $participant = ParticipantAccounts::verifyRegistration($this->phone, $this->code);
        } catch (DomainException $e) {
            $this->code = '';
            $this->error = $e->getMessage();

            return;
        }

        Auth::guard('participant')->login($participant, true);
        session()->regenerate();
        session()->forget(Register::VERIFY_SESSION_KEY);
        session()->flash('status', 'Contul tău e activ. Bine ai venit!');

        $this->redirect(session()->pull('url.intended', route('app.home')), navigate: true);
    }

    public function resend(SmsSender $sms): void
    {
        $this->error = $this->notice = '';

        try {
            ParticipantAccounts::resendCode($this->phone, $sms);
            $this->notice = 'Ți-am trimis un cod nou.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.participant.verify', [
            'wait' => $this->phone !== '' ? ParticipantAccounts::resendWaitSeconds($this->phone) : 0,
            'masked' => $this->phone !== '' ? substr($this->phone, 0, 3).' ••• '.substr($this->phone, -3) : '',
        ]);
    }
}
