<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Card de fidelitate — corecții). `batch`: uuid comun pentru ștampilele create în ACELAȘI apel
 * (ajustare manuală de N ștampile deodată, sau preluarea ștampilelor unui card fizic la înrolare) — ca la
 * `party_entries.batch`. Scopul: în istoricul din fișa participantului, o ajustare de „+5" apare ca UN singur
 * rând, nu 5. Ștampilele automate de la intrări (`source = entry`) n-au niciodată batch — fiecare rămâne un
 * rând propriu, ceea ce e corect (sunt intrări reale, distincte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_stamps', function (Blueprint $table) {
            $table->uuid('batch')->nullable()->after('source');
            $table->index('batch');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_stamps', function (Blueprint $table) {
            $table->dropIndex(['batch']);
            $table->dropColumn('batch');
        });
    }
};
