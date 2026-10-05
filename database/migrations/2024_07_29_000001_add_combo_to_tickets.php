<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DXA: adaugat (runda 26). Combo-uri de bilete („3+1 gratis"): eticheta combo-ului și marcajul biletului oferit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('combo_label', 20)->nullable()->after('valid_until');
            $table->boolean('combo_free')->default(false)->after('combo_label');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['combo_label', 'combo_free']);
        });
    }
};
