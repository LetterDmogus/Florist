<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReportEntry;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportMonthlyTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_monthly_report_with_excel_like_aggregates(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $month = 3;
        $year = 2026;

        $customer = Customer::create([
            'name' => 'Report Customer',
            'phone_number' => '081311110001',
        ]);

        $bouquetUnit = $this->createMoneyBouquetUnit();

        $order = Order::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'total' => 300000,
            'shipping_date' => '2026-03-10',
            'shipping_time' => '09:00',
            'shipping_type' => 'pickup',
            'payment_status' => 'paid',
            'order_status' => 'completed',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'item_type' => 'bouquet',
            'quantity' => 1,
            'subtotal' => 300000,
            'bouquet_unit_id' => $bouquetUnit->id,
            'money_bouquet' => 200000,
            'inventory_item_id' => null,
        ]);

        $item = $this->createItem(price: 50000, stock: 5);
        StockMovement::create([
            'item_id' => $item->id,
            'user_id' => $admin->id,
            'quantity' => 1,
            'price_at_the_time' => 50000,
            'total' => 50000,
            'description' => 'Pembelian stok report',
            'type' => 'in',
        ]);

        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-05',
            'category' => 'supply_income',
            'description' => 'Pendapatan supply bulanan',
            'amount_idr' => 20000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-07',
            'category' => 'store_expense',
            'description' => 'Biaya admin',
            'amount_idr' => 10000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-08',
            'category' => 'raw_material_expense',
            'description' => 'Bahan baku tambahan',
            'amount_idr' => 7000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-09',
            'category' => 'shipping_expense',
            'description' => 'Topup gosend',
            'amount_idr' => 6000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-11',
            'category' => 'purchase_supply',
            'description' => 'Pembelian supply tambahan',
            'amount_idr' => 30000,
            'freight_idr' => 5000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-12',
            'category' => 'refund',
            'description' => 'Refund RMB',
            'amount_rmb' => 10,
            'exchange_rate' => 2000,
        ]);
        ReportEntry::create([
            'user_id' => $admin->id,
            'occurred_on' => '2026-03-13',
            'category' => 'profit_adjustment',
            'description' => 'Penyesuaian bonus',
            'amount_idr' => -2000,
        ]);

        $salesResponse = $this->actingAs($admin)->get(route('reports.sales.index', [
            'month' => $month,
            'year' => $year,
        ]));

        $salesResponse->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Sales')
            ->where('salesSummary.money', fn ($value): bool => (float) $value === 200000.0)
            ->where('salesSummary.fee', fn ($value): bool => (float) $value === 100000.0)
            ->where('salesSummary.total', fn ($value): bool => (float) $value === 300000.0)
            ->where('profitSummary.gross_profit', fn ($value): bool => (float) $value === 53000.0)
            ->where('profitSummary.net_profit', fn ($value): bool => (float) $value === 51000.0)
        );

        $purchaseResponse = $this->actingAs($admin)->get(route('reports.purchases.index', [
            'month' => $month,
            'year' => $year,
        ]));

        $purchaseResponse->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Purchases')
            ->where('purchaseSummary.purchase_total', fn ($value): bool => (float) $value === 50000.0)
            ->where('purchaseSummary.supply_purchase_total', fn ($value): bool => (float) $value === 35000.0)
            ->where('purchaseSummary.refund_idr_total', fn ($value): bool => (float) $value === 20000.0)
            ->where('purchaseSummary.grand_total', fn ($value): bool => (float) $value === 47000.0)
        );
    }

    public function test_manager_cannot_manage_report_entries_crud(): void
    {
        $manager = User::factory()->create();
        $this->assignRole($manager, 'manager');

        $response = $this->actingAs($manager)->post(route('reports.entries.store'), [
            'occurred_on' => '2026-03-01',
            'category' => 'store_expense',
            'description' => 'Biaya manager',
            'amount_idr' => 10000,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('report_entries', 0);
    }

    public function test_admin_can_crud_report_entries(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $this->actingAs($admin)
            ->post(route('reports.entries.store'), [
                'occurred_on' => '2026-03-01',
                'category' => 'store_expense',
                'description' => 'Biaya awal',
                'amount_idr' => 12000,
            ])
            ->assertRedirect();

        $entry = ReportEntry::query()->first();
        $this->assertNotNull($entry);

        $this->actingAs($admin)
            ->put(route('reports.entries.update', $entry), [
                'occurred_on' => '2026-03-02',
                'category' => 'raw_material_expense',
                'description' => 'Biaya update',
                'amount_idr' => 15000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_entries', [
            'id' => $entry->id,
            'category' => 'raw_material_expense',
            'description' => 'Biaya update',
        ]);

        $this->actingAs($admin)
            ->delete(route('reports.entries.destroy', $entry))
            ->assertRedirect();

        $this->assertSoftDeleted('report_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_admin_can_export_sales_and_purchase_reports(): void
    {
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $salesExport = $this->actingAs($admin)->get(route('reports.sales.export', [
            'month' => 3,
            'year' => 2026,
        ]));

        $salesExport->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $salesExport->headers->get('content-type')
        );
        $this->assertStringContainsString('.xlsx', (string) $salesExport->headers->get('content-disposition'));

        $purchaseExport = $this->actingAs($admin)->get(route('reports.purchases.export', [
            'month' => 3,
            'year' => 2026,
        ]));

        $purchaseExport->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $purchaseExport->headers->get('content-type')
        );
        $this->assertStringContainsString('.xlsx', (string) $purchaseExport->headers->get('content-disposition'));
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);
    }

    private function createMoneyBouquetUnit(): BouquetUnit
    {
        $category = BouquetCategory::create([
            'name' => 'Money Bouquet Category',
            'slug' => 'money-bouquet-category',
        ]);

        $type = BouquetType::create([
            'category_id' => $category->id,
            'name' => 'MB',
            'description' => 'Money Bouquet',
            'is_custom' => false,
        ]);

        return BouquetUnit::create([
            'type_id' => $type->id,
            'serial_number' => 'MB-0001',
            'name' => 'Money Bouquet Unit',
            'price' => 300000,
        ]);
    }

    private function createItem(int $price, int $stock): ItemUnit
    {
        $category = ItemCategory::create([
            'name' => 'Report Item Category',
            'slug' => 'report-item-category',
        ]);

        return ItemUnit::create([
            'category_id' => $category->id,
            'serial_number' => 'RPT-ITM-01',
            'name' => 'Report Item',
            'price' => $price,
            'individual' => true,
            'stock' => $stock,
        ]);
    }
}
