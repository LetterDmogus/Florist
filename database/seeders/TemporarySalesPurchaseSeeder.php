<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SyncOrderShippingExpenseAction;
use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReportEntry;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemporarySalesPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = $this->resolveUser();
            $customer = $this->resolveCustomer();
            [$moneyUnit, $regularUnit, $itemUnit] = $this->resolveCatalogData();

            $this->seedSalesData($user, $customer, $moneyUnit, $regularUnit);
            $this->seedPurchaseData($user, $itemUnit);
            $this->seedManualReportEntries($user);
        });

        $this->command?->info('TemporarySalesPurchaseSeeder selesai: data penjualan & pembelian contoh sudah terisi.');
    }

    private function resolveUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'super@bees.id'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );
    }

    private function resolveCustomer(): Customer
    {
        return Customer::query()->updateOrCreate(
            ['phone_number' => '089900001111'],
            [
                'name' => 'Customer Seeder Sementara',
                'aliases' => [],
            ],
        );
    }

    /**
     * @return array{0:BouquetUnit,1:BouquetUnit,2:ItemUnit}
     */
    private function resolveCatalogData(): array
    {
        $bouquetCategory = BouquetCategory::query()->updateOrCreate(
            ['slug' => 'temp-seeder-report'],
            ['name' => 'Temp Seeder Report'],
        );

        $moneyType = BouquetType::query()->updateOrCreate(
            ['category_id' => $bouquetCategory->id, 'name' => 'Money Bouquet Temp'],
            ['description' => 'Tipe sementara untuk data laporan', 'is_custom' => false],
        );

        $regularType = BouquetType::query()->updateOrCreate(
            ['category_id' => $bouquetCategory->id, 'name' => 'Bouquet Reguler Temp'],
            ['description' => 'Tipe sementara untuk data laporan', 'is_custom' => false],
        );

        $moneyUnit = BouquetUnit::query()->updateOrCreate(
            ['serial_number' => 'TMP-MONEY-BQT-001'],
            [
                'type_id' => $moneyType->id,
                'name' => 'Money Bouquet Seeder',
                'description' => 'Data contoh sementara',
                'price' => 250000,
                'is_active' => true,
            ],
        );

        $regularUnit = BouquetUnit::query()->updateOrCreate(
            ['serial_number' => 'TMP-REG-BQT-001'],
            [
                'type_id' => $regularType->id,
                'name' => 'Bouquet Reguler Seeder',
                'description' => 'Data contoh sementara',
                'price' => 180000,
                'is_active' => true,
            ],
        );

        $itemCategory = ItemCategory::query()->updateOrCreate(
            ['slug' => 'temp-seeder-item'],
            ['name' => 'Temp Seeder Item'],
        );

        $itemUnit = ItemUnit::query()->updateOrCreate(
            ['serial_number' => 'TMP-ITEM-001'],
            [
                'category_id' => $itemCategory->id,
                'name' => 'Kertas Bungkus Seeder',
                'price' => 20000,
                'individual' => 'Pcs',
                'description' => 'Data contoh sementara',
                'stock' => 300,
            ],
        );

        return [$moneyUnit, $regularUnit, $itemUnit];
    }

    private function seedSalesData(User $user, Customer $customer, BouquetUnit $moneyUnit, BouquetUnit $regularUnit): void
    {
        $period = now()->format('Ym');

        for ($i = 1; $i <= 14; $i++) {
            $shippingDate = CarbonImmutable::now()
                ->startOfMonth()
                ->addDays(min($i * 2, 27))
                ->toDateString();

            $isMoneyBouquet = $i % 3 === 0;
            $unit = $isMoneyBouquet ? $moneyUnit : $regularUnit;
            $subtotal = (float) $unit->price;
            $shippingType = $i % 2 === 0 ? 'delivery' : 'pickup';
            $shippingFee = $shippingType === 'delivery' ? (float) (10000 + (($i % 4) * 5000)) : 0.0;
            $total = round($subtotal + $shippingFee, 2);

            $order = Order::query()->updateOrCreate(
                ['request_id' => "temp-sales-{$period}-".str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                    'total' => $total,
                    'shipping_date' => $shippingDate,
                    'shipping_time' => sprintf('%02d:00:00', 9 + ($i % 8)),
                    'shipping_type' => $shippingType,
                    'shipping_fee' => $shippingFee,
                    'down_payment' => null,
                    'payment_status' => 'paid',
                    'order_status' => 'completed',
                    'description' => 'TEMP-SEED-SALES',
                ],
            );

            $order->orderDetails()->delete();

            OrderDetail::query()->create([
                'order_id' => $order->id,
                'item_type' => 'bouquet',
                'quantity' => 1,
                'subtotal' => $subtotal,
                'bouquet_unit_id' => $unit->id,
                'inventory_item_id' => null,
                'money_bouquet' => $isMoneyBouquet ? $subtotal : null,
                'greeting_card' => null,
                'sender_name' => null,
            ]);

            if ($shippingType === 'delivery') {
                $order->delivery()->updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'recipient_name' => 'Penerima Seeder',
                        'recipient_phone' => '089900002222',
                        'full_address' => 'Jl. Seeder Sementara No. '.$i,
                    ],
                );
            } else {
                $order->delivery()->delete();
            }

            app(SyncOrderShippingExpenseAction::class)->handle($order, $user->id);
        }
    }

    private function seedPurchaseData(User $user, ItemUnit $itemUnit): void
    {
        $period = now()->format('Ym');

        for ($i = 1; $i <= 10; $i++) {
            $quantity = 5 + (($i + 1) % 4) * 3;
            $price = 12000 + (($i + 2) % 5) * 1500;
            $total = (float) ($quantity * $price);
            $movementDate = CarbonImmutable::now()
                ->startOfMonth()
                ->addDays(min($i * 2, 27))
                ->setTime(8 + ($i % 8), 15);

            $movement = StockMovement::query()->firstOrNew([
                'item_id' => $itemUnit->id,
                'type' => 'in',
                'description' => "TEMP-PURCHASE-{$period}-".str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);

            $wasRecentlyCreated = ! $movement->exists;

            $movement->fill([
                'order_id' => null,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'price_at_the_time' => (float) $price,
                'total' => $total,
            ]);
            $movement->created_at = $movementDate;
            $movement->updated_at = $movementDate;
            $movement->save();

            if ($wasRecentlyCreated) {
                $itemUnit->increment('stock', $quantity);
            }
        }
    }

    private function seedManualReportEntries(User $user): void
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $period = now()->format('Ym');

        $entries = [
            [
                'description' => "TEMP-SUPPLY-INCOME-{$period}-01",
                'category' => 'supply_income',
                'occurred_on' => $start->addDays(2)->toDateString(),
                'amount_idr' => 750000,
            ],
            [
                'description' => "TEMP-SUPPLY-INCOME-{$period}-02",
                'category' => 'supply_income',
                'occurred_on' => $start->addDays(15)->toDateString(),
                'amount_idr' => 560000,
            ],
            [
                'description' => "TEMP-PURCHASE-SUPPLY-{$period}-01",
                'category' => 'purchase_supply',
                'occurred_on' => $start->addDays(4)->toDateString(),
                'amount_idr' => 0,
                'amount_rmb' => 120,
                'exchange_rate' => 2200,
                'freight_idr' => 30000,
                'tracking_number' => 'TEMP-RESI-001',
                'code' => 'TMP-SUP-01',
                'estimated_arrived_on' => $start->addDays(12)->toDateString(),
            ],
            [
                'description' => "TEMP-PURCHASE-SUPPLY-{$period}-02",
                'category' => 'purchase_supply',
                'occurred_on' => $start->addDays(18)->toDateString(),
                'amount_idr' => 0,
                'amount_rmb' => 90,
                'exchange_rate' => 2250,
                'freight_idr' => 25000,
                'tracking_number' => 'TEMP-RESI-002',
                'code' => 'TMP-SUP-02',
                'estimated_arrived_on' => $start->addDays(24)->toDateString(),
            ],
            [
                'description' => "TEMP-STORE-EXPENSE-{$period}-01",
                'category' => 'store_expense',
                'occurred_on' => $start->addDays(6)->toDateString(),
                'amount_idr' => 350000,
            ],
            [
                'description' => "TEMP-STORE-EXPENSE-{$period}-02",
                'category' => 'store_expense',
                'occurred_on' => $start->addDays(20)->toDateString(),
                'amount_idr' => 280000,
            ],
            [
                'description' => "TEMP-RAW-MATERIAL-{$period}-01",
                'category' => 'raw_material_expense',
                'occurred_on' => $start->addDays(9)->toDateString(),
                'amount_idr' => 420000,
            ],
            [
                'description' => "TEMP-RAW-MATERIAL-{$period}-02",
                'category' => 'raw_material_expense',
                'occurred_on' => $start->addDays(22)->toDateString(),
                'amount_idr' => 210000,
            ],
            [
                'description' => "TEMP-REFUND-{$period}-01",
                'category' => 'refund',
                'occurred_on' => $start->addDays(25)->toDateString(),
                'amount_idr' => 0,
                'amount_rmb' => 25,
                'exchange_rate' => 2200,
            ],
            [
                'description' => "TEMP-ADJUSTMENT-{$period}-01",
                'category' => 'profit_adjustment',
                'occurred_on' => $start->addDays(27)->toDateString(),
                'amount_idr' => -50000,
                'notes' => 'Penyesuaian contoh sementara',
            ],
        ];

        foreach ($entries as $entry) {
            ReportEntry::query()->updateOrCreate(
                [
                    'category' => (string) $entry['category'],
                    'description' => (string) $entry['description'],
                ],
                [
                    'user_id' => $user->id,
                    'order_id' => null,
                    'occurred_on' => (string) $entry['occurred_on'],
                    'amount_idr' => (float) ($entry['amount_idr'] ?? 0),
                    'amount_rmb' => (float) ($entry['amount_rmb'] ?? 0),
                    'exchange_rate' => (float) ($entry['exchange_rate'] ?? 0),
                    'freight_idr' => (float) ($entry['freight_idr'] ?? 0),
                    'tracking_number' => $entry['tracking_number'] ?? null,
                    'code' => $entry['code'] ?? null,
                    'estimated_arrived_on' => $entry['estimated_arrived_on'] ?? null,
                    'notes' => $entry['notes'] ?? null,
                ],
            );
        }
    }
}
