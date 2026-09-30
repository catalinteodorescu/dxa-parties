<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Aplicația participanților - runda 12). Poza de profil: calea fișierului (disc privat, servit doar
 * participantului logat) și momentul schimbării (versiunea din URL, ca să nu rămână poza veche în cache).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('remember_token');
            $table->dateTime('avatar_updated_at')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_updated_at']);
        });
    }
};
