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
        $response->assertRedirect(route('cashier.index'));
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
        $response->assertRedirect(route('cashier.index'));
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

    public function test_checkout_is_idempotent_for_same_request_id(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Idempotent',
            'phone_number' => '081277771111',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Idempotent Type',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-IDEMP-001',
            'name' => 'Bouquet Idempotent',
            'price' => 160000,
        ]);

        $payload = [
            'request_id' => '5b8ed97d-5ce9-45e6-b0c4-c86d6af0c701',
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'shipping_date' => '2026-04-02',
            'shipping_time' => '11:00',
            'shipping_type' => 'pickup',
            'details' => [
                [
                    'item_type' => 'bouquet',
                    'mode' => 'catalog',
                    'bouquet_unit_id' => $unit->id,
                ],
            ],
        ];

        $firstResponse = $this
            ->actingAs($user)
            ->post(route('orders.store'), $payload);

        $secondResponse = $this
            ->actingAs($user)
            ->post(route('orders.store'), $payload);

        $firstResponse->assertRedirect(route('cashier.index'));
        $secondResponse->assertRedirect(route('cashier.index'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_details', 1);
        $this->assertDatabaseHas('orders', [
            'request_id' => $payload['request_id'],
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
            ->from(route('cashier.index'))
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

        $response->assertRedirect(route('cashier.index'));
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
        $response->assertRedirect(route('cashier.index'));
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'recipient_name' => 'Penerima Referensi',
            'recipient_phone' => '081366667777',
            'full_address' => 'Jl. Referensi No. 1',
        ]);
    }

    public function test_order_with_partial_down_payment_is_saved_as_dp(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer DP',
            'phone_number' => '081277770001',
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
            'serial_number' => 'BQT-DP-001',
            'name' => 'Bouquet DP',
            'price' => 200000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '17:00',
                'shipping_type' => 'pickup',
                'down_payment' => 50000,
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total' => 200000.00,
            'down_payment' => 50000.00,
            'payment_status' => 'dp',
        ]);
    }

    public function test_order_without_down_payment_is_saved_as_paid(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer No DP',
            'phone_number' => '081277770010',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Simple',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-NODP-001',
            'name' => 'Bouquet No DP',
            'price' => 180000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '17:10',
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total' => 180000.00,
            'down_payment' => null,
            'payment_status' => 'paid',
        ]);
    }

    public function test_order_with_full_down_payment_is_saved_as_paid(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Full Pay',
            'phone_number' => '081277770002',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Premium',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-PAID-001',
            'name' => 'Bouquet Full Paid',
            'price' => 300000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '17:30',
                'shipping_type' => 'pickup',
                'down_payment' => 300000,
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total' => 300000.00,
            'down_payment' => 300000.00,
            'payment_status' => 'paid',
        ]);
    }

    public function test_order_rejects_down_payment_higher_than_item_subtotal(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Over DP',
            'phone_number' => '081277770011',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Simple',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-OVDP-001',
            'name' => 'Bouquet Over DP',
            'price' => 100000,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('cashier.index'))
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '17:20',
                'shipping_type' => 'delivery',
                'shipping_fee' => 25000,
                'down_payment' => 120000,
                'delivery_mode' => 'new',
                'delivery_recipient_name' => 'Penerima',
                'delivery_recipient_phone' => '081377778888',
                'delivery_full_address' => 'Jl. Test',
                'details' => [
                    [
                        'item_type' => 'bouquet',
                        'mode' => 'catalog',
                        'bouquet_unit_id' => $unit->id,
                    ],
                ],
            ]);

        $response->assertRedirect(route('cashier.index'));
        $response->assertSessionHasErrors('down_payment');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_order_shipping_fee_is_stored_and_synced_to_report_entries(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Ongkir',
            'phone_number' => '081277770003',
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
            'serial_number' => 'BQT-SHIP-001',
            'name' => 'Bouquet Ongkir',
            'price' => 200000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '18:00',
                'shipping_type' => 'delivery',
                'shipping_fee' => 15000,
                'delivery_mode' => 'new',
                'delivery_recipient_name' => 'Penerima Ongkir',
                'delivery_recipient_phone' => '081366660000',
                'delivery_full_address' => 'Jl. Ongkir No. 99',
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_fee' => 15000.00,
            'total' => 215000.00,
        ]);
        $this->assertDatabaseHas('report_entries', [
            'order_id' => $order->id,
            'category' => 'shipping_expense',
            'amount_idr' => 15000.00,
            'description' => "Ongkir Order #{$order->id}",
        ]);
    }

    public function test_pickup_order_forces_shipping_fee_zero_and_has_no_shipping_report_entry(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Pickup',
            'phone_number' => '081277770004',
        ]);

        $category = BouquetCategory::create([
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'Basic',
            'is_custom' => false,
        ]);

        $unit = BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'BQT-PICK-001',
            'name' => 'Bouquet Pickup',
            'price' => 120000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('orders.store'), [
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '18:30',
                'shipping_type' => 'pickup',
                'shipping_fee' => 50000,
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
        $response->assertRedirect(route('cashier.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_type' => 'pickup',
            'shipping_fee' => 0.00,
            'total' => 120000.00,
        ]);
        $this->assertDatabaseMissing('report_entries', [
            'order_id' => $order->id,
            'category' => 'shipping_expense',
        ]);
    }

    public function test_cashier_page_is_accessible_via_cashier_route(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $this->actingAs($user)
            ->get(route('cashier.index'))
            ->assertOk();
    }

    public function test_legacy_order_resource_routes_are_disabled(): void
    {
        $user = User::factory()->create();
        $this->prepareRole($user, true);

        $customer = Customer::create([
            'name' => 'Customer Legacy Route',
            'phone_number' => '081288881234',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'total' => 100000,
            'shipping_date' => '2026-04-02',
            'shipping_time' => '10:00',
            'shipping_type' => 'pickup',
            'payment_status' => 'paid',
            'order_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertRedirect(route('cashier.index'));

        $this->actingAs($user)
            ->get(route('orders.create'))
            ->assertRedirect(route('cashier.index'));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertRedirect(route('orders.status.index'));

        $this->actingAs($user)
            ->put(route('orders.update', $order))
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('orders.destroy', $order))
            ->assertNotFound();
    }

    private function prepareRole(User $user, bool $canCustomBouquet): void
    {
        $role = Role::create([
            'name' => 'kasir',
            'guard_name' => 'web',
        ]);

        $permissions = ['orders.view', 'orders.create'];

        if ($canCustomBouquet) {
            $permissions[] = 'input custom bouquet';
        }

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $role->syncPermissions($permissions);

        $user->assignRole($role);
    }
}
