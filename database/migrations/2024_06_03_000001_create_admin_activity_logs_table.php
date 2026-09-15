<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();

            // Cine a facut actiunea. FK cu nullOnDelete - daca adminul e sters,
            // log-ul ramane, doar legatura catre rand se pierde (avem actor_label
            // ca sa stim oricum cine a fost).
            $table->foreignId('actor_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('actor_label')->nullable();

            $table->string('action');
            $table->string('description');

            // Pe cine vizeaza actiunea (ex: alt admin caruia i s-a schimbat rolul).
            // Fara FK - poate referi un admin deja sters intre timp.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            $table->string('ip_address')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
