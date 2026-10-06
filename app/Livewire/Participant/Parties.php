<?php

namespace App\Livewire\Participant;

use App\Livewire\Concerns\TogglesPartyInterest;
use App\Models\Party;
use App\Support\ParticipantAppSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * DXA: adaugat (Aplicația participanților). Toate petrecerile publicate și active: cele următoare, apoi cele trecute (cele „doar logați” apar
 * doar cu cont). Lazy load la defilare (câte 10, separat pentru următoare și trecute).
 */
#[Layout('layouts.participant', ['title' => 'Petreceri'])]
class Parties extends Component
{
    use TogglesPartyInterest;

    /** all | saved — „Favorite” = petrecerile cu inimă (runda 40). */
    #[Url(as: 'lista')]
    public string $view = 'all';

    public int $limit = 10;

    public int $pastLimit = 10;

    public function showView(string $view): void
    {
        if ($view === 'saved' && ! auth('participant')->check()) {
            $this->askLoginFor('Intră în cont ca să vezi petrecerile favorite.');

            return;
        }

        $this->view = $view === 'saved' ? 'saved' : 'all';
        $this->limit = $this->pastLimit = 10;
    }

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

        $saved = $this->savedIds();
        $onlySaved = $this->view === 'saved' && auth('participant')->check();

        $upcoming = Party::query()->visible($audience)->when($onlySaved, fn ($q) => $q->whereIn('id', $saved))->limit($this->limit + 1)->get();
        $past = ParticipantAppSettings::showPastParties()
            ? Party::query()->visiblePast($audience)->when($onlySaved, fn ($q) => $q->whereIn('id', $saved))->limit($this->pastLimit + 1)->get()
            : collect();

        return view('livewire.participant.parties', [
            'savedIds' => $saved,
            'onlySaved' => $onlySaved,
            'parties' => $upcoming->take($this->limit),
            'hasMore' => $upcoming->count() > $this->limit,
            'past' => $past->take($this->pastLimit),
            'hasMorePast' => $past->count() > $this->pastLimit,
        ]);
    }
}
