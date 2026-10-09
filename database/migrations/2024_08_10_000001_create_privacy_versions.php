<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 68). Politica de confidențialitate (informare, nu se acceptă) + secțiunea de permisiuni „Legal”.
 * `privacy_versions`: fiecare publicare e o versiune nouă, cu textul întreg. Adminii care puteau modifica Setările (și deci publicau Termenii)
 * primesc aceleași drepturi pe Legal, ca să nu piardă ce aveau; ceilalți se ajustează din Utilizatori › Permisiuni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_versions', function (Blueprint $table) {
            $table->id();
            $table->longText('body');
            $table->foreignId('published_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Admin::query()->get()->each(function (Admin $a) {
            $perms = (array) $a->permissions;
            $levels = (array) ($perms['levels'] ?? []);
            $settings = (int) ($levels['settings'] ?? 0);
            if ($settings >= 1 && ! isset($levels['legal'])) {
                $levels['legal'] = min($settings, 2);
                $perms['levels'] = $levels;
                $a->forceFill(['permissions' => $perms])->save();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_versions');
    }
};
