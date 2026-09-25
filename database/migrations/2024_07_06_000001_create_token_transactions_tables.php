<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Recepție - tokeni).
 *
 * `token_transactions`: ledgerul tokenilor emiși/ajustați (imuabil; anularea marchează rândul, nu îl șterge):
 *  - type `sold`: vânzare la recepție (tokens > 0, token_rate și amount înghețate, party_id, plăți în tabelul de mai jos);
 *  - type `adjustment`: corecție/stoc inițial (tokens cu semn, motiv obligatoriu);
 *  - type `write_off`: casare a tokenilor rămași în circulație (tokens < 0, motiv obligatoriu).
 * Tokeni în circulație = SUM(tokens neanulate) − tokenii încasați la bar (sale_payments, vânzări finalizate).
 * participant_id: fără FK (se completează cu modulul Participanți).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->foreignId('party_id')->nullable()->constrained('parties')->restrictOnDelete();
            $table->integer('tokens');
            $table->decimal('token_rate', 8, 2)->nullable();
            $table->decimal('amount', 8, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['party_id', 'type', 'cancelled_at']);
            $table->index('occurred_at');
        });

        Schema::create('token_transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_transaction_id')->constrained('token_transactions')->cascadeOnDelete();
            $table->string('method', 60);
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_transaction_payments');
        Schema::dropIfExists('token_transactions');
    }
};
