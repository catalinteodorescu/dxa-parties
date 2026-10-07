<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DXA: adaugat (runda 46 — permisiuni utilizatori). Conturile `admin` existente păstrează ce aveau până acum
// (totul, în afară de Utilizatori și Jurnal); conturile noi pornesc fără nicio permisiune.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('access_reception');
        });

        DB::table('admins')->where('role', '!=', 'superadmin')->update([
            'permissions' => json_encode(Permissions::legacyAdmin()),
        ]);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
