<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (protecție la retrimitere). Cheile unice ale încercărilor de înregistrare din aplicații (bar, recepție):
 * dacă răspunsul se pierde (semnal slab) și utilizatorul apasă din nou, aceeași încercare nu se înregistrează de două ori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submit_guards', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submit_guards');
    }
};
