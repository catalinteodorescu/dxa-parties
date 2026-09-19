<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_requested', 12, 3);
            // Actualizat automat (suma intrarilor) cand o raportare rezolva acest necesar.
            $table->decimal('qty_received', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['stock_requisition_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requisition_items');
    }
};
