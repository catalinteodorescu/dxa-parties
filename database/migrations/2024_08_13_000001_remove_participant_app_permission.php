<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;

/**
 * DXA: adaugat (runda 70c). Secțiunea de permisiuni „Aplicație participanți” (R70b) dispare: notificările au acum bifa „Notificări”
 * sub Setări (Administrare). Valorile rămase pe utilizatori se scot; bifa nouă o acordă manual cine administrează utilizatorii.
 */
return new class extends Migration
{
    public function up(): void
    {
        Admin::query()->get()->each(function (Admin $a) {
            $perms = (array) $a->permissions;
            $levels = (array) ($perms['levels'] ?? []);
            if (array_key_exists('participant_app', $levels)) {
                unset($levels['participant_app']);
                $perms['levels'] = $levels;
                $a->forceFill(['permissions' => $perms])->save();
            }
        });
    }

    public function down(): void
    {
        // Fără întoarcere.
    }
};
