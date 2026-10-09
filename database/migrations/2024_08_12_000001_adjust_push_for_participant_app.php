<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 70b). Ajustări la notificările push:
 *  - anunțurile nu mai au bifă „push” (coloana dispare); anunțurile deja publicate și vizibile la momentul migrării se marchează ca
 *    trimise, ca să nu primească toți abonații notificări pentru anunțuri vechi; cele ciorne sau programate pleacă când devin vizibile;
 *  - permisiunea „push” (acordată automat adminilor cu Participanți) dispare: pagina Notificări ține acum de secțiunea nouă
 *    „Aplicație participanți” (`participant_app`), pe care o acordă manual un utilizator cu drept de administrare a utilizatorilor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('announcements')->whereNull('push_sent_at')->where('status', 'published')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->update(['push_sent_at' => $now]);

        if (Schema::hasColumn('announcements', 'push')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('push');
            });
        }

        Admin::query()->get()->each(function (Admin $a) {
            $perms = (array) $a->permissions;
            $levels = (array) ($perms['levels'] ?? []);
            if (array_key_exists('push', $levels)) {
                unset($levels['push']);
                $perms['levels'] = $levels;
                $a->forceFill(['permissions' => $perms])->save();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('announcements', 'push')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->boolean('push')->default(false)->after('in_list');
            });
        }
    }
};
