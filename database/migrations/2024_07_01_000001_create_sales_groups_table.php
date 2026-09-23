<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grup de vanzari = sesiunea comuna de vanzare a barului (un singur grup deschis
        // per petrecere, partajat intre barmani; sau un grup "fara petrecere" pentru
        // vanzarile din afara petrecerilor). Se inchide la finalizarea Raportarii care
        // l-a consumat (vezi stock_reports.sales_group_id).
        Schema::create('sales_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('status')->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();

            // Adminul care l-a creat manual (cand il deschide barmanul din PWA, ramane null).
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();

            // Barmanul care l-a deschis. FARA foreign key deocamdata: utilizatorii-barmani
            // (aplicatia DXA - Bar) nu exista inca; se adauga constrangerea odata cu ei.
            $table->unsignedBigInteger('bartender_id')->nullable();

            $table->timestamps();

            $table->index(['status', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_groups');
    }
};
