<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (runda 40). Contoare reale de afișări / deschideri / click-uri pentru petreceri și anunțuri (agregat pe zile; „unici” pe zi,
 * dintr-o amprentă zilnică anonimă) + petrecerile salvate (inima) de participanți.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_stats', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);          // party | announcement
            $table->unsignedBigInteger('subject_id');
            $table->date('day');
            $table->string('event', 20);                 // impression | open | action
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->unique(['subject_type', 'subject_id', 'day', 'event'], 'content_stats_unique');
        });

        // Dedupe pentru „unici pe zi”: păstrat doar azi și ieri (se curăță singur).
        Schema::create('content_visitors', function (Blueprint $table) {
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->date('day');
            $table->string('event', 20);
            $table->string('visitor', 16);
            $table->unique(['subject_type', 'subject_id', 'day', 'event', 'visitor'], 'content_visitors_unique');
            $table->index('day');
        });

        Schema::create('party_interests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('party_id')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['participant_id', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_interests');
        Schema::dropIfExists('content_visitors');
        Schema::dropIfExists('content_stats');
    }
};
