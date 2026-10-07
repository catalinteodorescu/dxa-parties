<?php

namespace App\Livewire\Participant;

use App\Models\CreditTopup;
use App\Services\CreditTopups;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 51). Pagina de plată a unei încărcări de credite. Deocamdată e un loc rezervat: plata cu cardul (Stripe) urmează,
 * iar aici nu se retrage nimic. Arată ce a fost reținut (suma, bonusul, creditele) și lasă participantul să se întoarcă sau să renunțe.
 * Când vine Stripe, această pagină e înlocuită de plata găzduită; finalizarea trece prin CreditTopups::complete().
 */
#[Layout('layouts.participant', ['title' => 'Plata cu cardul'])]
class TopupPay extends Component
{
    public CreditTopup $topup;

    public function mount(CreditTopup $topup): void
    {
        abort_unless($topup->participant_id === auth('participant')->id(), 404);

        if ($topup->status === CreditTopup::PAID) {
            session()->flash('status', 'Încărcarea a fost plătită.');
            $this->redirectRoute('app.wallet', navigate: true);

            return;
        }

        $this->topup = $topup;
    }

    public function cancel(): void
    {
        CreditTopups::cancel($this->topup);   // fără mesaj de succes: renunțarea e silențioasă
        $this->redirectRoute('app.wallet', navigate: true);
    }

    public function render()
    {
        CreditTopups::expireStale();
        $this->topup->refresh();

        return view('livewire.participant.topup-pay', ['topup' => $this->topup]);
    }
}
