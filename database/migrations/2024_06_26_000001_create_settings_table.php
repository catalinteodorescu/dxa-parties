<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // Tabel generic cheie-valoare. Ce chei exista, ce tip au si cum se
            // afiseaza in formular e definit declarativ in App\Support\Settings\SettingsRegistry,
            // nu in schema bazei de date — asa se pot adauga sectiuni/campuri noi
            // de setari fara migrari noi.
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
