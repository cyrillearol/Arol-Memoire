<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@elliryc.local'),
        ], [
            'name' => env('ADMIN_NAME', 'Administrateur TutorLink'),
            'role' => 'admin',
            'status' => 'actif',
            'email_verified_at' => now(),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
        ]);
    }
}
