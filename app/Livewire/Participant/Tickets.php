<?php

namespace App\Livewire\Participant;

use App\Models\Ticket;
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

    public function more(): void
    {
        $this->limit += 10;
    }

    public function render()
    {
        $me = auth('participant')->user();
        $base = fn () => Ticket::query()->visibleTo($me->id)->with(['party', 'holder']);

        // Biletele „valabile” ale unei petreceri încheiate nu mai sunt valabile: trec în lista de jos, ca „expirate” (runda 32).
        [$past, $valid] = $base()->where('status', Ticket::VALID)->orderBy('id')->get()
            ->partition(fn (Ticket $t) => $t->party?->state() === 'past');

        return view('livewire.participant.tickets', [
            'valid' => $valid->values(),
            'past' => $past->sortByDesc('id')->values(),
            'recent' => $base()->where('status', '!=', Ticket::VALID)->orderByDesc('id')->limit($this->limit)->get(),
            'hasMore' => $base()->where('status', '!=', Ticket::VALID)->count() > $this->limit,
        ]);
    }
}
