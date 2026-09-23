<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O vanzare simpla (ex. apa vanduta la un curs) nu apartine niciunei sesiuni de vanzari.
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_group_id')->nullable()->change();
        });

        // Raportarea care a postat vanzarea (stoc + venit). Null = neraportata inca.
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('report_id')->nullable()->after('sales_group_id')
                ->constrained('stock_reports')->nullOnDelete();
            $table->index('report_id');
        });

        // Vanzarile din sesiuni deja raportate (finalizate inainte de aceasta migrare)
        // primesc legatura retroactiv, ca sa nu apara ca neraportate.
        DB::statement("UPDATE sales SET report_id = (
            SELECT stock_reports.id FROM stock_reports
            WHERE stock_reports.sales_group_id = sales.sales_group_id AND stock_reports.status = 'finalized'
            LIMIT 1
        ) WHERE sales_group_id IS NOT NULL AND report_id IS NULL");

        // In Raportare: includerea vanzarilor simple (fara sesiune) neraportate.
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->boolean('include_loose_sales')->default(false)->after('sales_group_id');
        });

        // Randuri de venit provenite din vanzari simple (nu dintr-o sesiune).
        Schema::table('stock_report_sales', function (Blueprint $table) {
            $table->boolean('is_loose')->default(false)->after('sales_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_report_sales', function (Blueprint $table) {
            $table->dropColumn('is_loose');
        });

        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropColumn('include_loose_sales');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['report_id']);
            $table->dropIndex(['report_id']);
            $table->dropColumn('report_id');
        });

        // sales.sales_group_id ramane nullable (nu se poate reveni sigur daca exista vanzari simple).
    }
};
