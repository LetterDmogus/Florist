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
        $password = Hash::make('password');

        // Super Admin
        $superAdmin = User::firstOrCreate([
            'email' => 'super@bees.id',
        ], [
            'name' => 'Super Admin',
            'password' => $password,
        ]);
        $superAdmin->assignRole('super-admin');

        // Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@bees.id',
        ], [
            'name' => 'Admin Bees',
            'password' => $password,
        ]);
        $admin->assignRole('admin');

        // Manager
        $manager = User::firstOrCreate([
            'email' => 'manager@bees.id',
        ], [
            'name' => 'Manager Bees',
            'password' => $password,
        ]);
        $manager->assignRole('manager');

        // Kasir
        $kasir = User::firstOrCreate([
            'email' => 'kasir@bees.id',
        ], [
            'name' => 'Kasir Bees',
            'password' => $password,
        ]);
        $kasir->assignRole('kasir');
    }
}
