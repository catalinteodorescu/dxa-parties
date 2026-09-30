<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 13b). „Bilete per comandă” fără valoare implicită: gol (null) = oricâte. Valorile 10 puse automat de
 * migrarea precedentă (implicitul de atunci) devin null, ca petrecerile existente să nu aibă o limită pe care nu ai setat-o tu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_tickets_per_order')->nullable()->default(null)->change();
        });

        DB::table('parties')->where('max_tickets_per_order', 10)->update(['max_tickets_per_order' => null]);
    }

    public function down(): void
    {
        DB::table('parties')->whereNull('max_tickets_per_order')->update(['max_tickets_per_order' => 10]);

        Schema::table('parties', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_tickets_per_order')->default(10)->nullable(false)->change();
        });
    }
};
