<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Creează administratorul inițial, dacă nu există deja.
     * Login temporar: "admin" / parolă: "admin".
     * Este singurul superadmin din start — poate crea, șterge și
     * schimba rolul/statusul celorlalți admini.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['phone' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => 'admin',
                'role' => 'superadmin',
                'is_active' => true,
                'phone_needs_setup' => true,
                'activated_at' => now(),
            ]
        );
    }
}
