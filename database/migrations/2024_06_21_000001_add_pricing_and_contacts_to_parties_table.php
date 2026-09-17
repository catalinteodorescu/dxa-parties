<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // Tipuri de bilet, fiecare cu reducerile lui:
            //   [{ name, price, discounts: [{ label, price, until }] }]
            $table->json('ticket_types')->nullable()->after('is_free');

            // Mai multe persoane de contact:
            //   [{ admin_id, name, phone, note }]
            $table->json('contacts')->nullable()->after('contact_note');
        });

        // Notă: coloanele vechi `price`, `price_tiers` și `contact_*` rămân în tabel
        // (nefolosite acum, păstrate pentru compatibilitate). Se pot elimina ulterior.
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['ticket_types', 'contacts']);
        });
    }
};
