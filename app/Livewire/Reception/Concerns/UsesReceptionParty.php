<?php

namespace App\Livewire\Reception\Concerns;

use App\Models\Party;
use App\Support\ReceptionApp;

/**
 * DXA: adaugat (PWA Recepție). Petrecerea la care lucrează recepționerul. Se alege pe un ecran separat
 * (Reception\PartyPicker, după login) și se reține în sesiune; toate celelalte ecrane o citesc de aici.
 * Dacă nu e aleasă (sau nu mai e disponibilă: încheiată, ciornă, dezactivată), utilizatorul e trimis la alegere.
 */
trait UsesReceptionParty
{
    public ?int $partyId = null;

    /** Memo pe request (private: nu se serializează în Livewire). */
    private $selectableParties = null;

    public function mountUsesReceptionParty(): void
    {
        $saved = (int) session(ReceptionApp::PARTY_SESSION_KEY);
        $this->partyId = $this->selectableParties()->firstWhere('id', $saved)?->id;

        if ($this->partyId === null) {
            $this->redirectRoute('receptie.party', navigate: true);
        }
    }

    protected function selectableParties()
    {
        return $this->selectableParties ??= Party::query()->forReception()->limit(30)->get();
    }

    protected function currentParty(): ?Party
    {
        return $this->partyId ? $this->selectableParties()->firstWhere('id', $this->partyId) : null;
    }
}
