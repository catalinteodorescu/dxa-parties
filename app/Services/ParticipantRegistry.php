<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Party;
use App\Models\PartyEntry;
use App\Models\ReceptionSession;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Collection;

/**
 * DXA: adaugat (Participanți - fundația). Singura cale de a crea, modifica, șterge și anonimiza participanți
 * (admin acum, PWA mai târziu). Telefonul e cheia: unic, normalizat (+407...).
 *
 * Deduplicare la intrare: același participant nu poate avea două intrări valabile în aceeași sesiune de recepție
 * (enteredInSession). Sesiunea se închide odată cu raportarea de recepție, deci la festival seara următoare e o
 * sesiune nouă; o sesiune uitată deschisă blochează reintrarea până se închide casa.
 */
class ParticipantRegistry
{
    public static function create(string $name, string $phone, ?int $adminId = null, string $source = 'reception'): Participant
    {
        [$name, $normalized] = self::validated($name, $phone);

        if ($existing = Participant::query()->where('phone', $normalized)->first()) {
            throw new DomainException('Există deja un participant cu acest telefon: '.$existing->name.'.');
        }

        $participant = Participant::create([
            'name' => $name,
            'phone' => $normalized,
            'source' => $source,
            'created_by' => $adminId,
        ]);

        ActivityLogger::log('participants.created', 'A adăugat participantul „'.$name.'” ('.$normalized.').');

        return $participant;
    }

    /** Participantul $id, doar dacă există și nu e anonimizat (folosit la intrări, vânzări de tokeni și bar). */
    public static function usable(int $id): Participant
    {
        $participant = Participant::find($id) ?? throw new DomainException('Participantul ales nu există.');

        if ($participant->isAnonymized()) {
            throw new DomainException('Un participant ales a fost anonimizat și nu mai poate fi folosit.');
        }

        return $participant;
    }

    public static function update(Participant $participant, string $name, string $phone): void
    {
        if ($participant->isAnonymized()) {
            throw new DomainException('Participantul a fost anonimizat și nu se mai poate modifica.');
        }

        [$name, $normalized] = self::validated($name, $phone);

        $clash = Participant::query()->where('phone', $normalized)->where('id', '!=', $participant->id)->first();
        if ($clash) {
            throw new DomainException('Există deja un participant cu acest telefon: '.$clash->name.'.');
        }

        if ($participant->name === $name && $participant->phone === $normalized) {
            return;
        }

        $old = $participant->label();
        $participant->update(['name' => $name, 'phone' => $normalized]);

        ActivityLogger::log('participants.updated', 'A modificat participantul „'.$old.'” în „'.$participant->label().'”.');
    }

    /** Șterge un participant care n-are nicio intrare; altfel se anonimizează. */
    public static function delete(Participant $participant): void
    {
        if ($participant->entries()->exists()) {
            throw new DomainException('Participantul are intrări înregistrate și nu poate fi șters. Poți să-l anonimizezi.');
        }

        $label = $participant->label();
        $participant->delete();

        ActivityLogger::log('participants.deleted', 'A șters participantul „'.$label.'”.');
    }

    /** Golește numele și telefonul (istoricul intrărilor rămâne, ca număr, pentru statistici). */
    public static function anonymize(Participant $participant): void
    {
        if ($participant->isAnonymized()) {
            throw new DomainException('Participantul e deja anonimizat.');
        }

        $label = $participant->label();
        $participant->update([
            'name' => Participant::ANONYMIZED_NAME,
            'phone' => null,
            'anonymized_at' => now(),
        ]);

        ActivityLogger::log('participants.anonymized', 'A anonimizat participantul „'.$label.'”.');
    }

    /**
     * Căutare după nume sau telefon (minim 2 caractere). Exclude anonimizații și id-urile din $exclude.
     *
     * @param  array<int, int>  $exclude
     * @return Collection<int, Participant>
     */
    public static function search(string $query, int $limit = 6, array $exclude = []): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return collect();
        }

        $digits = preg_replace('/\D+/', '', $query) ?? '';
        // „0722...” se caută și ca „722...” (telefonul e salvat ca +40722...).
        $phonePart = $digits !== '' ? ltrim(str_starts_with($digits, '0') ? substr($digits, 1) : $digits, '0') : '';

        return Participant::query()
            ->whereNull('anonymized_at')
            ->when($exclude, fn ($q) => $q->whereNotIn('id', $exclude))
            ->where(function ($q) use ($query, $phonePart) {
                $q->where('name', 'like', '%'.self::escapeLike($query).'%');
                if (mb_strlen($phonePart) >= 3) {
                    $q->orWhere('phone', 'like', '%'.$phonePart.'%');
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Intrările valabile (neanulate) ale participanților: id => număr.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function visitCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PartyEntry::query()->active()
            ->whereIn('participant_id', $ids)
            ->selectRaw('participant_id, COUNT(*) as n')
            ->groupBy('participant_id')
            ->pluck('n', 'participant_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Intrarea valabilă a participantului în sesiunea de recepție DESCHISĂ a petrecerii („aceeași sesiune”), sau null.
     * Fără sesiune deschisă nu poate exista dublură. O intrare anulată nu blochează.
     */
    public static function enteredInSession(Participant $participant, Party $party): ?PartyEntry
    {
        $session = ReceptionSession::currentFor($party->id);
        if (! $session) {
            return null;
        }

        return PartyEntry::query()->active()
            ->where('reception_session_id', $session->id)
            ->where('participant_id', $participant->id)
            ->orderByDesc('entered_at')
            ->first();
    }

    /** @return array{0: string, 1: string} [nume curățat, telefon normalizat] */
    private static function validated(string $name, string $phone): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (mb_strlen($name) < 2) {
            throw new DomainException('Numele participantului este obligatoriu (minim 2 caractere).');
        }
        if (mb_strlen($name) > 120) {
            throw new DomainException('Numele poate avea cel mult 120 de caractere.');
        }

        $normalized = Phone::normalize($phone);
        if ($normalized === null) {
            throw new DomainException('Telefonul nu pare valid (ex. 0722 123 456).');
        }

        return [$name, $normalized];
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
