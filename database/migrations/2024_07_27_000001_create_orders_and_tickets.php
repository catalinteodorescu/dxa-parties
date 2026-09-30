<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Aplicația participanților - runda 14). Comenzi și bilete cumpărate din aplicație.
 *
 *  - orders: o comandă a unui participant la o petrecere (1..N bilete), cu totalul și codul de reducere folosit.
 *    `payment_status` = 'at_entry' (de plătit la intrare; plata online cu Stripe vine mai târziu și adaugă 'paid').
 *  - tickets: câte un rând per bilet, cu prețul înghețat (preț de listă, reducere, preț final). Biletul apare în contul
 *    `owner_participant_id` (cumpărătorul, sau participantul cu cont al cărui telefon a fost dat la cumpărare); `holder_participant_id`
 *    e titularul cu nume (primul bilet = cumpărătorul; celelalte doar dacă telefonul dat aparține unui cont); fără titular = bilet fără nume.
 *    `party_entry_id`/`used_at` se completează când biletul devine intrare la Recepție (runda următoare).
 * FK-urile sunt doar în cod, nu în DB (ca în restul aplicației).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('participant_id')->index();
            $table->unsignedBigInteger('party_id')->index();
            $table->unsignedSmallInteger('tickets_count');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->unsignedBigInteger('discount_code_id')->nullable()->index();
            $table->string('payment_status', 20)->default('at_entry');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('party_id')->index();
            $table->string('ticket_type', 80);
            $table->unsignedBigInteger('owner_participant_id')->index();
            $table->unsignedBigInteger('holder_participant_id')->nullable()->index();
            $table->string('holder_phone', 20)->nullable();
            $table->decimal('list_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('discount_code_id')->nullable()->index();
            $table->string('status', 10)->default('valid');   // valid | used | void
            $table->unsignedBigInteger('party_entry_id')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'ticket_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('orders');
    }
};
