<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Completat DOAR cand intrarea (type='in') provine dintr-un necesar,
            // adica linia de raportare avea requisition_item_id. Folosit ca sa
            // regasim in Istoric mișcări sursa exacta a intrarii ("Recepție
            // necesar «...»" vs. "Raportare din ...").
            $table->foreignId('requisition_item_id')->nullable()->after('sale_id')
                ->constrained('stock_requisition_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['requisition_item_id']);
            $table->dropColumn('requisition_item_id');
        });
    }
};
