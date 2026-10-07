<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 51). Încărcarea de credite din aplicație (cu cardul) + bonusul la încărcare.
 *
 *  - `credit_transactions.bonus`: partea din `amount` primită ca bonus (gratis). `amount` rămâne TOTALUL creditat (plătit + bonus),
 *    deci soldul (SUM(amount)) nu se schimbă ca logică; banii încasați = amount - bonus.
 *  - `credit_topups`: o încărcare începută de participant (în așteptarea plății cu cardul). Suma, bonusul și taxa se îngheață la creare.
 *    Statusuri: pending → paid | failed | cancelled | expired. Creditele intră în ledger doar la `paid` (CreditTopups::complete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->decimal('bonus', 8, 2)->default(0)->after('amount');
        });

        Schema::create('credit_topups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->decimal('amount', 8, 2);            // cât plătește participantul (fără taxă)
            $table->decimal('bonus', 8, 2)->default(0); // bonus înghețat la creare
            $table->decimal('fee', 8, 2)->default(0);   // taxă de serviciu (Stripe; deocamdată 0)
            $table->string('status', 20)->default('pending');
            $table->string('provider', 20)->nullable();
            $table->string('provider_ref', 120)->nullable();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('credit_transactions')->nullOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['participant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_topups');

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn('bonus');
        });
    }
};
