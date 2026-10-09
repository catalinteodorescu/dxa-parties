<?php

namespace App\Livewire\Participant;

use App\Models\CreditTransaction;
use App\Models\PartyEntry;
use App\Models\Sale;
use App\Models\Ticket;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților - runda 16). Ecranele „Vezi tot”: toate biletele, toate tranzacțiile din portofel (încărcări, plăți, refund-uri, ajustări),
 * toate intrările și toate consumațiile de la bar ale contului meu. Aceeași componentă, `kind` vine din rută. Lista crește
 * cu lazy load (câte 20, la defilare). Doar citire.
 */
#[Layout('layouts.participant', ['title' => 'Istoric'])]
class History extends Component
{
    public const KINDS = [
        'tickets' => ['Toate biletele', 'app.tickets'],
        'credits' => ['Toate tranzacțiile', 'app.wallet'],
        'entries' => ['Toate intrările', 'app.account'],
        'bar' => ['Toate consumațiile de la bar', 'app.account'],
    ];

    public string $kind = 'tickets';

    public int $limit = 20;

    public function mount(string $kind = 'tickets'): void
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $this->kind = $kind;
    }

    public function more(): void
    {
        $this->limit += 20;
    }

    public function render()
    {
        $me = auth('participant')->user();
        $take = $this->limit + 1;

        $rows = match ($this->kind) {
            'tickets' => Ticket::query()->where('status', '!=', Ticket::PENDING)->visibleTo($me->id)->with(['party', 'holder'])->orderByDesc('id')->limit($take)->get(),
            'credits' => CreditTransaction::query()->where('participant_id', $me->id)->whereNull('cancelled_at')
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit($take)->get(),
            'entries' => PartyEntry::query()->active()->where('participant_id', $me->id)->with('party')->orderByDesc('entered_at')->orderByDesc('id')->limit($take)->get(),
            'bar' => Sale::query()->where('customer_id', $me->id)->where('status', 'completed')->with(['lines.menuItem', 'group.party'])
                ->orderByDesc('sold_at')->orderByDesc('id')->limit($take)->get(),
        };

        return view('livewire.participant.history', [
            'title' => self::KINDS[$this->kind][0],
            'back' => route(self::KINDS[$this->kind][1]),
            'rows' => $rows->take($this->limit),
            'hasMore' => $rows->count() > $this->limit,
        ]);
    }
}
