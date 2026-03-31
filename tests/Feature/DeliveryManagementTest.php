<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }
}
