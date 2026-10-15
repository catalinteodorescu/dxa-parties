<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DXA: adaugat (runda 71). PIN-ul (hash) cu care un lucrător preia telefonul comun din aplicațiile Recepție / Bar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
