<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grupul de vanzari ales in Raportare (setat in draft, consumat la finalizare).
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->foreignId('sales_group_id')->nullable()->after('party_id')
                ->constrained('sales_groups')->nullOnDelete();
        });

        // Ce randuri de venit provin dintr-un grup (vs. vanzari suplimentare introduse manual).
        Schema::table('stock_report_sales', function (Blueprint $table) {
            $table->foreignId('sales_group_id')->nullable()->after('menu_item_id')
                ->constrained('sales_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_report_sales', function (Blueprint $table) {
            $table->dropForeign(['sales_group_id']);
            $table->dropColumn('sales_group_id');
        });

        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropForeign(['sales_group_id']);
            $table->dropColumn('sales_group_id');
        });
    }
};
