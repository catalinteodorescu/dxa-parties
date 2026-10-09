<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;

/**
 * DXA: adaugat (runda 66). Secțiunea nouă de permisiuni „Bilete”: adminii care vedeau deja Participanți primesc vizualizare și pe Bilete,
 * ca să nu rămână fără pagina nouă. Superadmin-ii au mereu totul; ceilalți se pot ajusta apoi din Utilizatori › Permisiuni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Admin::query()->get()->each(function (Admin $a) {
            $perms = (array) $a->permissions;
            $levels = (array) ($perms['levels'] ?? []);
            if ((int) ($levels['participants'] ?? 0) >= 1 && ! isset($levels['tickets'])) {
                $levels['tickets'] = 1;
                $perms['levels'] = $levels;
                $a->forceFill(['permissions' => $perms])->save();
            }
        });
    }

    public function down(): void
    {
        // Fără întoarcere: nivelul „tickets” rămâne inofensiv.
    }
};
