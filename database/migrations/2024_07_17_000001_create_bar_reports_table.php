<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (PWA Bar - raportare). `bar_reports`: raportarea de casă a barmanului pentru o sesiune de vânzări
 * (una per sesiune), ca la recepție: fond de casă, bani scoși din casă (+ notă), cash numărat, note pe metode și note.
 * Barmanul o „trimite” din aplicație (submitted_at): cât e trimisă, nu se mai vinde și nu se mai anulează nimic în
 * sesiune; adminul o redeschide din web sau închide sesiunea prin Raportarea de stoc (StockReport::finalize()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_group_id')->unique()->constrained('sales_groups')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->date('date');
            $table->text('note')->nullable();

            $table->decimal('opening_float', 10, 2)->nullable();
            $table->decimal('handed_over', 10, 2)->nullable();
            $table->string('handed_note', 255)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->json('method_notes')->nullable(); // cheie metodă => notă

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_reports');
    }
};
