<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DXA: runda 82 — costul real al SMS-urilor lunii (suma `transaction_cost` raportată de SMSO, în eurocenți), pentru pagina Proprietar. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_usage', function (Blueprint $table) {
            $table->decimal('cost_eurocents', 12, 3)->default(0)->after('overage_eur');
        });
    }

    public function down(): void
    {
        Schema::table('sms_usage', function (Blueprint $table) {
            $table->dropColumn('cost_eurocents');
        });
    }
};
