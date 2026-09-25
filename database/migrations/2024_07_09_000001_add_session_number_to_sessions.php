<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DXA: adaugat (Bar + Recepție — numerotarea sesiunilor). Adaugă `session_number` pe `sales_groups` și pe
 * `reception_sessions`: al câtelea rând e, per petrecere (sau per „fără petrecere”), în ordinea deschiderii.
 * Folosit doar pentru etichetare (SalesGroup::title()/label(), ReceptionSession::title()) — de exemplu
 * „(sesiune 2 · 25.09.2026) Latin Party”, ca să deosebească mai multe sesiuni ale aceleiași petreceri
 * (ex. un festival pe mai multe nopți). Calculat o singură dată la deschidere, nu recalculat la afișare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_groups', function (Blueprint $table) {
            $table->unsignedInteger('session_number')->default(1)->after('party_id');
        });
        Schema::table('reception_sessions', function (Blueprint $table) {
            $table->unsignedInteger('session_number')->default(1)->after('party_id');
        });

        $this->backfill('sales_groups');
        $this->backfill('reception_sessions');
    }

    /** Numerotează 1, 2, 3… fiecare grup de rânduri cu același party_id (inclusiv NULL), în ordinea id-ului. */
    private function backfill(string $table): void
    {
        $rows = DB::table($table)->orderBy('party_id')->orderBy('id')->get(['id', 'party_id']);

        $n = 0;
        $lastPartyId = false; // diferit de orice party_id posibil (inclusiv null), ca sa forteze primul reset
        foreach ($rows as $row) {
            if ($row->party_id !== $lastPartyId) {
                $n = 0;
                $lastPartyId = $row->party_id;
            }
            $n++;
            DB::table($table)->where('id', $row->id)->update(['session_number' => $n]);
        }
    }

    public function down(): void
    {
        Schema::table('reception_sessions', function (Blueprint $table) {
            $table->dropColumn('session_number');
        });
        Schema::table('sales_groups', function (Blueprint $table) {
            $table->dropColumn('session_number');
        });
    }
};
