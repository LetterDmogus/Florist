<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderCashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_checkout_custom_bouquet_and_create_new_bouquet_unit(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer A',
            'phone_number' => '081234567890',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Birthday',
            'slug' => 'birthday',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '10:00',
                'shipping_type' => 'delivery',
                'delivery_mode' => 'new',
                'delivery_recipient_name' => 'Penerima A',
                'delivery_recipient_phone' => '081355550000',
                'delivery_full_address' => 'Jl. Melati No. 10',
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'custom',
                        'custom_category_id' => $category->id,
                        'custom_name' => 'Custom Sunrise',
                        'custom_price' => 250000,
                    ],
                ],
            ]);

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('bouquet_units', [
            'name' => 'Custom Sunrise',
            'price' => 250000.00,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'item_type' => 'bouquet',
            'quantity' => 1,
            'money_bouquet' => 250000.00,
        ]);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'recipient_name' => 'Penerima A',
            'recipient_phone' => '081355550000',
            'full_address' => 'Jl. Melati No. 10',
        ]);
    }

    public function test_kasir_can_checkout_with_new_customer_from_cashier_form(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Standard',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-NEW-CUST',
            'name' => 'Bouquet Kasir',
            'price' => 145000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_mode' => 'new',
                'new_customer_name' => 'Customer Walk In',
                'new_customer_phone_number' => '081355551111',
                'shipping_date' => '2026-03-31',
                'shipping_time' => '11:00',
                'shipping_type' => 'pickup',
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'catalog',
                        'bouquet_unit_id' => $unit->id,
                    ],
                ],
            ]);

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('customers', [
            'name' => 'Customer Walk In',
            'phone_number' => '081355551111',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'bouquet_unit_id' => $unit->id,
            'money_bouquet' => 145000.00,
        ]);
        $this->assertDatabaseMissing('deliveries', [
            'order_id' => $order->id,
        ]);
    }

    public function test_custom_mode_requires_input_custom_bouquet_permission(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, false);

        $customer = Customer::create([
            'name' => 'Customer B',
            'phone_number' => '081298765432',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Anniversary',
            'slug' => 'anniversary',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('orders.index'))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '13:00',
                'shipping_type' => 'pickup',
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'custom',
                        'custom_category_id' => $category->id,
                        'custom_name' => 'Custom Forbidden',
                        'custom_price' => 180000,
                    ],
                ],
            ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHasErrors('details.0.mode');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('bouquet_units', [
            'name' => 'Custom Forbidden',
        ]);
    }

    public function test_catalog_bouquet_stores_money_bouquet_snapshot(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer C',
            'phone_number' => '081211112222',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Standard',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-0001',
            'name' => 'Bouquet Classic',
            'price' => 175000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '15:00',
                'shipping_type' => 'delivery',
                'delivery_mode' => 'new',
                'delivery_recipient_name' => 'Penerima C',
                'delivery_recipient_phone' => '081355552222',
                'delivery_full_address' => 'Jl. Anggrek No. 7',
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'catalog',
                        'bouquet_unit_id' => $unit->id,
                    ],
                ],
            ]);

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'bouquet_unit_id' => $unit->id,
            'money_bouquet' => 175000.00,
            'quantity' => 1,
            'subtotal' => 175000.00,
        ]);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'recipient_name' => 'Penerima C',
            'recipient_phone' => '081355552222',
            'full_address' => 'Jl. Anggrek No. 7',
        ]);
    }

    public function test_delivery_mode_existing_can_reuse_saved_delivery_reference(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer D',
            'phone_number' => '081288889999',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Standard',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-DEL-REF',
            'name' => 'Bouquet Reference',
            'price' => 155000,
        ]);

        $sourceOrder = Order::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'total' => 100000,
            'shipping_date' => '2026-03-31',
            'shipping_time' => '09:00',
            'shipping_type' => 'delivery',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $deliveryReference = Delivery::create([
            'order_id' => $sourceOrder->id,
            'recipient_name' => 'Penerima Referensi',
            'recipient_phone' => '081366667777',
            'full_address' => 'Jl. Referensi No. 1',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '16:00',
                'shipping_type' => 'delivery',
                'delivery_mode' => 'existing',
                'delivery_id' => $deliveryReference->id,
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'catalog',
                        'bouquet_unit_id' => $unit->id,
                    ],
                ],
            ]);

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'recipient_name' => 'Penerima Referensi',
            'recipient_phone' => '081366667777',
            'full_address' => 'Jl. Referensi No. 1',
        ]);
    }

    private function prepareRole(User $user, bool $canCustomBouquet): void
    {
        $role = Role::create([
            'name' => 'kasir',
            'guard_name' => 'web',
        ]);

        if ($canCustomBouquet) {
            $permission = Permission::create([
                'name' => 'input custom bouquet',
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);
    }
}
