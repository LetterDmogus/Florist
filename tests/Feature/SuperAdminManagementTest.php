<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_can_open_user_and_role_management_pages(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('users.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('roles.index'))
            ->assertOk();
    }

    public function test_non_super_admin_cannot_open_super_admin_management_pages(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_from_management_page(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->post(route('users.store'), [
                'name' => 'Kasir Baru',
                'email' => 'kasir.baru@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role_name' => 'kasir',
            ])
            ->assertRedirect(route('users.index'));

        $newUser = User::where('email', 'kasir.baru@example.com')->first();

        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('kasir'));
    }

    public function test_super_admin_can_create_custom_role(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->post(route('roles.store'), [
                'name' => 'florist-staff',
                'permissions' => ['view bouquet', 'view customers'],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', [
            'name' => 'florist-staff',
            'guard_name' => 'web',
        ]);
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->put(route('users.update', $superAdmin), [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role_name' => 'admin',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');

        $superAdmin->refresh();

        $this->assertTrue($superAdmin->hasRole('super-admin'));
    }

    public function test_system_role_name_cannot_be_renamed(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');
        $adminRole = Role::findByName('admin', 'web');

        $this->actingAs($superAdmin)
            ->put(route('roles.update', $adminRole), [
                'name' => 'admin-baru',
                'permissions' => ['manage users'],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', [
            'id' => $adminRole->id,
            'name' => 'admin',
        ]);
    }

    public function test_user_delete_is_soft_delete_and_can_be_restored_or_force_deleted(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');
        $targetUser = User::factory()->create();
        $targetUser->assignRole('kasir');

        $this->actingAs($superAdmin)
            ->delete(route('users.destroy', $targetUser))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $targetUser->id]);

        $this->actingAs($superAdmin)
            ->post(route('users.restore', $targetUser->id))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('users.force-delete', $targetUser->id))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_role_delete_is_soft_delete_and_can_be_restored_or_force_deleted(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $role = Role::create([
            'name' => 'temporary-role',
            'guard_name' => 'web',
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertSoftDeleted('roles', ['id' => $role->id]);

        $this->actingAs($superAdmin)
            ->post(route('roles.restore', $role->id))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($superAdmin)
            ->delete(route('roles.force-delete', $role->id))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }
}
