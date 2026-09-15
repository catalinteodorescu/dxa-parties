<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Helper central pentru logarea actiunilor importante din panoul de admin.
 *
 * Conventie pentru $action: "entitate.eveniment" (ex: "admin.created",
 * "admin.login.failed"). Pastreaza-le consistente, ca sa fie usor de
 * filtrat mai tarziu intr-o eventuala pagina de "Jurnal activitate".
 *
 * $actor implicit e adminul autentificat curent (guard 'admin'). Trimite
 * actor: null explicit pentru evenimente fara un admin autentificat
 * (ex: login esuat, reset de parola prin link).
 *
 * $subject se completeaza DOAR cand actiunea vizeaza alt admin decat cel
 * care o executa (ex: superadmin schimba rolul altcuiva). Pentru actiuni
 * asupra propriului cont, lasa $subject null - actor_label e suficient.
 */
class ActivityLogger
{
    public static function log(string $action, string $description, ?Admin $subject = null, ?Admin $actor = null): void
    {
        $actor ??= Auth::guard('admin')->user();

        AdminActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_label' => $actor ? self::label($actor) : 'Sistem',
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? Admin::class : null,
            'subject_id' => $subject?->id,
            'subject_label' => $subject ? self::label($subject) : null,
            'ip_address' => Request::ip(),
        ]);
    }

    public static function label(Admin $admin): string
    {
        return $admin->name ? "{$admin->name} ({$admin->phone})" : $admin->phone;
    }
}
