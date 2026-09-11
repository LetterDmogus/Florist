<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first() ?? User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super@bees.id',
            'password' => bcrypt('password'),
        ]);

        $customersData = [
            ['name' => 'Aisyah Putri', 'phone' => '081234567891'],
            ['name' => 'Budi Santoso', 'phone' => '081234567892'],
            ['name' => 'Citra Lestari', 'phone' => '081234567893'],
            ['name' => 'Dimas Pratama', 'phone' => '081234567894'],
            ['name' => 'Elena Wijaya', 'phone' => '081234567895'],
            ['name' => 'Fajar Nugraha', 'phone' => '081298765431'],
            ['name' => 'Gita Gutawa', 'phone' => '081298765432'],
            ['name' => 'Hendra Setiawan', 'phone' => '081298765433'],
        ];

        $customerModels = [];
        foreach ($customersData as $c) {
            $customerModels[] = Customer::query()->firstOrCreate(
                ['phone_number' => $c['phone']],
                ['name' => $c['name'], 'aliases' => []]
            );
        }

        // Ensure catalogs exist
        $bouquetUnits = BouquetUnit::query()->where('is_active', true)->get();
        if ($bouquetUnits->isEmpty()) {
            $this->call(BouquetSeeder::class);
            $bouquetUnits = BouquetUnit::query()->where('is_active', true)->get();
        }

        $inventoryUnits = ItemUnit::query()->get();
        if ($inventoryUnits->isEmpty()) {
            $this->call(ItemUnitSeeder::class);
            $inventoryUnits = ItemUnit::query()->get();
        }

        // 1. COMPLEX BOUQUET ORDERS (Various statuses, custom greeting, money bouquet, delivery & pickup)
        $bouquetOrders = [
            [
                'request_id' => 'SEED-BQT-001-PENDING',
                'order_status' => 'pending',
                'customer' => $customerModels[0],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '14:00',
                'shipping_fee' => 0,
                'payment_status' => 'dp',
                'down_payment' => 100000,
                'description' => 'Buket wisuda untuk adik. Request pita warna ungu lavender dan kertas matte lilac.',
                'items' => [
                    [
                        'bouquet' => $bouquetUnits[0] ?? null,
                        'quantity' => 1,
                        'money_bouquet' => 500000,
                        'greeting_card' => 'Happy Graduation Aisyah! Semoga ilmunya berkah dan sukses kedepannya!',
                        'sender_name' => 'Kakak Tersayang',
                    ],
                ],
            ],
            [
                'request_id' => 'SEED-BQT-002-READY',
                'order_status' => 'ready',
                'customer' => $customerModels[1],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '16:30',
                'shipping_fee' => 0,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Siap di-pickup sore jam 16:30. Buket sudah di ruang display florist.',
                'items' => [
                    [
                        'bouquet' => $bouquetUnits[1] ?? $bouquetUnits[0],
                        'quantity' => 1,
                        'money_bouquet' => null,
                        'greeting_card' => 'Selamat Ulang Tahun pernikahan yang ke-5 sayang!',
                        'sender_name' => 'Budi Santoso',
                    ],
                    [
                        'bouquet' => $bouquetUnits[2] ?? $bouquetUnits[0],
                        'quantity' => 1,
                        'money_bouquet' => null,
                        'greeting_card' => 'Thank you for being the best mom ever!',
                        'sender_name' => 'Budi & Keluarga',
                    ],
                ],
            ],
            [
                'request_id' => 'SEED-BQT-003-DELIVERY',
                'order_status' => 'on_delivery',
                'customer' => $customerModels[2],
                'shipping_type' => 'delivery',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '13:00',
                'shipping_fee' => 25000,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Pengantaran prioritas ke kantor klien. Kurir motor wajib hati-hati jangan sampai bunga layu/rusak.',
                'delivery' => [
                    'recipient_name' => 'Ibu Rahmawati (Divisi Marketing)',
                    'recipient_phone' => '085712345678',
                    'full_address' => 'Gedung Wisma Menara Lt. 8, Jl. Jend. Sudirman Kav. 21, Jakarta Selatan',
                ],
                'items' => [
                    [
                        'bouquet' => $bouquetUnits[0],
                        'quantity' => 1,
                        'money_bouquet' => null,
                        'greeting_card' => 'Congratulation on your big promotion Bu Rahma!',
                        'sender_name' => 'Citra Lestari & Rekan Kerja',
                    ],
                ],
            ],
            [
                'request_id' => 'SEED-BQT-004-COMPLETED',
                'order_status' => 'completed',
                'customer' => $customerModels[3],
                'shipping_type' => 'delivery',
                'shipping_date' => Carbon::yesterday()->toDateString(),
                'shipping_time' => '10:00',
                'shipping_fee' => 20000,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Pesanan telah diterima customer kemarin siang, foto bukti serah terima sudah di-upload.',
                'delivery' => [
                    'recipient_name' => 'Dimas Pratama',
                    'recipient_phone' => '081234567894',
                    'full_address' => 'Apartemen Sudirman Tower A No. 1205, Jakarta Pusat',
                ],
                'items' => [
                    [
                        'bouquet' => $bouquetUnits[1] ?? $bouquetUnits[0],
                        'quantity' => 1,
                        'money_bouquet' => 200000,
                        'greeting_card' => 'Happy Sweet Seventeen Putriku tercinta!',
                        'sender_name' => 'Dimas & Istri',
                    ],
                ],
            ],
            [
                'request_id' => 'SEED-BQT-005-CANCELED',
                'order_status' => 'canceled',
                'customer' => $customerModels[4],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->subDays(2)->toDateString(),
                'shipping_time' => '15:00',
                'shipping_fee' => 0,
                'payment_status' => 'unpaid',
                'down_payment' => null,
                'description' => 'Customer konfirmasi pembatalan karena acara pesta dipindah ke luar kota.',
                'items' => [
                    [
                        'bouquet' => $bouquetUnits[0],
                        'quantity' => 1,
                        'money_bouquet' => null,
                        'greeting_card' => 'Get well soon ya!',
                        'sender_name' => 'Elena Wijaya',
                    ],
                ],
            ],
        ];

        foreach ($bouquetOrders as $orderData) {
            $itemsTotal = 0;
            $detailList = [];

            foreach ($orderData['items'] as $item) {
                if (! $item['bouquet']) continue;
                $sub = (float) $item['bouquet']->price * (int) $item['quantity'];
                if (! empty($item['money_bouquet'])) {
                    $sub += (float) $item['money_bouquet'];
                }
                $itemsTotal += $sub;
                $detailList[] = [
                    'item_type' => 'bouquet',
                    'bouquet_unit_id' => $item['bouquet']->id,
                    'quantity' => $item['quantity'],
                    'subtotal' => $sub,
                    'money_bouquet' => $item['money_bouquet'],
                    'greeting_card' => $item['greeting_card'],
                    'sender_name' => $item['sender_name'],
                ];
            }

            $total = $itemsTotal + (float) $orderData['shipping_fee'];

            $order = Order::query()->updateOrCreate(
                ['request_id' => $orderData['request_id']],
                [
                    'user_id' => $user->id,
                    'customer_id' => $orderData['customer']->id,
                    'order_type' => 'bouquet',
                    'total' => $total,
                    'shipping_date' => $orderData['shipping_date'],
                    'shipping_time' => $orderData['shipping_time'],
                    'shipping_type' => $orderData['shipping_type'],
                    'shipping_fee' => $orderData['shipping_fee'],
                    'down_payment' => $orderData['down_payment'],
                    'payment_status' => $orderData['payment_status'],
                    'order_status' => $orderData['order_status'],
                    'description' => $orderData['description'],
                ]
            );

            $order->orderDetails()->delete();
            foreach ($detailList as $detail) {
                $detail['order_id'] = $order->id;
                OrderDetail::query()->create($detail);
            }

            if ($orderData['shipping_type'] === 'delivery' && isset($orderData['delivery'])) {
                $order->delivery()->delete();
                Delivery::query()->create([
                    'order_id' => $order->id,
                    'recipient_name' => $orderData['delivery']['recipient_name'],
                    'recipient_phone' => $orderData['delivery']['recipient_phone'],
                    'full_address' => $orderData['delivery']['full_address'],
                ]);
            }
        }

        // 2. COMPLEX INVENTORY / GUDANG ORDERS (Retail items, materials, bulk cut flowers, ribbons)
        $inventoryOrders = [
            [
                'request_id' => 'SEED-INV-001-PENDING',
                'order_status' => 'pending',
                'customer' => $customerModels[5],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '11:30',
                'shipping_fee' => 0,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Pembelian bahan wrapping & pita florist studio oleh mitra dekorasi.',
                'items' => [
                    ['inv' => $inventoryUnits[0] ?? null, 'qty' => 5], // Kertas Pink
                    ['inv' => $inventoryUnits[1] ?? null, 'qty' => 3], // Kertas Hitam Gold
                    ['inv' => $inventoryUnits[3] ?? null, 'qty' => 2], // Pita Gold Roll
                ],
            ],
            [
                'request_id' => 'SEED-INV-002-READY',
                'order_status' => 'ready',
                'customer' => $customerModels[6],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '15:00',
                'shipping_fee' => 0,
                'payment_status' => 'dp',
                'down_payment' => 150000,
                'description' => 'Barang sudah dipacking kardus, menunggu pengambilan mobil pickup pembeli.',
                'items' => [
                    ['inv' => $inventoryUnits[6] ?? $inventoryUnits[0], 'qty' => 20], // Mawar Merah Tangkai
                    ['inv' => $inventoryUnits[7] ?? $inventoryUnits[1], 'qty' => 10], // Mawar Putih Tangkai
                    ['inv' => $inventoryUnits[8] ?? $inventoryUnits[2], 'qty' => 2],  // Baby breath ikat
                ],
            ],
            [
                'request_id' => 'SEED-INV-003-DELIVERY',
                'order_status' => 'on_delivery',
                'customer' => $customerModels[7],
                'shipping_type' => 'delivery',
                'shipping_date' => Carbon::today()->toDateString(),
                'shipping_time' => '14:00',
                'shipping_fee' => 35000,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Pengiriman vas keramik & lampu LED hias via kurir ekspres.',
                'delivery' => [
                    'recipient_name' => 'Hendra Setiawan (Toko Bunga Sejahtera)',
                    'recipient_phone' => '081298765433',
                    'full_address' => 'Ruko Plaza Harmony Blok C-12, Gading Serpong, Tangerang',
                ],
                'items' => [
                    ['inv' => $inventoryUnits[9] ?? $inventoryUnits[0], 'qty' => 4],  // Vas Keramik
                    ['inv' => $inventoryUnits[10] ?? $inventoryUnits[1], 'qty' => 2], // Keranjang Rotan
                    ['inv' => $inventoryUnits[5] ?? $inventoryUnits[2], 'qty' => 10], // Lampu LED fairy
                ],
            ],
            [
                'request_id' => 'SEED-INV-004-COMPLETED',
                'order_status' => 'completed',
                'customer' => $customerModels[0],
                'shipping_type' => 'pickup',
                'shipping_date' => Carbon::today()->subDays(1)->toDateString(),
                'shipping_time' => '09:00',
                'shipping_fee' => 0,
                'payment_status' => 'paid',
                'down_payment' => null,
                'description' => 'Beli eceran langsung di kasir toko lunas cash.',
                'items' => [
                    ['inv' => $inventoryUnits[2] ?? $inventoryUnits[0], 'qty' => 1], // Kraft paper roll
                    ['inv' => $inventoryUnits[4] ?? $inventoryUnits[1], 'qty' => 2], // Pita burgundy
                ],
            ],
        ];

        foreach ($inventoryOrders as $orderData) {
            $itemsTotal = 0;
            $detailList = [];

            foreach ($orderData['items'] as $item) {
                if (! $item['inv']) continue;
                $sub = (float) $item['inv']->price * (int) $item['qty'];
                $itemsTotal += $sub;
                $detailList[] = [
                    'item_type' => 'inventory_item',
                    'inventory_item_id' => $item['inv']->id,
                    'quantity' => $item['qty'],
                    'subtotal' => $sub,
                ];
            }

            $total = $itemsTotal + (float) $orderData['shipping_fee'];

            $order = Order::query()->updateOrCreate(
                ['request_id' => $orderData['request_id']],
                [
                    'user_id' => $user->id,
                    'customer_id' => $orderData['customer']->id,
                    'order_type' => 'inventory',
                    'total' => $total,
                    'shipping_date' => $orderData['shipping_date'],
                    'shipping_time' => $orderData['shipping_time'],
                    'shipping_type' => $orderData['shipping_type'],
                    'shipping_fee' => $orderData['shipping_fee'],
                    'down_payment' => $orderData['down_payment'],
                    'payment_status' => $orderData['payment_status'],
                    'order_status' => $orderData['order_status'],
                    'description' => $orderData['description'],
                ]
            );

            $order->orderDetails()->delete();
            foreach ($detailList as $detail) {
                $detail['order_id'] = $order->id;
                OrderDetail::query()->create($detail);
            }

            if ($orderData['shipping_type'] === 'delivery' && isset($orderData['delivery'])) {
                $order->delivery()->delete();
                Delivery::query()->create([
                    'order_id' => $order->id,
                    'recipient_name' => $orderData['delivery']['recipient_name'],
                    'recipient_phone' => $orderData['delivery']['recipient_phone'],
                    'full_address' => $orderData['delivery']['full_address'],
                ]);
            }
        }
    }
}
