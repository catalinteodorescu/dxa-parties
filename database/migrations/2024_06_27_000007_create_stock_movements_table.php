<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();

            // Prezent doar pt. miscari generate dintr-o raportare (in/out din raportare).
            // Null pt. stoc initial (generat la crearea stock_item-ului) si pt. corectii
            // manuale de cost (tip "adjustment").
            $table->foreignId('report_id')->nullable()->constrained('stock_reports')->nullOnDelete();

            // Prezent doar la iesirile generate de o vanzare de meniu (tip "out" prin
            // explodarea retetei) - permite sa urmarim din ce vanzare a rezultat consumul
            // acestui ingredient. Null la iesiri de tip pierdere/consum manual.
            $table->foreignId('sale_id')->nullable()->constrained('stock_report_sales')->nullOnDelete();

            // initial:    stoc de pornire, setat o singura data la crearea stock_item-ului.
            // in:         intrare prin raportare (aprovizionare).
            // out:        iesire - fie prin vanzare de meniu (sale_id completat),
            //             fie consum/pierdere manuala (sale_id null, note = motiv).
            // adjustment: corectie manuala de cost (qty = 0, doar unit_cost) sau corectie
            //             de cantitate in urma unui inventar fizic.
            $table->string('type');

            $table->decimal('qty', 12, 3)->default(0);
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
