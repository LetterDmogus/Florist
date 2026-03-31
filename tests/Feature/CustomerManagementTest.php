<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_crud_customer_with_restore_and_force_delete(): void
    {
        $kasir = User::factory()->create();
        $this->assignRole($kasir, 'kasir');

        $createResponse = $this
            ->actingAs($kasir)
            ->post(route('customers.store'), [
                'name' => 'Customer One',
                'phone_number' => '081200000001',
                'aliases' => ['Cust 1', 'Langganan'],
            ]);

        $createResponse->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Customer One',
            'phone_number' => '081200000001',
        ]);

        $customer = Customer::query()->firstOrFail();

        $updateResponse = $this
            ->actingAs($kasir)
            ->put(route('customers.update', $customer), [
                'name' => 'Customer One Updated',
                'phone_number' => '081200000001',
                'aliases' => ['Updated Alias'],
            ]);

        $updateResponse->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Customer One Updated',
            'phone_number' => '081200000001',
        ]);

        $deleteResponse = $this
            ->actingAs($kasir)
            ->delete(route('customers.destroy', $customer));

        $deleteResponse->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);

        $restoreResponse = $this
            ->actingAs($kasir)
            ->post(route('customers.restore', $customer->id));

        $restoreResponse->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);

        $this
            ->actingAs($kasir)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $forceDeleteResponse = $this
            ->actingAs($kasir)
            ->delete(route('customers.force-delete', $customer->id));

        $forceDeleteResponse->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }
}
