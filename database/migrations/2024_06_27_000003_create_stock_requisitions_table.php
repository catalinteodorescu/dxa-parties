<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requisitions', function (Blueprint $table) {
            $table->id();
            // Auto-generat la creare (ex. "Necesar 12.10.2026"), editabil.
            $table->string('label');
            // open: creat, inca nimic primit. fulfilled: o raportare a acoperit
            // (partial sau complet) toate liniile. closed: inchis manual (ex. anulat).
            $table->string('status')->default('open');
            // Legatura optionala cu o petrecere (util pt. rapoarte pe eveniment mai tarziu).
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requisitions');
    }
};
