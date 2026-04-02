<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_logs_permission_can_access_system_health_endpoint(): void
    {
        $admin = User::factory()->create();
        $this->assignLogsViewer($admin);

        $response = $this->actingAs($admin)->get(route('system.health'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['ok'],
                'cache' => ['ok'],
                'queue',
            ],
        ]);
    }

    public function test_user_without_logs_permission_cannot_access_system_health_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('system.health'))
            ->assertForbidden();
    }

    private function assignLogsViewer(User $user): void
    {
        Permission::findOrCreate('logs.view', 'web');

        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(['logs.view']);
        $user->assignRole($role);
    }
}
