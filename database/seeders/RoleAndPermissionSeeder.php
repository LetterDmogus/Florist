<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage users',
            'manage roles',
            'manage inventory',
            'view inventory',
            'manage bouquet',
            'view bouquet',
            'manage orders',
            'view orders',
            'manage customers',
            'view customers',
            'view reports',
            'view activity log',
            'view dashboard admin',
            'view master data',
            'input order baru',
            'input jumlah dp',
            'input custom bouquet',
            'input pergerakan inventory',
            'backup manual',
            'import export data',
            'hard delete data',
            'site setting',
        ];

        foreach (array_unique($permissions) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Create Roles and Assign Permissions

        // Super Admin: All permissions
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Admin
        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
            'view dashboard admin',
            'view reports',
            'view master data',
            'view activity log',
            'manage inventory',
            'input pergerakan inventory',
            'manage bouquet',
            'manage customers',
            'manage orders',
            'input order baru',
        ]);

        // Manager
        $manager = Role::findOrCreate('manager', 'web');
        $manager->syncPermissions([
            'view reports',
            'view inventory',
            'view bouquet',
            'view orders',
            'view customers',
            'input pergerakan inventory',
            'view dashboard admin',
        ]);

        // Kasir
        $kasir = Role::findOrCreate('kasir', 'web');
        $kasir->syncPermissions([
            'input order baru',
            'view orders',
            'manage customers',
            'input jumlah dp',
            'input custom bouquet',
            'view bouquet',
        ]);
    }
}
