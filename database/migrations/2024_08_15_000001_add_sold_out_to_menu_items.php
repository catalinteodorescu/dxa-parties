<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DXA: adaugat (runda 72). Produs epuizat, marcat din aplicația de bar pentru O petrecere: la altă petrecere revine singur la vânzare.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sold_out_party_id')->nullable()->after('is_active');
            $table->timestamp('sold_out_at')->nullable()->after('sold_out_party_id');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['sold_out_party_id', 'sold_out_at']);
        });
    }
};
