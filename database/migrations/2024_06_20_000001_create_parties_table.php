<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();

            // Identitate.
            $table->string('name');
            $table->string('image_path')->nullable();

            // Tip: 'basic' (petrecere simpla / periodica) | 'festival' (mai multe zile).
            $table->string('kind')->default('basic');

            // Timp. La 'basic' folosim date + start_time/end_time de pe petrecere.
            // La 'festival' orele stau pe fiecare zi (in JSON `days`); pastram totusi
            // start_date/end_date ca reper.
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Calculate la salvare (regula overnight: 21:00 -> 03:00 trece a doua zi).
            // Conduc sortarea si starile (viitoare / acum / trecute).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Locatie (text liber; sala proprie se poate precompleta din formular).
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->string('location_url')->nullable();   // link harta

            // Dresscode la 'basic'; la 'festival' e pe fiecare zi (in `days`).
            $table->string('dresscode')->nullable();

            // Pret de baza (la intrare). Treptele early-bird stau in JSON `price_tiers`.
            $table->decimal('price', 8, 2)->nullable();
            $table->boolean('is_free')->default(false);

            // Contact: fie un admin (snapshot nume+telefon la salvare), fie completat manual.
            $table->foreignId('contact_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_note')->nullable();

            // Audienta: 'all' (toata lumea) | 'auth' (doar utilizatorii logati).
            $table->string('audience')->default('all');

            // Plasare in carusel (mixt cu anunturile; ordinea se seteaza pe pagina de
            // setari homepage, mai tarziu). In lista de Petreceri apare oricum cand e vizibila.
            $table->boolean('in_carousel')->default(false);

            // Vizibilitate + stare de publicare (fara fereastra de publicare separata;
            // petrecerea "expira" singura dupa data evenimentului).
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('draft'); // draft | published

            // Continut imbricat (varianta hibrid - JSON).
            //  days:            [{ date, start_time, end_time, dresscode, program: [{ start, end, title, type, guest, room }] }]
            //  guests:          [{ name, country, style, style_other?, photo_path?, url? }]
            //  price_tiers:     [{ label, price, until }]
            //  payment_methods: ["cash","card","transfer","credite","revolut", ...custom]
            //  links:           [{ label, url }]
            //  custom_fields:   [{ label, value }]
            $table->json('days')->nullable();
            $table->json('guests')->nullable();
            $table->json('price_tiers')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('links')->nullable();
            $table->json('custom_fields')->nullable();

            // Descriere (ultima in formular; are buton de generare automata).
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index('starts_at');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
