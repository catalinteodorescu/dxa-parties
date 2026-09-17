<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // Ciclul muzical al petrecerii: [{ style, frequency }], ex.
            // [{style: 'bachata', frequency: 3}, {style: 'salsa', frequency: 3}, {style: 'kizomba', frequency: 2}]
            // = 3 melodii bachata, apoi 3 salsa, apoi 2 kizomba, apoi se reia ciclul.
            $table->json('music_styles')->nullable()->after('guests');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('music_styles');
        });
    }
};
