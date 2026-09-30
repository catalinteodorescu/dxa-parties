<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DXA: adaugat (runda 15). Termenul „intri cu acest preț până la" al unui bilet (null = biletul nu expiră). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dateTime('valid_until')->nullable()->after('discount_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
