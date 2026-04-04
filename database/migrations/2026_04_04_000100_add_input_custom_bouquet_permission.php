<?php

declare(strict_types=1);

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $permission = Permission::findOrCreate('input custom bouquet', 'web');

        Role::query()
            ->whereIn('name', ['super-admin', 'admin', 'kasir'])
            ->get()
            ->each(function (Role $role) use ($permission): void {
                $role->givePermissionTo($permission);
            });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $permission = Permission::query()
            ->where('name', 'input custom bouquet')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        Role::query()
            ->whereIn('name', ['super-admin', 'admin', 'kasir'])
            ->get()
            ->each(function (Role $role) use ($permission): void {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            });

        $permission->delete();
    }
};

