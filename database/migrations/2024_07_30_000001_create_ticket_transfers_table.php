<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DXA: adaugat (runda 39). Istoricul transferurilor de bilete între participanți („Trimite biletul”). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('from_participant_id')->index();
            $table->unsignedBigInteger('to_participant_id')->index();
            $table->string('to_phone', 20);
            $table->boolean('to_had_account')->default(false);
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
