<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_delivery_with_restore_and_force_delete(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $customer = Customer::create([
            'name' => 'Delivery Customer',
            'phone_number' => '081322220001',
        ]);

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 150000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '10:30',
            'shipping_type' => 'pickup',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('deliveries.store'), [
                'order_id' => $order->id,
                'recipient_name' => 'Penerima A',
                'recipient_phone' => '081399990001',
                'full_address' => 'Jl. Mawar No. 1',
            ])
            ->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::query()->first();
        $this->assertNotNull($delivery);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_type' => 'delivery',
        ]);

        $this->actingAs($admin)
            ->put(route('deliveries.update', $delivery), [
                'recipient_name' => 'Penerima B',
                'recipient_phone' => '081399990002',
                'full_address' => 'Jl. Melati No. 2',
            ])
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'recipient_name' => 'Penerima B',
        ]);

        $this->actingAs($admin)
            ->delete(route('deliveries.destroy', $delivery))
            ->assertRedirect(route('deliveries.index'));

        $this->assertSoftDeleted('deliveries', [
            'id' => $delivery->id,
        ]);

        $this->actingAs($admin)
            ->post(route('deliveries.restore', $delivery->id))
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('deliveries.force-delete', $delivery->id))
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseMissing('deliveries', [
            'id' => $delivery->id,
        ]);
    }

    public function test_admin_can_lookup_orders_for_delivery_modal(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $customer = Customer::create([
            'name' => 'Lookup Delivery Customer',
            'phone_number' => '081322220099',
        ]);

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 99000,
            'shipping_date' => '2026-04-01',
            'shipping_time' => '14:00',
            'shipping_type' => 'pickup',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('deliveries.lookups.orders', [
            'search' => 'Lookup Delivery',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $order->id);
        $response->assertJsonPath('data.0.customer_name', 'Lookup Delivery Customer');
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $permissions = match ($roleName) {
            'admin' => ['deliveries.view'],
            default => [],
        };

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if (! empty($permissions)) {
            $role->syncPermissions($permissions);
        }

        $user->assignRole($role);
    }
}
