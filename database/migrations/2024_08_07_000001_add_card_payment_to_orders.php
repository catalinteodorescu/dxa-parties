<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 65). Biletele plătite cu cardul (Stripe): comanda rămâne „în așteptarea plății” până la webhook.
 * `payment_status` (string) primește valorile card_pending / card / card_expired / card_failed / card_credited (fără schimbare de schemă);
 * biletele rezervate au statusul `pending` (coloana `tickets.status` e string de 10, încape).
 * Coloane noi pe `orders`: cât ține rezervarea, furnizorul și referința plății, momentul plății.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('payment_expires_at')->nullable()->index();
            $table->string('payment_provider', 20)->nullable();
            $table->string('payment_ref', 100)->nullable();
            $table->dateTime('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_expires_at', 'payment_provider', 'payment_ref', 'paid_at']);
        });
    }
};
