<?php

namespace App\Livewire\Participant;

use App\Contracts\SmsSender;
use App\Models\Ticket;
use App\Services\TicketTransfers;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16). Pagina „Bilete”: biletele valabile (carusel cu QR, câte unul pe ecran),
 * apoi biletele expirate (petrecere încheiată), folosite / anulate, cu lazy load la defilare (câte 10). Doar citire.
 */
#[Layout('layouts.participant', ['title' => 'Bilete'])]
class Tickets extends Component
{
    public int $limit = 10;

    /** „Trimite biletul” (runda 39): biletul ales, telefonul, pasul (form|confirm), eroarea și destinatarul de confirmat. */
    public ?int $sendId = null;

    public string $sendPhone = '';

    public string $sendStep = 'form';

    public string $sendError = '';

    /** @var array{phone?: string, name?: ?string, has_account?: bool} */
    public array $sendInfo = [];

    public function more(): void
    {
        $this->limit += 10;
    }

    public function startSend(int $id): void
    {
        $me = auth('participant')->user();
        $ticket = Ticket::query()->visibleTo($me->id)->with('party')->find($id);

        if (! $ticket || ! TicketTransfers::canSend($ticket, $me)) {
            $this->dispatch('toast', message: 'Biletul nu mai poate fi trimis.', type: 'err');

            return;
        }

        $this->reset(['sendPhone', 'sendError', 'sendInfo']);
        $this->sendStep = 'form';
        $this->sendId = $id;
    }

    public function cancelSend(): void
    {
        $this->reset(['sendId', 'sendPhone', 'sendError', 'sendInfo']);
        $this->sendStep = 'form';
    }

    public function backSend(): void
    {
        $this->sendStep = 'form';
        $this->sendError = '';
    }

    /** Pasul 1: telefonul e valid? Arată cine primește biletul (nume dacă are cont) și cere confirmarea. */
    public function checkSend(): void
    {
        $this->sendError = '';

        try {
            $info = TicketTransfers::recipient($this->sendPhone, auth('participant')->user());
        } catch (DomainException $e) {
            $this->sendError = $e->getMessage();

            return;
        }

        $this->sendInfo = ['phone' => $info['phone'], 'name' => $info['participant']?->name, 'has_account' => $info['has_account']];
        $this->sendStep = 'confirm';
    }

    /** Pasul 2: trimite biletul (și SMS-ul, dacă destinatarul nu are cont). */
    public function confirmSend(SmsSender $sms): void
    {
        $this->sendError = '';

        try {
            $r = TicketTransfers::send((int) $this->sendId, auth('participant')->user(), (string) ($this->sendInfo['phone'] ?? ''), $sms);
        } catch (DomainException $e) {
            $this->sendError = $e->getMessage();
            $this->sendStep = 'form';

            return;
        }

        $this->cancelSend();

        if ($r['has_account']) {
            $this->dispatch('toast', message: 'Biletul a fost trimis în contul lui '.$r['recipient']->name.'.', type: 'ok');
        } elseif ($r['sms']) {
            $this->dispatch('toast', message: 'Biletul a fost trimis. '.$r['recipient']->phone.' primește un SMS cu linkul către bilet.', type: 'ok');
        } else {
            $this->dispatch('toast', message: 'Biletul a fost trimis, dar SMS-ul către '.$r['recipient']->phone.' nu a putut fi trimis.', type: 'err');
        }
    }

    public function render()
    {
        $me = auth('participant')->user();
        $base = fn () => Ticket::query()->visibleTo($me->id)->with(['party', 'holder']);

        // Biletele „valabile” ale unei petreceri încheiate nu mai sunt valabile: trec în lista de jos, ca „expirate” (runda 32).
        [$past, $valid] = $base()->where('status', Ticket::VALID)->orderBy('id')->get()
            ->partition(fn (Ticket $t) => $t->party?->state() === 'past');

        $sendTicket = $this->sendId ? $base()->find($this->sendId) : null;

        return view('livewire.participant.tickets', [
            'sendTicket' => $sendTicket && TicketTransfers::canSend($sendTicket, $me) ? $sendTicket : null,
            'valid' => $valid->values(),
            'past' => $past->sortByDesc('id')->values(),
            'recent' => $base()->where('status', '!=', Ticket::VALID)->orderByDesc('id')->limit($this->limit)->get(),
            'hasMore' => $base()->where('status', '!=', Ticket::VALID)->count() > $this->limit,
        ]);
    }
}
