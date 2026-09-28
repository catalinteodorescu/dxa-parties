<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Portofelul de credite - Etapa 2: vânzare la recepție).
 *
 * Vânzarea de credite la recepție intră în ACELAȘI flux/raport ca vânzarea de tokeni: rândul de tip `load`
 * cu sursa `reception` capătă party_id + reception_session_id (ca token_transactions), plăți în tabelul de mai
 * jos (ca token_transaction_payments) și se poate anula (ca vânzarea de tokeni), fără să se șteargă — se
 * marchează cancelled_at/cancelled_by/cancel_reason, iar soldul se recalculează excluzând rândurile anulate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreignId('party_id')->nullable()->after('source')->constrained('parties')->restrictOnDelete();
            $table->foreignId('reception_session_id')->nullable()->after('party_id')->constrained('reception_sessions')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('note');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('admins')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_by');

            $table->index(['party_id', 'type', 'cancelled_at']);
        });

        Schema::create('credit_transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_transaction_id')->constrained('credit_transactions')->cascadeOnDelete();
            $table->string('method', 60);
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transaction_payments');

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropIndex(['party_id', 'type', 'cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
            $table->dropConstrainedForeignId('reception_session_id');
            $table->dropConstrainedForeignId('party_id');
        });
    }
};
