<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 77). Notificări push pentru personal (conturile de admin, în aplicațiile Recepție / Bar și în panou):
 * aceleași tabele ca la participanți. O abonare / notificare aparține fie unui participant, fie unui cont de admin (`admin_id`),
 * iar `app` spune unde s-a făcut abonarea (reception | bar | admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
            $table->foreignId('admin_id')->nullable()->after('participant_id')->constrained('admins')->cascadeOnDelete();
            $table->string('app', 12)->nullable()->after('admin_id');
        });

        Schema::table('push_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
            $table->foreignId('admin_id')->nullable()->after('participant_id')->constrained('admins')->cascadeOnDelete();
            $table->string('app', 12)->nullable()->after('admin_id');
            $table->unique(['admin_id', 'dedupe_key'], 'push_notifications_admin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropUnique('push_notifications_admin_unique');
            $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn('app');
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn('app');
        });
    }
};
