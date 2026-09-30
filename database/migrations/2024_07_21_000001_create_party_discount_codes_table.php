<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Coduri de reducere).
 *
 * `party_discount_codes`: mai multe coduri pe petrecere (ex. câte unul pe promotor). Tipuri:
 *  - percent: −value% din prețul curent;
 *  - amount:  −value lei din prețul curent;
 *  - tier:    prețul treptei `tier_label` (ex. „Early bird") a biletului, indiferent de dată (niciodată mai mare decât prețul curent).
 * `ticket_types` (json, null = toate) limitează codul la anumite tipuri de bilet (după nume).
 * Utilizările NU se stochează: se numără din `party_entries` valabile (o utilizare = un bilet = un rând valabil), deci
 * anularea unei intrări eliberează codul.
 *
 * `party_entries` primește `discount_code_id` (fără FK, ca `participant_id`) și `discount_amount` (per persoană,
 * înghețat la momentul intrării, ca `list_price`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('promoter', 120)->nullable();
            $table->string('type', 10);
            $table->decimal('value', 8, 2)->nullable();
            $table->string('tier_label', 60)->nullable();
            $table->json('ticket_types')->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_participant')->nullable()->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['party_id', 'code']);
        });

        Schema::table('party_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_code_id')->nullable()->after('override_reason');
            $table->decimal('discount_amount', 8, 2)->default(0)->after('discount_code_id');
            $table->index('discount_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('party_entries', function (Blueprint $table) {
            $table->dropIndex(['discount_code_id']);
            $table->dropColumn(['discount_code_id', 'discount_amount']);
        });

        Schema::dropIfExists('party_discount_codes');
    }
};
