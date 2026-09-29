<?php

namespace App\Livewire\Bar\Concerns;

use App\Models\Party;
use App\Support\BarApp;

/**
 * DXA: adaugat (PWA Bar). Petrecerea la care lucrează barmanul. Se alege pe un ecran separat (Bar\PartyPicker, după login)
 * și se reține în sesiune; toate celelalte ecrane o citesc de aici. Dacă nu e aleasă (sau nu mai e disponibilă: ciornă,
 * dezactivată, încheiată fără sesiune deschisă), utilizatorul e trimis la alegere.
 */
trait UsesBarParty
{
    public ?int $partyId = null;

    /** Memo pe request (private: nu se serializează în Livewire). */
    private $selectableParties = null;

    public function mountUsesBarParty(): void
    {
        $saved = (int) session(BarApp::PARTY_SESSION_KEY);
        $this->partyId = $this->selectableParties()->firstWhere('id', $saved)?->id;

        if ($this->partyId === null) {
            $this->redirectRoute('bar.party', navigate: true);
        }
    }

    protected function selectableParties()
    {
        return $this->selectableParties ??= Party::query()->forBar()->limit(30)->get();
    }

    protected function currentParty(): ?Party
    {
        return $this->partyId ? $this->selectableParties()->firstWhere('id', $this->partyId) : null;
    }
}
