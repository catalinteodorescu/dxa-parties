<?php

namespace App\Livewire\Admin\Participants;

use App\Models\Participant;
use App\Models\PartyEntry;
use App\Services\ParticipantRegistry;
use App\Services\ParticipantStats;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * DXA: adaugat (Participanți - fundația). Fișa unui participant: date (editabile), numărul de intrări și istoricul lor.
 * Ștergerea e permisă doar fără intrări; altfel „Anonimizează” (golește numele și telefonul, păstrează numărătoarea).
 */
#[Layout('layouts.admin')]
class Show extends Component
{
    public Participant $participant;

    public string $name = '';

    public string $phone = '';

    public ?string $message = null;

    public ?string $error = null;

    public function mount(Participant $participant): void
    {
        $this->participant = $participant;
        $this->syncFields();
    }

    private function syncFields(): void
    {
        $this->name = $this->participant->isAnonymized() ? '' : $this->participant->name;
        $this->phone = (string) $this->participant->phone;
    }

    public function save(): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::update($this->participant, $this->name, $this->phone);
            $this->participant->refresh();
            $this->syncFields();
            $this->message = 'Datele au fost salvate.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function anonymize(): void
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::anonymize($this->participant);
            $this->participant->refresh();
            $this->syncFields();
            $this->message = 'Participantul a fost anonimizat.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function delete()
    {
        $this->message = $this->error = null;

        try {
            ParticipantRegistry::delete($this->participant);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        session()->flash('status', 'Participantul a fost șters.');

        return $this->redirectRoute('admin.participants.index', navigate: true);
    }

    public function render()
    {
        $entries = PartyEntry::query()
            ->where('participant_id', $this->participant->id)
            ->with('party:id,name,start_date')
            ->orderByDesc('entered_at')->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.admin.participants.show', [
            'spend' => ParticipantStats::for($this->participant),
            'entries' => $entries,
            'totalEntries' => $this->participant->entries()->count(),
            'validEntries' => (int) $this->participant->entries()->active()->count(),
            'paidEntries' => (int) $this->participant->entries()->active()->where('price_paid', '>', 0)->count(),
            'freeEntries' => (int) $this->participant->entries()->active()->where('price_paid', '<=', 0)->count(),
            'firstAt' => $this->participant->entries()->active()->min('entered_at'),
            'lastAt' => $this->participant->entries()->active()->max('entered_at'),
        ]);
    }
}
