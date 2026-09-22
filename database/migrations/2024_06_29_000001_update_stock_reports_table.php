<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
            $table->dropColumn('requisition_id');

            // draft: liniile stau doar in stock_report_lines (staging), nimic real
            // nu s-a scris inca in stock_movements/stock_report_sales.
            // finalized: miscarile reale au fost create, ireversibil - fara
            // editare/stergere dupa acest punct.
            $table->string('status')->default('draft')->after('date');
            $table->timestamp('finalized_at')->nullable()->after('status');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')
                ->constrained('admins')->nullOnDelete();

            // Informativ (pt. sectiunea Vanzari si viitoarea "Vanzari per party") -
            // nu mai deduce necesarul din requisition_id, acum liniile de intrare
            // isi leaga individual necesarul prin stock_report_lines.requisition_item_id.
            $table->foreignId('party_id')->nullable()->after('finalized_by')
                ->constrained('parties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->dropColumn('party_id');

            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['finalized_by', 'finalized_at', 'status']);

            $table->foreignId('requisition_id')->nullable()->after('date')
                ->constrained('stock_requisitions')->nullOnDelete();
        });
    }
};
