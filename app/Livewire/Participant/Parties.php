<?php

namespace App\Livewire\Participant;

use App\Models\Party;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Toate petrecerile publicate și active: cele următoare, apoi cele trecute (cele „doar logați” apar
 * doar cu cont). Lazy load la defilare (câte 10, separat pentru următoare și trecute).
 */
#[Layout('layouts.participant', ['title' => 'Petreceri'])]
class Parties extends Component
{
    public int $limit = 10;

    public int $pastLimit = 10;

    public function more(): void
    {
        $this->limit += 10;
    }

    public function morePast(): void
    {
        $this->pastLimit += 10;
    }

    public function render()
    {
        $audience = auth('participant')->check() ? 'auth' : 'all';

        $upcoming = Party::query()->visible($audience)->limit($this->limit + 1)->get();
        $past = Party::query()->visiblePast($audience)->limit($this->pastLimit + 1)->get();

        return view('livewire.participant.parties', [
            'parties' => $upcoming->take($this->limit),
            'hasMore' => $upcoming->count() > $this->limit,
            'past' => $past->take($this->pastLimit),
            'hasMorePast' => $past->count() > $this->pastLimit,
        ]);
    }
}
