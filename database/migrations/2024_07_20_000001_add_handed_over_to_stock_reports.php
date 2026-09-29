<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Bar - raportare de stoc). Banii scoși din casă în timpul serii: se aduc din raportarea de casă a barului
 * (trimisă din aplicație) și se scad din cash-ul așteptat, ca în raportarea barmanului.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->decimal('handed_over', 10, 2)->nullable()->after('opening_float');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropColumn('handed_over');
        });
    }
};
