<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_group_id')->constrained('sales_groups')->restrictOnDelete();

            // app = inregistrata din aplicatia de bar; manual = introdusa din admin.
            $table->string('source')->default('manual');

            // UUID generat de client (PWA) - idempotenta: aceeasi vanzare trimisa de
            // doua ori nu se dubleaza.
            $table->uuid('client_uuid')->nullable()->unique();

            // Fara foreign key deocamdata (barmanii / participantii nu exista inca ca
            // entitati; se adauga constrangerile odata cu modulele lor).
            $table->unsignedBigInteger('bartender_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('identified_by')->nullable(); // qr | phone (cum a fost identificat participantul)

            $table->string('status')->default('completed'); // completed | cancelled
            $table->decimal('total', 10, 2)->default(0);    // SNAPSHOT: suma liniilor
            $table->timestamp('sold_at');

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('cancel_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_group_id', 'status']);
            $table->index('sold_at');
        });

        // Preturile si costul sunt SNAPSHOT la momentul vanzarii - nu se recalculeaza
        // retroactiv daca se schimba pretul din meniu sau costul ingredientelor.
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->timestamps();

            $table->index('menu_item_id');
        });

        // O vanzare poate avea mai multe plati (plata mixta).
        // amount = valoarea in lei acoperita de aceasta plata (la tokeni: tokens x token_rate).
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method'); // cash | token | credit | benefit
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('tokens')->nullable();       // doar la method = token
            $table->decimal('token_rate', 8, 2)->nullable();     // cursul de la momentul vanzarii
            $table->timestamps();

            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
