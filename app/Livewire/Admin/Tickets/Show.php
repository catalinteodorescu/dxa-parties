<?php

namespace App\Livewire\Admin\Tickets;

use App\Models\PartyDiscountCode;
use App\Models\PartyEntry;
use App\Models\Ticket;
use App\Models\TicketTransfer;
use App\Services\AdminTickets;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (runda 66). Detaliul unui bilet: starea lui, comanda din care face parte (cu celelalte bilete), plata (inclusiv referința Stripe),
 * reducerea aplicată, trimiterile către alte conturi și intrarea de la Recepție. Runda 67: anulare cu returnare în credite (acțiunea sensibilă „Anulare bilete”).
 */
#[Layout('layouts.admin')]
class Show extends Component
{
    public Ticket $ticket;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket;
    }

    /** Anulează biletul (motiv obligatoriu); $refund = returnează prețul plătit în credite. */
    public function cancel(string $reason, bool $refund = true): void
    {
        $this->message = $this->error = null;

        try {
            $amount = AdminTickets::cancel($this->ticket, $reason, $refund, Auth::guard('admin')->id());
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->ticket->refresh();
        $this->message = $amount > 0
            ? 'Biletul a fost anulat. '.number_format($amount, 2, ',', '.').' lei au intrat în portofelul cumpărătorului, ca credite.'
            : 'Biletul a fost anulat.';
    }

    public function render()
    {
        $t = $this->ticket->load(['party', 'owner', 'holder', 'order.participant']);

        return view('livewire.admin.tickets.show', [
            't' => $t,
            'canCancel' => AdminTickets::canCancel($t),
            'refundable' => AdminTickets::refundable($t),
            'siblings' => Ticket::query()->where('order_id', $t->order_id)->where('id', '!=', $t->id)->with(['holder'])->orderBy('id')->get(),
            'transfers' => TicketTransfer::query()->where('ticket_id', $t->id)->with(['from', 'to'])->orderBy('created_at')->orderBy('id')->get(),
            'entry' => $t->party_entry_id ? PartyEntry::query()->find($t->party_entry_id) : null,
            'code' => $t->discount_code_id ? PartyDiscountCode::query()->find($t->discount_code_id) : null,
        ]);
    }
}
