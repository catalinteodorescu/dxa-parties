<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Venitul se tine separat de miscarile de stoc (stock_movements), la nivel
        // de menu_item (ce s-a vandut, cu ce pret), tocmai fiindca un menu_item poate
        // "exploda" in mai multe stock_items prin reteta lui (ex. un cocktail).
        // unit_cost/total_cost sunt un SNAPSHOT calculat la momentul raportarii, din
        // reteta menu_item-ului (suma qty_reteta * avg_cost al fiecarui ingredient) -
        // nu se recalculeaza retroactiv daca avg_cost al unui ingredient se schimba dupa.
        Schema::create('stock_report_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('stock_reports')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_price', 10, 2);
            // Nullable: daca la momentul vanzarii vreun ingredient din reteta avea cost
            // necunoscut (avg_cost null), nu putem calcula costul - dar in mod normal
            // nu ar trebui sa se ajunga aici, fiindca un menu_item cu reteta incompleta
            // e marcat indisponibil si nu poate fi vandut/raportat.
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_report_sales');
    }
};
