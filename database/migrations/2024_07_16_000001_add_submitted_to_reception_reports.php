<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (PWA Recepție - raportare). Recepționerul poate „trimite” raportarea din aplicație (a numărat casa):
 * draftul rămâne draft, dar e marcat ca trimis (cine și când). Cât e trimis, nu se mai înregistrează și nu se mai anulează
 * nimic în sesiune; adminul o finalizează (închide casa) sau o redeschide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('finalized_at');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reception_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
        });
    }
};
