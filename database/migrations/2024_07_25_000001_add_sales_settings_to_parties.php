<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Aplicația participanților - runda 13). Setările de vânzare și capacitate ale petrecerii.
 *
 *  - online_sales: se vând bilete online (în aplicație)? Implicit NU (petrecerile existente rămân doar afișate cu prețuri).
 *  - max_tickets_per_order: câte bilete se pot cumpăra într-o comandă (implicit 10).
 *  - tickets_for_sale: câte bilete în total se pot vinde online (null = nelimitat); nu contează dacă online_sales e oprit.
 *  - max_participants: capacitatea petrecerii; NU blochează nimic, doar atenționează la Recepție când se atinge.
 * Limita și treptele de preț pe tip de bilet stau în `ticket_types` (JSON), lângă restul câmpurilor biletului.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('online_sales')->default(false);
            $table->unsignedSmallInteger('max_tickets_per_order')->default(10);
            $table->unsignedInteger('tickets_for_sale')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['online_sales', 'max_tickets_per_order', 'tickets_for_sale', 'max_participants']);
        });
    }
};
