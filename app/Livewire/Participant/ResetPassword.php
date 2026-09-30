<?php

namespace App\Livewire\Participant;

use App\Models\Participant;
use App\Services\ParticipantAccounts;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Parolă nouă, din linkul semnat primit prin SMS. Linkul se stinge după folosire
 * (amprenta din `v` depinde de parola curentă).
 */
#[Layout('layouts.participant-guest', ['title' => 'Parolă nouă'])]
class ResetPassword extends Component
{
    public int $participantId;

    public string $stamp = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(Participant $participant): void
    {
        $stamp = (string) request()->query('v', '');
        abort_unless(ParticipantAccounts::resetLinkValid($participant, $stamp), 403, 'Linkul de resetare nu mai este valabil.');

        $this->participantId = $participant->id;
        $this->stamp = $stamp;
    }

    public function save(): void
    {
        $this->error = '';
        $this->validate(['password' => ['required', 'string', 'confirmed']], [
            'password.required' => 'Alege o parolă.',
            'password.confirmed' => 'Parolele nu coincid.',
        ]);

        try {
            ParticipantAccounts::resetPassword(Participant::findOrFail($this->participantId), $this->stamp, $this->password);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('status', 'Parola a fost schimbată. Poți intra în cont.');

        $this->redirectRoute('app.login', navigate: true);
    }

    public function render()
    {
        return view('livewire.participant.reset-password');
    }
}
