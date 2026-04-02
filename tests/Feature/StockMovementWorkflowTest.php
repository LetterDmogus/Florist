<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StockMovementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_purchase_and_sale_movements(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $item = $this->createItem(stock: 10, price: 50000);
        $customer = Customer::create([
            'name' => 'Sale Customer',
            'phone_number' => '081366660001',
        ]);

        $this->actingAs($admin)
            ->post(route('stock-movements.store'), [
                'movement_type' => 'purchase',
                'item_id' => $item->id,
                'quantity' => 5,
                'price_at_the_time' => 30000,
                'description' => 'Restock mingguan',
            ])
            ->assertRedirect(route('stock-movements.index'));

        $item->refresh();
        $this->assertSame(15, $item->stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => 'in',
            'quantity' => 5,
            'price_at_the_time' => 30000.00,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-movements.store'), [
                'movement_type' => 'sale',
                'item_id' => $item->id,
                'quantity' => 2,
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'shipping_date' => '2026-03-31',
                'shipping_time' => '13:00',
                'shipping_type' => 'pickup',
                'description' => 'Jual langsung dari stok',
            ])
            ->assertRedirect(route('stock-movements.index'));

        $item->refresh();
        $this->assertSame(13, $item->stock);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customer->id,
            'total' => 100000.00,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'item_type' => 'inventory_item',
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'subtotal' => 100000.00,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => 'sold',
            'order_id' => $order->id,
            'quantity' => 2,
            'price_at_the_time' => 50000.00,
            'total' => 100000.00,
        ]);
    }

    public function test_admin_cannot_create_outbound_movement_when_stock_is_insufficient(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $item = $this->createItem(stock: 1, price: 25000);

        $response = $this->actingAs($admin)
            ->from(route('stock-movements.index'))
            ->post(route('stock-movements.store'), [
                'movement_type' => 'usage',
                'item_id' => $item->id,
                'quantity' => 3,
                'description' => 'Pemakaian berlebih',
            ]);

        $response->assertRedirect(route('stock-movements.index'));
        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_details', 0);
    }

    private function createItem(int $stock, int $price): ItemUnit
    {
        $category = ItemCategory::create([
            'name' => 'Stock Category',
            'slug' => 'stock-category',
        ]);

        return ItemUnit::create([
            'category_id' => $category->id,
            'serial_number' => 'ITM-0001',
            'name' => 'Pita Satin',
            'price' => $price,
            'individual' => true,
            'stock' => $stock,
        ]);
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $permissions = match ($roleName) {
            'admin' => ['stock.view', 'stock.manage'],
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
