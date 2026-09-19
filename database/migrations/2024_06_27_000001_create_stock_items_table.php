<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Unitate de baza pentru cantitati (buc/ml/l/kg/g) - toate miscarile
            // (stoc initial, intrari, iesiri) si reteta din menu_item_recipes
            // folosesc aceeasi unitate pentru acelasi stock_item.
            $table->string('unit');
            // Cache calculat din stock_movements (nu se editeaza direct din formular),
            // recalculat la fiecare miscare. Decimal cu precizie pt. cantitati mici (ex. ml).
            $table->decimal('stock_qty', 12, 3)->default(0);
            // Cost mediu ponderat (CMP), null pana la prima intrare cu pret (sau stoc initial cu cost,
            // sau o corectie manuala ulterioara - vezi actiunea "Corecteaza cost").
            $table->decimal('avg_cost', 10, 4)->nullable();
            // Prag de alerta pt. atentionare stoc minim. Null = fara alerta configurata.
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
