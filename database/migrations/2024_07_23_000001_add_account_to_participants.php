<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Aplicația participanților - runda 1, fundația).
 *
 * `participants` devine și cont de aplicație: parolă + `phone_verified_at` (telefonul confirmat prin SMS) + remember_token.
 * Un participant creat la Recepție n-are parolă până nu își face cont cu același telefon (după verificarea SMS se leagă de el).
 *
 * `participant_verifications`: înregistrările în așteptare (nume + telefon + parolă hash-uită, cod SMS hash-uit). Participantul
 * se creează / se leagă DOAR după ce codul e confirmat, deci nimeni nu poate „ocupa” telefonul altcuiva și nu apar rânduri de gunoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
            $table->dateTime('phone_verified_at')->nullable()->after('password');
            $table->rememberToken()->after('phone_verified_at');
        });

        Schema::create('participant_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();          // o singură înregistrare în așteptare per telefon
            $table->string('name', 120);
            $table->string('password_hash');
            $table->string('code_hash', 64);
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(0);
            $table->dateTime('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_verifications');

        Schema::table('participants', function (Blueprint $table) {
            $table->dropRememberToken();
            $table->dropColumn(['password', 'phone_verified_at']);
        });
    }
};
