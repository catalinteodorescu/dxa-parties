<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runda 91. Pagina „Notificări” din aplicații: o notificare poate fi citită (`read_at`). Indexurile servesc numărătoarea de necitite
 * (clopoțelul din antet) și lista, separat pentru participanți și pentru conturile de personal (după aplicație).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
            $table->index(['participant_id', 'read_at'], 'push_notifications_participant_read');
            $table->index(['admin_id', 'app', 'read_at'], 'push_notifications_admin_read');
        });

        // Ce s-a trimis până acum nu apare ca „necitit”: clopoțelul pornește de la zero.
        DB::table('push_notifications')->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropIndex('push_notifications_participant_read');
            $table->dropIndex('push_notifications_admin_read');
            $table->dropColumn('read_at');
        });
    }
};
