<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define Permissions by Module
        $modules = [
            'Dashboard' => [
                'dashboard.view',
            ],
            'Orders' => [
                'orders.view',
                'orders.create',
                'input custom bouquet',
                'orders.status.view',
                'orders.status.update',
                'orders.print',
                'orders.delete',
            ],
            'Customers' => [
                'customers.view',
                'customers.manage',
                'customers.delete',
            ],
            'Inventory' => [
                'inventory.view',
                'inventory.manage',
                'inventory.delete',
                'stock.view',
                'stock.manage',
            ],
            'Bouquets' => [
                'bouquets.view',
                'bouquets.manage',
                'bouquets.delete',
            ],
            'Deliveries' => [
                'deliveries.view',
                'deliveries.manage',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
            ],
            'System' => [
                'users.view',
                'users.manage',
                'roles.view',
                'roles.manage',
                'settings.manage',
                'logs.view',
            ],
        ];

        foreach ($modules as $module => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
        }

        // Create Roles and Assign Permissions

        // Super Admin
        $superAdminRole = Role::findOrCreate('super-admin', 'web');
        $superAdminRole->syncPermissions(Permission::all());

        // Admin
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions([
            'dashboard.view',
            'orders.view',
            'orders.create',
            'input custom bouquet',
            'orders.status.view',
            'orders.status.update',
            'orders.print',
            'customers.view',
            'customers.manage',
            'inventory.view',
            'inventory.manage',
            'stock.view',
            'stock.manage',
            'bouquets.view',
            'bouquets.manage',
            'deliveries.view',
            'deliveries.manage',
            'reports.view',
            'reports.export',
            'logs.view',
        ]);

        // Kasir
        $kasirRole = Role::findOrCreate('kasir', 'web');
        $kasirRole->syncPermissions([
            'dashboard.view',
            'orders.view',
            'orders.create',
            'input custom bouquet',
            'orders.status.view',
            'orders.print',
            'customers.view',
            'customers.manage',
            'bouquets.view',
            'stock.view',
        ]);

        // Manager
        $managerRole = Role::findOrCreate('manager', 'web');
        $managerRole->syncPermissions([
            'dashboard.view',
            'orders.view',
            'orders.status.view',
            'customers.view',
            'inventory.view',
            'bouquets.view',
            'deliveries.view',
            'stock.view',
            'reports.view',
            'reports.export',
        ]);

        // Ensure Super Admin User
        $user = User::where('email', 'super@bees.id')->first();
        if ($user) {
            $user->assignRole($superAdminRole);
        }
    }
}
