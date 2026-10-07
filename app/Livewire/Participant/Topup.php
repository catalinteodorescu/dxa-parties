<?php

namespace App\Livewire\Participant;

use App\Services\CreditBonus;
use App\Services\CreditTopups;
use App\Services\PaymentRows;
use App\Services\Payments\PaymentGateway;
use App\Support\PaymentMethods;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 51, refăcut în 51b). Pagina „Încarcă credite”: alegerea sumei (sume rapide + sumă liberă), bonusul la prag și „Confirmă”.
 * Cât timp plata online e simulată (config app.dxa_tickets_auto_paid, ca la biletele cumpărate online), „Confirmă” creditează direct contul
 * (CreditTopups::complete). Altfel trimite la pagina de plată a furnizorului (PaymentGateway; acum un loc rezervat). „Renunță” doar întoarce la Portofel.
 */
#[Layout('layouts.participant', ['title' => 'Încarcă credite'])]
class Topup extends Component
{
    public string $amount = '';

    public string $error = '';

    public function mount()
    {
        if (! PaymentMethods::creditsPurchasable()) {
            return $this->redirectRoute('app.wallet', navigate: true);
        }
    }

    public function setAmount(int $n): void
    {
        $this->amount = (string) $n;
        $this->error = '';
    }

    public function confirm(PaymentGateway $gateway)
    {
        $this->error = '';
        $me = auth('participant')->user();

        try {
            $topup = CreditTopups::start($me, self::parse($this->amount));

            if (config('app.dxa_tickets_auto_paid')) {
                // Plată simulată (până la Stripe): creditele intră direct în cont, ca la comenzile de bilete marcate achitate.
                CreditTopups::complete($topup, null, 'simulated');
                session()->flash('status', $topup->bonus > 0
                    ? 'Ai încărcat '.PaymentRows::money((float) $topup->amount).' lei credite și ai primit bonus '.PaymentRows::money((float) $topup->bonus).' lei.'
                    : 'Ai încărcat '.PaymentRows::money((float) $topup->amount).' lei credite.');

                return $this->redirectRoute('app.wallet', navigate: true);
            }
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirect($gateway->checkoutUrl($topup), navigate: true);
    }

    private static function parse(string $raw): float
    {
        $raw = str_replace(',', '.', trim($raw));

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    public function render()
    {
        $quote = null;
        $quoteError = '';
        if (trim($this->amount) !== '') {
            try {
                $quote = CreditTopups::quote(self::parse($this->amount));
            } catch (DomainException $e) {
                $quoteError = $e->getMessage();
            }
        }

        return view('livewire.participant.topup', [
            'presets' => CreditBonus::presets(),
            'tiers' => CreditBonus::tiers(),
            'quote' => $quote,
            'quoteError' => $quoteError,
            'min' => CreditBonus::min(),
            'max' => CreditBonus::max(),
        ]);
    }
}
