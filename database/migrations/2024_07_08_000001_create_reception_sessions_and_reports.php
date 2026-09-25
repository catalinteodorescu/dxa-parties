<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Recepție - sesiuni și raportări de recepție = închiderea casei).
 *
 * `reception_sessions`: sesiunea de recepție a unei petreceri, ca „sesiunea de vânzări” de la bar. Se deschide singură la
 *   prima intrare / vânzare de tokeni; o singură sesiune deschisă per petrecere; se închide la finalizarea raportării ei.
 * `reception_reports`: raportarea de recepție (una per sesiune). Draft cu autosave, apoi finalizată: valorile așteptate
 *   se ÎNGHEAȚĂ la finalizare (coloanele expected_cash / cash_* + `snapshot` JSON).
 * `party_entries.reception_session_id` și `token_transactions.reception_session_id` (nullable): sesiunea în care a fost
 *   înregistrată intrarea / vânzarea de tokeni. Ajustările și casările de tokeni nu sunt încasări la casă și rămân fără sesiune.
 *
 * Înregistrările existente se atașează unei singure sesiuni deschise per petrecere (una per petrecere cu intrări/vânzări).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('status', 12)->default('open'); // open | closed
            $table->foreignId('opened_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'status']);
        });

        Schema::create('reception_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_session_id')->unique()->constrained('reception_sessions')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 12)->default('draft'); // draft | finalized
            $table->text('note')->nullable();

            // Introduse de admin (draft): toate în lei.
            $table->decimal('opening_float', 10, 2)->nullable();
            $table->decimal('handed_over', 10, 2)->nullable();
            $table->string('handed_note', 255)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->json('method_notes')->nullable(); // cheie metodă => notă

            // Înghețate la finalizare.
            $table->decimal('cash_entries', 10, 2)->nullable();
            $table->decimal('cash_tokens', 10, 2)->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('cash_diff', 10, 2)->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['party_id', 'status']);
        });

        Schema::table('party_entries', function (Blueprint $table) {
            $table->foreignId('reception_session_id')->nullable()->after('party_id')
                ->constrained('reception_sessions')->nullOnDelete();
        });

        Schema::table('token_transactions', function (Blueprint $table) {
            $table->foreignId('reception_session_id')->nullable()->after('party_id')
                ->constrained('reception_sessions')->nullOnDelete();
        });

        // Înregistrările existente: o sesiune deschisă per petrecere cu intrări sau vânzări de tokeni.
        $partyIds = DB::table('party_entries')->distinct()->pluck('party_id')
            ->merge(DB::table('token_transactions')->where('type', 'sold')->whereNotNull('party_id')->distinct()->pluck('party_id'))
            ->unique()
            ->values();

        foreach ($partyIds as $partyId) {
            $first = collect([
                DB::table('party_entries')->where('party_id', $partyId)->min('entered_at'),
                DB::table('token_transactions')->where('type', 'sold')->where('party_id', $partyId)->min('occurred_at'),
            ])->filter()->min() ?? now()->toDateTimeString();

            $sessionId = DB::table('reception_sessions')->insertGetId([
                'party_id' => $partyId,
                'status' => 'open',
                'created_at' => $first,
                'updated_at' => $first,
            ]);

            DB::table('party_entries')->where('party_id', $partyId)->update(['reception_session_id' => $sessionId]);
            DB::table('token_transactions')->where('type', 'sold')->where('party_id', $partyId)->update(['reception_session_id' => $sessionId]);
        }
    }

    public function down(): void
    {
        Schema::table('token_transactions', function (Blueprint $table) {
            $table->dropForeign(['reception_session_id']);
            $table->dropColumn('reception_session_id');
        });

        Schema::table('party_entries', function (Blueprint $table) {
            $table->dropForeign(['reception_session_id']);
            $table->dropColumn('reception_session_id');
        });

        Schema::dropIfExists('reception_reports');
        Schema::dropIfExists('reception_sessions');
    }
};
