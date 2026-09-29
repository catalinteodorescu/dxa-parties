<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Utilizatori - acces pe aplicație). Tabelul `admins` rămâne tabelul de utilizatori; fiecare cont
 * primește trei bife independente de acces: panoul web de administrare, aplicația de bar și aplicația de recepție.
 * Superadmin-ul are implicit acces la toate (bifele lui nu contează). Conturile existente păstrează accesul la
 * panou (default true) și nu primesc bar/recepție — le bifează un superadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('access_admin')->default(true)->after('is_active');
            $table->boolean('access_bar')->default(false)->after('access_admin');
            $table->boolean('access_reception')->default(false)->after('access_bar');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['access_admin', 'access_bar', 'access_reception']);
        });
    }
};
