<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InitialSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Super Admin
        $user = User::updateOrCreate(
            ['email' => 'super@bees.id'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        if (!$user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        // 2. Bouquet Categories
        $categories = [
            ['name' => 'Fresh Flowers'],
            ['name' => 'Money Bouquet'],
            ['name' => 'Dried Flowers'],
            ['name' => 'Artificial Flowers'],
            ['name' => 'Fruit Basket'],
        ];

        foreach ($categories as $cat) {
            $category = BouquetCategory::updateOrCreate(
                ['name' => $cat['name']], 
                ['slug' => Str::slug($cat['name'])]
            );

            // 3. Bouquet Types
            $types = [
                ['name' => 'Hand Bouquet', 'description' => 'Buket tangan standard'],
                ['name' => 'Bloom Box', 'description' => 'Bunga dalam kotak'],
                ['name' => 'Standing Flower', 'description' => 'Bunga papan/standing'],
            ];

            foreach ($types as $t) {
                $typeName = $category->name . ' - ' . $t['name'];
                $type = BouquetType::updateOrCreate(
                    ['name' => $typeName],
                    ['category_id' => $category->id, 'description' => $t['description'], 'is_custom' => false]
                );

                // 4. Bouquet Units (Generate many for pagination)
                for ($i = 1; $i <= 8; $i++) {
                    BouquetUnit::updateOrCreate(
                        ['serial_number' => 'BQT-' . $category->id . $type->id . $i],
                        [
                            'type_id' => $type->id,
                            'name' => $type->name . ' Design ' . $i,
                            'price' => 150000 + (rand(1, 10) * 50000),
                            'description' => 'Beautiful design variation ' . $i,
                        ]
                    );
                }
            }
        }

        // 5. Inventory Categories
        $itemCats = ['Wrappings', 'Ribbons', 'Tools', 'Accessories', 'Flowers (Raw)'];
        foreach ($itemCats as $name) {
            $itemCat = ItemCategory::updateOrCreate(['name' => $name], ['slug' => Str::slug($name)]);

            // 6. Inventory Units
            for ($i = 1; $i <= 10; $i++) {
                $item = ItemUnit::updateOrCreate(
                    ['serial_number' => 'INV-' . $itemCat->id . $i],
                    [
                        'category_id' => $itemCat->id,
                        'name' => $name . ' Item ' . $i,
                        'price' => rand(10000, 50000),
                        'individual' => 'Pcs',
                        'description' => 'Description for ' . $name . ' ' . $i,
                        'stock' => rand(50, 200),
                    ]
                );

                // 7. Stock Movements
                StockMovement::create([
                    'item_id' => $item->id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'quantity' => $item->stock,
                    'price_at_the_time' => $item->price,
                    'total' => $item->stock * $item->price,
                    'description' => 'Initial stock seed',
                ]);
            }
        }

        // 8. Customers
        for ($i = 1; $i <= 30; $i++) {
            Customer::updateOrCreate(
                ['phone_number' => '0812' . str_pad((string)$i, 8, '0', STR_PAD_LEFT)],
                ['name' => 'Customer ' . $i]
            );
        }

        // 9. Orders (Spread across last 60 days)
        $customerIds = Customer::pluck('id')->toArray();
        $statuses = Order::ORDER_STATUSES;

        for ($i = 0; $i < 100; $i++) {
            $date = now()->subDays(rand(0, 60))->subHours(rand(0, 23));
            Order::create([
                'user_id' => $user->id,
                'customer_id' => $customerIds[array_rand($customerIds)],
                'total' => rand(200000, 1500000),
                'shipping_date' => $date->toDateString(),
                'shipping_time' => '10:00:00',
                'shipping_type' => rand(0, 1) ? 'delivery' : 'pickup',
                'payment_status' => rand(0, 1) ? 'paid' : 'unpaid',
                'order_status' => $statuses[array_rand($statuses)],
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
