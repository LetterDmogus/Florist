<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_order_status_page(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $response = $this
            ->actingAs($admin)
            ->get(route('orders.status.index'));

        $response->assertOk();
    }

    public function test_kasir_cannot_view_order_status_page(): void
    {
        $kasir = User::factory()->create();
        $this->assignRole($kasir, 'kasir');

        $response = $this
            ->actingAs($kasir)
            ->get(route('orders.status.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $customer = Customer::create([
            'name' => 'Status Customer',
            'phone_number' => '081200001111',
        ]);

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 150000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '10:00',
            'shipping_type' => 'delivery',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('orders.status.update', $order), [
                'order_status' => 'processing',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'processing',
        ]);
    }

    public function test_kasir_cannot_update_order_status(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');
        $kasir = User::factory()->create();
        $this->assignRole($kasir, 'kasir');

        $customer = Customer::create([
            'name' => 'Status Customer B',
            'phone_number' => '081200002222',
        ]);

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 200000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '11:00',
            'shipping_type' => 'pickup',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $response = $this
            ->actingAs($kasir)
            ->patch(route('orders.status.update', $order), [
                'order_status' => 'completed',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'pending',
        ]);
    }

    public function test_admin_cannot_move_back_order_from_final_status(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $customer = Customer::create([
            'name' => 'Status Customer Final',
            'phone_number' => '081200003333',
        ]);

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 210000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '12:00',
            'shipping_type' => 'delivery',
            'payment_status' => 'unpaid',
            'order_status' => 'completed',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('orders.status.index'))
            ->patch(route('orders.status.update', $order), [
                'order_status' => 'on_delivery',
            ]);

        $response->assertRedirect(route('orders.status.index'));
        $response->assertSessionHasErrors('order_status');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'completed',
        ]);
    }

    public function test_admin_can_search_order_status_list_by_customer_name(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $customerMatch = Customer::create([
            'name' => 'Customer Match',
            'phone_number' => '081200004444',
        ]);

        $customerOther = Customer::create([
            'name' => 'Customer Other',
            'phone_number' => '081200005555',
        ]);

        Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customerMatch->id,
            'total' => 120000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '09:30',
            'shipping_type' => 'pickup',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customerOther->id,
            'total' => 180000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '10:30',
            'shipping_type' => 'delivery',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('orders.status.index', [
                'search' => 'Match',
            ]));

        $response->assertOk();
        $response->assertSee('Customer Match');
        $response->assertDontSee('Customer Other');
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
