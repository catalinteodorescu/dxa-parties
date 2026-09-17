<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('body')->nullable();          // text scurt (afisat mai ales in zona de anunturi)
            $table->string('image_path')->nullable();
            $table->string('url')->nullable();
            $table->string('url_label')->nullable();    // eticheta butonului (CTA), ex: "Vezi detalii"

            // Audienta: 'all' (toata lumea) | 'auth' (doar utilizatorii logati)
            $table->string('audience')->default('all');

            // Plasare — un anunt poate fi in ambele simultan.
            $table->boolean('in_carousel')->default(false);
            $table->boolean('in_list')->default(true);

            // Activare/dezactivare manuala.
            $table->boolean('is_active')->default(true);

            // Fereastra de afisare. starts_at se seteaza la creare (implicit "acum").
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Ordine comuna (drag & drop). Caruselul si lista respecta aceeasi
            // ordine relativa, filtrata dupa flag-ul de plasare.
            $table->integer('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
