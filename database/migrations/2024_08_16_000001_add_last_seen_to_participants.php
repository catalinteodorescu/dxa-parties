<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DXA: adaugat (runda 75). Ultima activitate a unui participant în aplicație (se actualizează cel mult o dată pe oră) — pentru blocul „Aplicație” din dashboard. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
