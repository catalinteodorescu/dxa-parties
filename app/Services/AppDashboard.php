<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DXA: adaugat (runda 75). Cifrele blocului „Aplicație” din dashboard: conturi și activitate, notificări push, vizite și instalări.
 *
 * Limite (le spune și blocul): activitatea se măsoară de la activarea rundei 75; „instalări” = evenimentul `appinstalled` (doar Android / Chrome,
 * iOS nu îl trimite); „deschise din aplicația instalată” = vizite făcute în modul standalone, pe orice telefon.
 */
class AppDashboard
{
    public static function stats(): object
    {
        $accounts = Participant::query()->whereNotNull('password')->whereNotNull('phone_verified_at')->whereNull('anonymized_at');

        $total = (clone $accounts)->count();
        $new30 = (clone $accounts)->where('phone_verified_at', '>=', now()->subDays(30))->count();
        $active7 = (clone $accounts)->where('last_seen_at', '>=', now()->subDays(7))->count();
        $active30 = (clone $accounts)->where('last_seen_at', '>=', now()->subDays(30))->count();

        $pushPeople = PushSubscription::query()->whereIn('participant_id', (clone $accounts)->select('id'))->distinct()->count('participant_id');
        $lastPush = PushNotification::query()->whereNull('admin_id')->where('kind', '!=', 'test')->latest('id')->value('created_at');
        $push7 = PushNotification::query()->whereNull('admin_id')->where('kind', '!=', 'test')->where('created_at', '>=', now()->subDays(7))->count();

        return (object) [
            'accounts' => $total,
            'new30' => $new30,
            'active7' => $active7,
            'active30' => $active30,
            'push_people' => $pushPeople,
            'push_pct' => $total > 0 ? (int) round($pushPeople * 100 / $total) : null,
            'push_last' => $lastPush ? Carbon::parse($lastPush) : null,
            'push7' => $push7,
            'visits7' => self::sum('visit', 7, 'uniques'),
            'visits30' => self::sum('visit', 30, 'uniques'),
            'standalone7' => self::sum('standalone', 7, 'uniques'),
            'installs30' => self::sum('install', 30, 'total'),
            'installs_all' => self::sum('install', 3650, 'total'),
        ];
    }

    private static function sum(string $event, int $days, string $column): int
    {
        return (int) DB::table('content_stats')->where('subject_type', 'app')->where('event', $event)
            ->where('day', '>=', now()->subDays($days)->toDateString())->sum($column);
    }
}
