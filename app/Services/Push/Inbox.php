<?php

namespace App\Services\Push;

use App\Models\PushNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Runda 91. Căsuța „Notificări” din aplicații: aceleași notificări trimise ca push (tabela `push_notifications`), cu „citit / necitit”.
 * Lista și numărătoarea sunt ale persoanei: un participant, sau un cont de personal într-o aplicație (reception | bar). Notificările de
 * test nu apar. Păstrarea (60 de zile) e în `Notifier::prune()`.
 */
class Inbox
{
    public static function forParticipant(int $participantId): Builder
    {
        return PushNotification::query()->where('participant_id', $participantId)->where('kind', '!=', 'test');
    }

    public static function forStaff(int $adminId, string $app): Builder
    {
        return PushNotification::query()->where('admin_id', $adminId)->where('app', $app)->where('kind', '!=', 'test');
    }

    public static function unread(Builder $query): int
    {
        return (clone $query)->whereNull('read_at')->count();
    }

    /** Marchează una ca citită (doar dacă aparține listei date). Întoarce notificarea, sau null dacă nu e a persoanei. */
    public static function markRead(Builder $query, int $id): ?PushNotification
    {
        $n = (clone $query)->whereKey($id)->first();
        if ($n && $n->read_at === null) {
            $n->forceFill(['read_at' => now()])->save();
        }

        return $n;
    }

    public static function markAllRead(Builder $query): int
    {
        return (clone $query)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** Doar adrese din aplicație (încep cu „/”, nu „//”): o notificare nu poate trimite în afara aplicației. */
    public static function safeUrl(?string $url, string $fallback): string
    {
        $url = trim((string) $url);

        return $url !== '' && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : $fallback;
    }

    /** Eticheta zilei pentru grupare: Azi / Ieri / data. */
    public static function dayLabel(PushNotification $n): string
    {
        $d = $n->created_at;

        return $d->isToday() ? 'Azi' : ($d->isYesterday() ? 'Ieri' : $d->format('d.m.Y'));
    }
}
