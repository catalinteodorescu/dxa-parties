<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Raportări - tokeni). Recepția numără și tokenii fizici: fondul de tokeni de la început (`opening_tokens`)
 * și cei rămași la final (`counted_tokens`), față de cei vânduți în sesiune. Barul numără tokenii încasați
 * (`counted_tokens`), față de cei plătiți cu token în vânzările sesiunii.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->unsignedInteger('opening_tokens')->nullable()->after('counted_cash');
            $table->unsignedInteger('counted_tokens')->nullable()->after('opening_tokens');
        });

        Schema::table('bar_reports', function (Blueprint $table) {
            $table->unsignedInteger('counted_tokens')->nullable()->after('counted_cash');
        });
    }

    public function down(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->dropColumn(['opening_tokens', 'counted_tokens']);
        });

        Schema::table('bar_reports', function (Blueprint $table) {
            $table->dropColumn('counted_tokens');
        });
    }
};
