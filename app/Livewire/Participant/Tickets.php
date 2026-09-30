<?php

namespace App\Livewire\Participant;

use App\Models\Ticket;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16). Pagina „Bilete”: biletele valabile (carusel cu QR, câte unul pe ecran),
 * apoi ultimele 5 bilete folosite / anulate, cu „Vezi mai mult” spre lista completă. Doar citire.
 */
#[Layout('layouts.participant', ['title' => 'Bilete'])]
class Tickets extends Component
{
    public function render()
    {
        $me = auth('participant')->user();
        $base = fn () => Ticket::query()->where('owner_participant_id', $me->id)->with(['party', 'holder']);

        return view('livewire.participant.tickets', [
            'valid' => $base()->where('status', Ticket::VALID)->orderBy('id')->get(),
            'recent' => $base()->where('status', '!=', Ticket::VALID)->orderByDesc('id')->limit(5)->get(),
            'hasMore' => $base()->where('status', '!=', Ticket::VALID)->count() > 5,
        ]);
    }
}
