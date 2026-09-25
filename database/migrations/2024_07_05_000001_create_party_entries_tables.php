<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Recepție - intrări).
 *
 * `party_entries`: un rând IMUABIL per persoană intrată, cu prețul înghețat la momentul intrării:
 *  - list_price = ce spunea sistemul la ora intrării (fără toleranță);
 *  - price_paid = ce s-a încasat efectiv (poate fi mai mic: toleranță, suprascriere manuală cu motiv);
 *  - batch = uuid comun pentru persoanele înregistrate odată (grup); anularea se face pe grup;
 *  - participant_id: fără FK (se completează când apare modulul Participanți; contorul anonim = null);
 *  - anulare cu motiv, niciodată ștergere.
 * `party_entry_payments`: plățile unui rând (metodă din Setări > Metode de plată + sumă). Plata mixtă se
 * introduce pe totalul grupului și se distribuie pe rânduri de EntryRecorder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->uuid('batch');
            $table->string('ticket_type', 120);
            $table->decimal('list_price', 8, 2);
            $table->decimal('price_paid', 8, 2);
            $table->boolean('grace_applied')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->dateTime('entered_at');
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['party_id', 'cancelled_at', 'entered_at']);
            $table->index('batch');
        });

        Schema::create('party_entry_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_entry_id')->constrained('party_entries')->cascadeOnDelete();
            $table->string('method', 60);
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_entry_payments');
        Schema::dropIfExists('party_entries');
    }
};
