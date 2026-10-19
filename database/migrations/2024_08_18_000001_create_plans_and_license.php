<?php

use App\Support\Features;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: runda 79 — planuri Basic/Pro și licența instalării. `plans`: ce include fiecare plan (listă de funcționalități + limite).
 * `license`: un singur rând, planul activ al instalării + suprascrieri pe funcție (cheie => true/false). O instalare existentă
 * pornește pe Pro: nu pierde nimic până o treci tu pe Basic din pagina de proprietar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 60);
            $table->decimal('price_monthly', 8, 2)->nullable();
            $table->decimal('price_yearly_monthly', 8, 2)->nullable(); // prețul pe lună la plata anuală
            $table->json('features');
            $table->json('limits');
            $table->timestamps();
        });

        Schema::create('license', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key', 20);
            $table->json('overrides')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('plans')->insert([
            ['key' => 'basic', 'name' => 'Basic', 'price_monthly' => 199, 'price_yearly_monthly' => 169,
                'features' => json_encode(Features::basicDefaults()),
                'limits' => json_encode(['max_superadmins' => 1, 'max_admins' => 1, 'sms_included' => 100]),
                'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pro', 'name' => 'Pro', 'price_monthly' => 349, 'price_yearly_monthly' => 299,
                'features' => json_encode(Features::keys()),
                'limits' => json_encode(['max_superadmins' => null, 'max_admins' => null, 'sms_included' => 300]),
                'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('license')->insert(['plan_key' => 'pro', 'overrides' => json_encode([]), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('license');
        Schema::dropIfExists('plans');
    }
};
