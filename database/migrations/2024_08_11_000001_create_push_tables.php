<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 70). Notificări push (Web Push):
 *  - push_subscriptions: un telefon/browser abonat (endpoint-ul dat de browser) al unui participant;
 *  - push_notifications: ce i s-a trimis fiecărui participant (cheie unică per participant => nu se trimite de două ori);
 *  - push_deliveries: livrarea fiecărei notificări către fiecare abonare (service worker-ul cere conținutul de aici);
 *  - push_events: evenimente „către toți” deja tratate (ex. petrecere nouă), ca să nu se repete.
 * Petrecerile deja publicate la momentul migrării sunt marcate ca tratate: nu se trimite „petrecere nouă” pentru ele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh')->nullable();
            $table->string('auth')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('dedupe_key', 120);
            $table->string('title', 120);
            $table->string('body', 255);
            $table->string('url', 255)->default('/');
            $table->timestamp('created_at')->nullable();
            $table->unique(['participant_id', 'dedupe_key']);
            $table->index(['kind', 'created_at']);
        });

        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->unique(['push_notification_id', 'push_subscription_id'], 'push_deliveries_unique');
        });

        Schema::create('push_events', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('push')->default(false)->after('in_list');
            $table->timestamp('push_sent_at')->nullable()->after('push');
        });

        // Petrecerile deja publicate: tratate (altfel ar primi toți „petrecere nouă” la prima rulare).
        $now = now();
        DB::table('parties')->where('status', 'published')->pluck('id')->chunk(200)->each(function ($ids) use ($now) {
            DB::table('push_events')->insert($ids->map(fn ($id) => ['key' => 'party_new:'.$id, 'created_at' => $now])->all());
        });

        // Pagina nouă „Notificări push”: adminii care vedeau Participanți primesc vizualizare.
        Admin::query()->get()->each(function (Admin $a) {
            $perms = (array) $a->permissions;
            $levels = (array) ($perms['levels'] ?? []);
            if ((int) ($levels['participants'] ?? 0) >= 1 && ! isset($levels['push'])) {
                $levels['push'] = 1;
                $perms['levels'] = $levels;
                $a->forceFill(['permissions' => $perms])->save();
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['push', 'push_sent_at']);
        });
        Schema::dropIfExists('push_events');
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('push_subscriptions');
    }
};
