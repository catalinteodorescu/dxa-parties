<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Nullable: doar scolile care folosesc tokeni (bilete de la receptie, ex. 1 tk = 5 lei
            // la DXA - vezi config/dxa.php) completeaza aceasta coloana. Cand se foloseste,
            // pretul in tokeni e sursa de adevar, iar `price` (lei) se calculeaza automat din el.
            $table->decimal('tokens', 8, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('tokens');
        });
    }
};
