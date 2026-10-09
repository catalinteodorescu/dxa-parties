<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 67). Anularea unui bilet din admin: cine, când, de ce și cât s-a returnat în credite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancel_reason', 250)->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason', 'refunded_amount']);
        });
    }
};
