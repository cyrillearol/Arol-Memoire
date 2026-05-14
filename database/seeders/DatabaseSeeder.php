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
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if (! $adminEmail || ! $adminPassword) {
            $this->command?->warn('ADMIN_EMAIL and ADMIN_PASSWORD are missing. Admin user was not seeded.');

            return;
        }

        User::updateOrCreate([
            'email' => $adminEmail,
        ], [
            'name' => env('ADMIN_NAME', 'Administrateur'),
            'role' => 'admin',
            'status' => 'actif',
            'email_verified_at' => now(),
            'password' => Hash::make($adminPassword),
        ]);
    }
}