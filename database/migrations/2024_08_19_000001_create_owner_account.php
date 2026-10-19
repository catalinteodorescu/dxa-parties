<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Runda 79b: contul de proprietar. Din runda 84 NU se mai creează din migrare (telefonul și parola proprietarului nu mai sunt în cod):
 * îl creează `php artisan app:install --owner-phone=... --owner-password=...` (App\Services\Installer::ensureOwner).
 * Migrarea rămâne ca să nu se piardă istoricul; pe instalările existente contul de proprietar deja creat nu se atinge.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
