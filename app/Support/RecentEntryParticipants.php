<?php

namespace App\Support;

/**
 * DXA: adaugat (PWA Recepție). Participanții din ultima intrare înregistrată în PWA, ca „scurtătură” pentru
 * ecranele de vânzare tokeni / credite: apar ca sugestii (un tap), niciodată precompletați.
 * Trăiesc în sesiune, doar pentru aceeași petrecere și doar 20 de minute (ca să nu se vândă din greșeală unui
 * participant vechi). După o vânzare, participantul iese din lista acelui tip de vânzare (tokeni / credite).
 */
class RecentEntryParticipants
{
    private const KEY = 'receptie.recent_entry';

    private const TTL_MINUTES = 20;

    public const TOKENS = 'tokens';

    public const CREDITS = 'credits';

    /** Reține participanții unei intrări noi (o listă goală șterge sugestiile vechi). */
    public static function put(int $partyId, array $participantIds): void
    {
        if ($participantIds === []) {
            session()->forget(self::KEY);

            return;
        }

        session([self::KEY => [
            'party' => $partyId,
            'at' => now()->timestamp,
            'ids' => array_values(array_unique(array_map('intval', $participantIds))),
            'sold' => [self::TOKENS => [], self::CREDITS => []],
        ]]);
    }

    /** @return array<int, int> participanții încă nevânduți pentru acest tip de vânzare */
    public static function for(int $partyId, string $kind): array
    {
        $data = session(self::KEY);

        if (! is_array($data) || ($data['party'] ?? null) !== $partyId
            || now()->timestamp - (int) ($data['at'] ?? 0) > self::TTL_MINUTES * 60) {
            return [];
        }

        return array_values(array_diff($data['ids'] ?? [], $data['sold'][$kind] ?? []));
    }

    public static function markSold(int $partyId, string $kind, ?int $participantId): void
    {
        $data = session(self::KEY);

        if ($participantId === null || ! is_array($data) || ($data['party'] ?? null) !== $partyId) {
            return;
        }

        $data['sold'][$kind][] = $participantId;
        session([self::KEY => $data]);
    }
}
