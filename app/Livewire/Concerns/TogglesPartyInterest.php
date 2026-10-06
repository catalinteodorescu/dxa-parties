<?php

namespace App\Livewire\Concerns;

use App\Models\Party;
use App\Services\PartyInterests;
use DomainException;

/**
 * DXA: adaugat (runda 40). Inima „la favorite” pentru componentele din aplicație (Acasă, Petreceri, pagina petrecerii).
 * Vizitatorul fără cont e trimis la login și se întoarce unde era. Componenta dă view-ului `savedIds` = $this->savedIds().
 */
trait TogglesPartyInterest
{
    public function toggleInterest(int $partyId): void
    {
        $me = auth('participant')->user();
        if (! $me) {
            $this->askLoginFor('Intră în cont ca să adaugi petreceri la favorite.');

            return;
        }

        $party = Party::query()->find($partyId);
        if (! $party || $party->isDraft() || ! $party->is_active) {
            return;
        }

        try {
            $saved = PartyInterests::toggle($me, $party);
            $this->dispatch('toast', message: $saved ? 'Petrecerea a fost adăugată la favorite.' : 'Petrecerea a fost scoasă din favorite.', type: 'ok');
        } catch (DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'err');
        }
    }

    /** @return array<int, int> */
    public function savedIds(): array
    {
        return PartyInterests::ids(auth('participant')->user());
    }

    protected function askLoginFor(string $message): void
    {
        session()->put('url.intended', request()->header('Referer') ?: route('app.parties'));
        session()->flash('status', $message);
        $this->redirectRoute('app.login', navigate: true);
    }
}
