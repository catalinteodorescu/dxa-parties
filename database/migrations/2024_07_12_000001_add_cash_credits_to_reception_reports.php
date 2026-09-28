<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Portofelul de credite - Etapa 2). Raportarea de recepție tratează vânzarea de credite exact ca
 * pe cea de tokeni: `cash_credits` (partea de cash din vânzarea de credite) se îngheață la finalizare, alături
 * de `cash_tokens`, și intră în formula cash-ului așteptat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->decimal('cash_credits', 10, 2)->nullable()->after('cash_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->dropColumn('cash_credits');
        });
    }
};
