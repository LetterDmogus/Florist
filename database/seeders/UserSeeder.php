<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminEmail = (string) env('SEED_SUPERADMIN_EMAIL', 'super@bees.id');
        $superAdminName = (string) env('SEED_SUPERADMIN_NAME', 'Super Admin');
        $superAdminPassword = (string) env('SEED_SUPERADMIN_PASSWORD', 'password');

        // Seed only essential account for first login.
        $superAdmin = User::updateOrCreate([
            'email' => $superAdminEmail,
        ], [
            'name' => $superAdminName,
            'password' => Hash::make($superAdminPassword),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');
    }
}
