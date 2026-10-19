<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DXA: runda 79b. Contul de proprietar al instalării (vede pagina Proprietar; ascuns din Utilizatori, nu intră în limitele de conturi).
 * Se creează la instalare, o singură dată. Parola e salvată ca hash (bcrypt), nu în clar. Un cont care are deja numărul acesta
 * NU se atinge (migrarea sare peste, ca să nu schimbe un cont obișnuit). În teste nu se rulează (testele își fac singure conturile).
 */
return new class extends Migration
{
    public const NAME = 'Proprietar';

    public const PHONE = '0748960817';

    public const PASSWORD_HASH = '$2y$12$.oqJmWxTVxgoiLecFQCtqey2XG1cyyEicTnUBIx0e.b/WNveb7/bK';

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        self::ensureOwner();
    }

    /** @return 'created'|'exists' */
    public static function ensureOwner(): string
    {
        if (DB::table('admins')->where('phone', self::PHONE)->exists()) {
            return 'exists';
        }

        $now = now();
        DB::table('admins')->insert([
            'name' => self::NAME,
            'phone' => self::PHONE,
            'password' => self::PASSWORD_HASH,
            'role' => 'owner',
            'is_active' => true,
            'access_admin' => true,
            'access_bar' => false,
            'access_reception' => false,
            'permissions' => json_encode(['levels' => [], 'actions' => []]),
            'phone_needs_setup' => false,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'created';
    }

    public function down(): void
    {
        DB::table('admins')->where('phone', self::PHONE)->where('role', 'owner')->delete();
    }
};
