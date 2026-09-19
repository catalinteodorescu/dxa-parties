<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // Optional: daca raportarea rezolva un necesar existent (preincarca liniile lui).
            $table->foreignId('requisition_id')->nullable()->constrained('stock_requisitions')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reports');
    }
};
