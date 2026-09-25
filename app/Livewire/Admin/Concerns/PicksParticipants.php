<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\Participant;
use App\Models\Party;
use App\Services\ParticipantRegistry;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * DXA: adaugat (Participanți). Alegerea participanților într-un formular Livewire: căutare după telefon/nume,
 * „+ Participant nou” (nume + telefon), chip-uri cu ×. Se folosește la Intrări (mai mulți), vânzarea de tokeni și
 * vânzarea de la bar (unul singur). Interfața e în partialul livewire.admin._participant-picker.
 *
 * Componenta folosește starea din $participantIds; erorile picker-ului se afișează în picker ($participantError).
 * Cârlige: participantLimit() (implicit 1: alegerea unui al doilea îl înlocuiește pe primul) și participantsChanged().
 */
trait PicksParticipants
{
    /** @var array<int, int> */
    public array $participantIds = [];

    public string $participantSearch = '';

    public bool $newParticipant = false;

    public string $newName = '';

    public string $newPhone = '';

    public ?string $participantError = null;

    /** Câți participanți se pot alege (1 = un singur client). */
    protected function participantLimit(): int
    {
        return 1;
    }

    /** Cârlig apelat după ce lista s-a schimbat (ex. Intrări ajustează numărul de persoane). */
    protected function participantsChanged(): void
    {
        // implicit: nimic
    }

    public function addParticipant(int $id): void
    {
        $this->participantError = null;

        if (in_array($id, $this->participantIds, true)) {
            return;
        }

        try {
            ParticipantRegistry::usable($id);
        } catch (DomainException $e) {
            $this->participantError = $e->getMessage();

            return;
        }

        if ($this->participantLimit() === 1) {
            $this->participantIds = [$id];   // un singur client: cel nou îl înlocuiește pe cel vechi
        } elseif (count($this->participantIds) >= $this->participantLimit()) {
            $this->participantError = 'Cel mult '.$this->participantLimit().' persoane odată.';

            return;
        } else {
            $this->participantIds[] = $id;
        }

        $this->participantSearch = '';
        $this->participantsChanged();
    }

    public function removeParticipant(int $id): void
    {
        $this->participantIds = array_values(array_filter($this->participantIds, fn ($p) => $p !== $id));
        $this->participantsChanged();
    }

    public function toggleNewParticipant(): void
    {
        $this->newParticipant = ! $this->newParticipant;
        $this->participantError = null;

        // Dacă tocmai ai căutat un telefon, îl preiei în formular.
        if ($this->newParticipant && $this->newPhone === '' && preg_match('/^[\d\s+()-]{6,}$/', trim($this->participantSearch))) {
            $this->newPhone = trim($this->participantSearch);
        }
    }

    /** Creează un participant nou (nume + telefon) și îl alege. */
    public function createParticipant(): void
    {
        $this->participantError = null;

        try {
            $participant = ParticipantRegistry::create($this->newName, $this->newPhone, Auth::guard('admin')->id());
        } catch (DomainException $e) {
            $this->participantError = $e->getMessage();

            return;
        }

        $this->newName = $this->newPhone = '';
        $this->newParticipant = false;
        $this->addParticipant($participant->id);
    }

    protected function resetParticipants(): void
    {
        $this->participantIds = [];
        $this->participantSearch = '';
        $this->newParticipant = false;
        $this->newName = $this->newPhone = '';
        $this->participantError = null;
    }

    /**
     * Datele pentru partialul _participant-picker: alesii, rezultatele căutării, numărul de intrări și, opțional,
     * avertismentul „a intrat deja” (doar la Intrări).
     *
     * @return array{chosenParticipants: \Illuminate\Support\Collection, participantResults: \Illuminate\Support\Collection, participantVisits: array<int, int>, participantDupes: array<int, string>}
     */
    protected function participantPickerData(?Party $party = null, bool $checkDuplicates = false): array
    {
        $chosen = $this->participantIds
            ? Participant::query()->whereIn('id', $this->participantIds)->get()->keyBy('id')
            : collect();
        $chosenParticipants = collect($this->participantIds)->map(fn ($id) => $chosen->get($id))->filter()->values();
        $results = ParticipantRegistry::search($this->participantSearch, 6, $this->participantIds);

        $dupes = [];
        if ($checkDuplicates && $party) {
            foreach ($chosenParticipants as $p) {
                if ($prev = ParticipantRegistry::enteredInSession($p, $party)) {
                    $dupes[$p->id] = $prev->entered_at->format('H:i');
                }
            }
        }

        return [
            'chosenParticipants' => $chosenParticipants,
            'participantResults' => $results,
            'participantVisits' => ParticipantRegistry::visitCounts($chosenParticipants->pluck('id')->merge($results->pluck('id'))->all()),
            'participantDupes' => $dupes,
        ];
    }
}
