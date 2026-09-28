<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Portofelul de credite).
 *
 * `credit_transactions`: ledgerul imuabil al soldului de credite al fiecărui participant (1 credit = 1 leu):
 *  - type `load`: încărcare de credite (amount > 0), din sursa `participant_app` (cumpărare cu cardul, nedezvoltată
 *    încă), `reception` (vândute la recepție, în loc de tokeni) sau `manual` (admin, corecție/stoc inițial);
 *  - type `payment`: plată cu credite la bar sau la intrare (amount < 0), debitată automat, fără aprobarea
 *    participantului; `reference_type`/`reference_id` leagă rândul de vânzarea/intrarea plătită;
 *  - type `refund`: returnare manuală de credite (amount > 0), motiv obligatoriu — nu există refund automat;
 *  - type `adjustment`: corecție manuală, cu semn, motiv obligatoriu.
 * Soldul unui participant = SUM(amount) pe rândurile lui; se ține cache pe `participants.credit_balance`
 * (coloană adăugată mai jos), recalculat DOAR prin App\Services\CreditLedger — niciodată prin INSERT direct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 8, 2);
            $table->string('source', 20);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['participant_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('occurred_at');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->decimal('credit_balance', 8, 2)->default(0)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });

        Schema::dropIfExists('credit_transactions');
    }
};
