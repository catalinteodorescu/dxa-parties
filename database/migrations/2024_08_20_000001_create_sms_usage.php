<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: runda 80 — contorul de SMS. Un rând pe lună (`period` = AAAA-LL): câte SMS-uri a trimis aplicația, plus cota inclusă și prețul
 * depășirii de la ultimul trimis (instantaneu, ca raportul unei luni vechi să nu se schimbe dacă planul se schimbă ulterior).
 * `plans.sms_overage_eur`: prețul unui SMS peste cotă, în euro, fără TVA (implicit 0,07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_usage', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('included')->nullable();
            $table->decimal('overage_eur', 6, 3)->nullable();
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('sms_overage_eur', 6, 3)->default(0.07)->after('price_yearly_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('sms_overage_eur');
        });
        Schema::dropIfExists('sms_usage');
    }
};
