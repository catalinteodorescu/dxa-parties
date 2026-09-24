<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Bar - raportari) — Numărătoarea de final de seară.
 *
 * Pe raportare: fondul de casă, cash-ul și tokenii NUMĂRAȚI (introduși de om) și, la
 * finalizare, snapshot-ul valorilor AȘTEPTATE (din vânzările postate de raportare), ca
 * diferența să rămână corectă chiar dacă datele se schimbă ulterior.
 *
 * `stock_report_counts` = foaia de inventar: o cantitate numărată per produs de stoc.
 * În draft are doar `counted_qty`; la finalizare se completează `expected_qty` (stocul
 * teoretic după postare), `diff_qty` (numărat − așteptat) și `unit_cost` (CMP la acel moment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->decimal('opening_float', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->unsignedInteger('counted_tokens')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->unsignedInteger('expected_tokens')->nullable();
            $table->boolean('stock_aligned')->default(false);
        });

        Schema::create('stock_report_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('stock_reports')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->decimal('counted_qty', 12, 3);
            $table->decimal('expected_qty', 12, 3)->nullable();
            $table->decimal('diff_qty', 12, 3)->nullable();
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_report_counts');

        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropColumn([
                'opening_float',
                'counted_cash',
                'counted_tokens',
                'expected_cash',
                'expected_tokens',
                'stock_aligned',
            ]);
        });
    }
};
