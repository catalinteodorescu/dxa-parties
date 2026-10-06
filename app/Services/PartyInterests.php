<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyInterest;
use DomainException;

/**
 * DXA: adaugat (runda 40, ajustat în 40c). Petrecerile salvate (inima): un participant cu cont salvează / scoate o petrecere. Se pot salva
 * petreceri publicate și active, inclusiv cele încheiate; scoaterea merge oricând. Numărul de „interesați” din admin numără toate salvările
 * (și pe cele la petreceri trecute). În lista „Salvate” din aplicație, petrecerile trecute apar doar dacă setarea „arată petreceri trecute” e activă.
 */
class PartyInterests
{
    /** Salvează sau scoate petrecerea. Întoarce true dacă acum e salvată. */
    public static function toggle(Participant $participant, Party $party): bool
    {
        $existing = PartyInterest::query()->where('participant_id', $participant->id)->where('party_id', $party->id);

        if ($existing->exists()) {
            $existing->delete();

            return false;
        }

        if ($party->isDraft() || ! $party->is_active) {
            throw new DomainException('Petrecerea nu mai poate fi adăugată la favorite.');
        }

        PartyInterest::query()->insertOrIgnore(['participant_id' => $participant->id, 'party_id' => $party->id, 'created_at' => now()]);

        return true;
    }

    /** @return array<int, int> id-urile petrecerilor salvate de participant */
    public static function ids(?Participant $participant): array
    {
        if (! $participant) {
            return [];
        }

        return PartyInterest::query()->where('participant_id', $participant->id)->pluck('party_id')->map(fn ($v) => (int) $v)->all();
    }

    public static function count(Party $party): int
    {
        return PartyInterest::query()->where('party_id', $party->id)->count();
    }
}
