<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Participanți - fundația, Arhiva B).
 *
 * `participants`: persoane identificate (adăugate manual la recepție acum; conturi din aplicație mai târziu).
 *  - uuid: identificator stabil, pentru viitorul cod QR personal;
 *  - phone: normalizat (+407...), unic; când persoana își face cont în aplicație cu același telefon, înregistrarea se preia;
 *  - source: 'reception' acum ('app' mai târziu);
 *  - anonymized_at: „dreptul de ștergere” - numele și telefonul se golesc, istoricul intrărilor rămâne pentru statistici.
 * `party_entries.participant_id` exista deja (fără FK, ca la vânzări); adăugăm doar un index pentru numărători.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable()->unique();
            $table->string('source', 20)->default('reception');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('party_entries', function (Blueprint $table) {
            $table->index('participant_id');
        });
    }

    public function down(): void
    {
        Schema::table('party_entries', function (Blueprint $table) {
            $table->dropIndex(['participant_id']);
        });

        Schema::dropIfExists('participants');
    }
};
