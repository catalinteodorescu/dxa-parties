<?php

namespace App\Livewire\Participant;

use App\Contracts\SmsSender;
use App\Services\AccountDeletion;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 52). „Șterge contul”: pagină separată de confirmare. Arată ce se pierde (credite, bilete, fidelitate), cere parola și
 * o bifă „am înțeles”, apoi anonimizează contul (App\Services\AccountDeletion), deconectează și întoarce la Acasă cu un mesaj.
 */
#[Layout('layouts.participant', ['title' => 'Șterge contul'])]
class DeleteAccount extends Component
{
    public string $password = '';

    public bool $understood = false;

    public string $error = '';

    public function delete()
    {
        $this->error = '';
        $me = auth('participant')->user();

        if (! $this->understood) {
            $this->error = 'Bifează că ai înțeles ce se întâmplă.';

            return null;
        }

        try {
            AccountDeletion::delete($me, $this->password, app(SmsSender::class));
        } catch (DomainException $e) {
            $this->password = '';
            $this->error = $e->getMessage();

            return null;
        }

        Auth::guard('participant')->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Contul tău a fost șters.');

        return $this->redirectRoute('app.home', navigate: false);
    }

    public function render()
    {
        return view('livewire.participant.delete-account', [
            'summary' => AccountDeletion::summary(auth('participant')->user()),
        ]);
    }
}
