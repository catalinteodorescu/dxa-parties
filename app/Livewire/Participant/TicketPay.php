<?php

namespace App\Livewire\Participant;

use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use App\Services\TicketOrders;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 65). Întoarcerea de la plata cu cardul a unei comenzi de bilete (/bilete/plata/{order}).
 * - `?plata=ok` și comanda încă în așteptare: „Se confirmă plata…” cu reîmprospătare; când webhook-ul activează biletele, ducem la Bilete.
 * - Fără `plata=ok` (a renunțat la Stripe): poate relua plata cât rezervarea e valabilă, sau renunța (biletele se eliberează).
 * - Rezervarea expirată / plata eșuată: mesaj clar, nu s-a retras nimic (sau, la plată târzie fără locuri, suma e în portofel).
 * Întoarcerea NU activează nimic: doar webhook-ul semnat.
 */
#[Layout('layouts.participant', ['title' => 'Plata biletelor'])]
class TicketPay extends Component
{
    public Order $order;

    public bool $returning = false;

    public string $error = '';

    public function mount(Order $order): void
    {
        abort_unless($order->participant_id === auth('participant')->id(), 404);
        $this->order = $order;
        $this->returning = request()->query('plata') === 'ok';

        TicketOrders::expireStalePending();
        $this->order->refresh();
        $this->finishIfPaid();
    }

    public function pay(PaymentGateway $gateway)
    {
        $this->error = '';
        TicketOrders::expireStalePending();
        $this->order->refresh();
        if (! $this->order->isAwaitingCard()) {
            return null;
        }

        try {
            $url = $gateway->orderCheckoutUrl($this->order);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirect($url);   // pagină pe alt domeniu: redirecționare completă
    }

    /** Apelată de reîmprospătarea periodică după întoarcerea de la Stripe. */
    public function check(): void
    {
        TicketOrders::expireStalePending();
        $this->order->refresh();
        $this->finishIfPaid();
    }

    /** Renunță: biletele rezervate se eliberează (doar cât comanda e încă în așteptare). */
    public function cancel(): void
    {
        TicketOrders::releaseCard($this->order);
        $this->redirectRoute('app.tickets', navigate: true);
    }

    private function finishIfPaid(): void
    {
        if ($this->order->isPaidByCard()) {
            $n = $this->order->tickets()->count();
            session()->flash('status', $n === 1 ? 'Plata a reușit. Biletul tău e gata! Îl găsești la Bilete.' : 'Plata a reușit. Cele '.$n.' bilete sunt gata! Le găsești la Bilete, iar pe cele libere le poți trimite altcuiva.');
            $this->redirectRoute('app.tickets', navigate: true);
        }
    }

    public function render()
    {
        $this->order->refresh()->load('party');

        return view('livewire.participant.ticket-pay', [
            'order' => $this->order,
            'online' => app(PaymentGateway::class)->online(),
        ]);
    }
}
