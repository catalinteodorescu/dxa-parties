<?php

namespace App\Livewire\Participant;

use App\Models\CreditTopup;
use App\Services\CreditTopups;
use App\Services\Payments\PaymentGateway;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 51, refăcut în runda 64). Pagina unei încărcări de credite în așteptarea plății.
 * - Cu Stripe activ: „Plătește cu cardul” duce la pagina Stripe; la întoarcere (?plata=ok) pagina așteaptă webhook-ul (se reîmprospătează singură)
 *   și, când încărcarea e plătită, duce la Portofel cu mesajul de succes. Întoarcerea NU creditează nimic: doar webhook-ul semnat.
 * - Fără Stripe: loc rezervat „plata urmează”, fără să se retragă nimic.
 */
#[Layout('layouts.participant', ['title' => 'Plata cu cardul'])]
class TopupPay extends Component
{
    public CreditTopup $topup;

    public bool $returning = false;

    public string $error = '';

    public function mount(CreditTopup $topup): void
    {
        abort_unless($topup->participant_id === auth('participant')->id(), 404);
        $this->topup = $topup;
        $this->returning = request()->query('plata') === 'ok';

        $this->finishIfPaid();
    }

    public function pay(PaymentGateway $gateway)
    {
        $this->error = '';
        CreditTopups::expireStale();
        $this->topup->refresh();
        if (! $this->topup->isPending()) {
            return null;
        }

        try {
            $url = $gateway->checkoutUrl($this->topup);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirect($url);   // pagină pe alt domeniu: redirecționare completă
    }

    /** Apelată de reîmprospătarea periodică după întoarcerea de la Stripe. */
    public function check(): void
    {
        $this->topup->refresh();
        $this->finishIfPaid();
    }

    public function cancel(): void
    {
        CreditTopups::cancel($this->topup);   // fără mesaj de succes: renunțarea e silențioasă
        $this->redirectRoute('app.wallet', navigate: true);
    }

    private function finishIfPaid(): void
    {
        if ($this->topup->status === CreditTopup::PAID) {
            session()->flash('status', $this->returning ? CreditTopups::paidMessage($this->topup) : 'Încărcarea a fost plătită.');
            $this->redirectRoute('app.wallet', navigate: true);
        }
    }

    public function render(PaymentGateway $gateway)
    {
        CreditTopups::expireStale();
        $this->topup->refresh();

        return view('livewire.participant.topup-pay', ['topup' => $this->topup, 'online' => $gateway->online()]);
    }
}
