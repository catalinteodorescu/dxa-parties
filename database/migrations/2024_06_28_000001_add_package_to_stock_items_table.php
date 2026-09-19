<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            // Pur informativ - NU schimba unitatea de baza (in care se tine
            // stock_qty/avg_cost si in care se scriu retetele). Permite
            // afisarea unei echivalente in "sticle"/"bucati" pt. produsele
            // tinute in ml/l/g/kg dar cumparate/gandite pe ambalaj (ex.
            // "sticla 700ml"). Nu urmareste sticle individuale (deschise/
            // pline) - doar imparte cantitatea totala la marimea ambalajului.
            $table->string('package_label', 60)->nullable()->after('min_stock');
            $table->decimal('package_qty', 12, 3)->nullable()->after('package_label');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['package_label', 'package_qty']);
        });
    }
};
