<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Card de fidelitate).
 *
 * Mecanism: la X intrări (setare globală `loyalty_stamps_required`), următoarea e gratis. Nu orice participant
 * are card — se înrolează explicit (manual acum, din aplicație mai târziu). Nu orice petrecere acordă ștampile —
 * `parties.loyalty_eligible` controlează atât ștampilarea, cât și acceptarea plății „Beneficiu" la intrare
 * (vezi App\Services\EntryRecorder și App\Support\PaymentMethods::BENEFIT, reutilizată aici — nu s-a creat o
 * metodă de plată nouă).
 *
 * `loyalty_cards`: un rând per card (activ sau completat/arhivat) al unui participant. `stamps_required` e
 * FIXAT la valoarea din Setări de la crearea cardului (nu se schimbă retroactiv dacă se modifică setarea
 * globală, ca desenul cardului — X cercuri + 1 „intrare gratis" — să rămână corect). Când se completează
 * (stamps_required + 1 ștampile valide), cardul devine `completed` și se arhivează; App\Services\LoyaltyLedger
 * creează automat următorul card `active`.
 *
 * `loyalty_stamps`: ledger imuabil (ca la credit_transactions) — nu se șterge nimic, doar se anulează
 * (voided_at/void_reason), de ex. la anularea intrării care a produs-o. `source`:
 *  - `entry`: automată, la o intrare identificată (party_entry_id completat) pe o petrecere eligibilă;
 *  - `manual_adjust`: ajustare de admin din fișa participantului — acoperă atât corecțiile, cât și migrarea
 *    ștampilelor de pe un card fizic la înrolare (motiv obligatoriu).
 * Fără coloană „position": ordinea cercurilor se calculează la afișare (ștampilele nevanulate, în ordinea creării).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('loyalty_eligible')->default(false)->after('payment_methods');
        });

        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->unsignedTinyInteger('stamps_required');
            $table->string('status', 20)->default('active'); // active | completed
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['participant_id', 'status']);
        });

        Schema::create('loyalty_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_card_id')->constrained('loyalty_cards')->cascadeOnDelete();
            $table->string('source', 20); // entry | manual_adjust
            $table->dateTime('stamped_at');
            // Fara FK (ca la party_entries.participant_id): se completeaza doar la source = entry.
            $table->unsignedBigInteger('party_entry_id')->nullable();
            $table->string('reason', 255)->nullable(); // obligatoriu la manual_adjust
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['loyalty_card_id', 'voided_at']);
            $table->index('party_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_stamps');
        Schema::dropIfExists('loyalty_cards');

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('loyalty_eligible');
        });
    }
};
