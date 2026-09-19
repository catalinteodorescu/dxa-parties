<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: nu poti sterge un stock_item folosit intr-o reteta
            // (la fel cum menu_category nu se poate sterge daca are produse).
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            // Cantitate consumata din stock_item la o unitate vanduta din menu_item
            // (ex. 40 pt. "40 ml vodca" daca stock_item-ul "Vodca" e in ml).
            $table->decimal('qty', 12, 3);
            $table->timestamps();

            $table->unique(['menu_item_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_recipes');
    }
};
