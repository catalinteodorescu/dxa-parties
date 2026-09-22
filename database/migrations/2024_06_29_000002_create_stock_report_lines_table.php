<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staging pt. un raport 'draft': fiecare rand = o linie din formular
        // (intrare/vanzare/pierdere), editabila liber cat timp raportul nu e
        // finalizat. NU se atinge stock_qty/avg_cost si NU se scrie nimic in
        // stock_movements/stock_report_sales pana la StockReport::finalize().
        // La finalizare, liniile sunt golite - nu ramane sursa dubla de adevar.
        Schema::create('stock_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('stock_reports')->cascadeOnDelete();

            // entry: aprovizionare (stock_item_id, unit_cost optional)
            // sale:  vanzare de meniu (menu_item_id, fara pret manual - se
            //        calculeaza la finalizare din MenuItem::price/costPerUnit())
            // loss:  pierdere/consum manual (stock_item_id, note obligatoriu)
            $table->string('kind');

            $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            // Prezent DOAR la o intrare (entry) adusa dintr-un necesar deschis -
            // la finalizare, actualizeaza qty_received pe aceasta linie de necesar.
            $table->foreignId('requisition_item_id')->nullable()
                ->constrained('stock_requisition_items')->nullOnDelete();

            $table->decimal('qty', 12, 3);

            // Doar la entry - null = cost necunoscut la aceasta intrare (CMP-ul
            // produsului ramane neschimbat, doar cantitatea creste - vezi
            // StockItem::applyIncrease()).
            $table->decimal('unit_cost', 10, 4)->nullable();

            // Motiv obligatoriu la loss (validat in Livewire, nu la nivel de DB).
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['report_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_report_lines');
    }
};
