<?php

namespace App\Livewire\Participant;

use App\Contracts\SmsSender;
use App\Services\ParticipantAccounts;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Resetare parolă: linkul semnat vine prin SMS (ca la admin). Mesajul e același
 * indiferent dacă numărul are cont (nu dezvăluim starea contului).
 */
#[Layout('layouts.participant-guest', ['title' => 'Parolă uitată'])]
class ForgotPassword extends Component
{
    public string $phone = '';

    public bool $sent = false;

    public function send(SmsSender $sms): void
    {
        $this->validate(['phone' => ['required', 'string', 'max:40']], ['phone.required' => 'Scrie telefonul.']);

        ParticipantAccounts::sendResetLink($this->phone, $sms);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.participant.forgot-password');
    }
}
