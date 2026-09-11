<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemCategory;
use App\Models\ItemUnit;
use Illuminate\Database\Seeder;

class ItemUnitSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Kertas Wrapping (Paper Wraps)',
                'slug' => 'kertas-wrapping',
                'items' => [
                    [
                        'name' => 'Korean Cellophane Paper Matte Pink',
                        'serial_number' => 'WRP-MAT-PNK',
                        'price' => 7500,
                        'stock' => 120,
                        'description' => 'Kertas wrapping korea anti-air warna merah muda matte.',
                    ],
                    [
                        'name' => 'Korean Cellophane Paper Gold Line Black',
                        'serial_number' => 'WRP-GLD-BLK',
                        'price' => 8500,
                        'stock' => 85,
                        'description' => 'Kertas wrapping hitam elegan dengan lis pinggiran emas mewah.',
                    ],
                    [
                        'name' => 'Kraft Paper Roll Rustic 50m',
                        'serial_number' => 'WRP-KRF-50M',
                        'price' => 45000,
                        'stock' => 25,
                        'description' => 'Kertas samson / kraft cokelat gaya vintage rustic rol besar.',
                    ],
                ],
            ],
            [
                'name' => 'Pita & Aksesoris (Ribbons & Acc)',
                'slug' => 'pita-aksesoris',
                'items' => [
                    [
                        'name' => 'Pita Satin Silk 2.5cm Champagne Gold (Roll)',
                        'serial_number' => 'RBN-STN-GLD',
                        'price' => 22000,
                        'stock' => 60,
                        'description' => 'Pita satin kilau halus lebar 2.5cm panjang 25 yard warna sampanye.',
                    ],
                    [
                        'name' => 'Pita Satin Silk 2.5cm Burgundy Wine (Roll)',
                        'serial_number' => 'RBN-STN-BUR',
                        'price' => 22000,
                        'stock' => 45,
                        'description' => 'Pita satin warna merah marun wine gelap elegan.',
                    ],
                    [
                        'name' => 'Lampu LED String Fairy Warm White (1m)',
                        'serial_number' => 'ACC-LED-WHT',
                        'price' => 6000,
                        'stock' => 200,
                        'description' => 'Lampu kawat tembaga baterai untuk variasi buket menyala.',
                    ],
                    [
                        'name' => 'Boneka Teddy Bear Wisuda Mini 12cm',
                        'serial_number' => 'ACC-TDY-WSD',
                        'price' => 18000,
                        'stock' => 40,
                        'description' => 'Boneka beruang berkerah toga untuk sisipan buket wisuda.',
                    ],
                ],
            ],
            [
                'name' => 'Tangkai Bunga Satuan (Cut Flowers)',
                'slug' => 'tangkai-bunga',
                'items' => [
                    [
                        'name' => 'Mawar Merah Semi Holland Grade A (Tangkai)',
                        'serial_number' => 'FLW-MWR-RED',
                        'price' => 12000,
                        'stock' => 150,
                        'description' => 'Mawar potong merah segar semi holland kuntum mekar sempurna.',
                    ],
                    [
                        'name' => 'Mawar Putih Avalanche (Tangkai)',
                        'serial_number' => 'FLW-MWR-WHT',
                        'price' => 12500,
                        'stock' => 100,
                        'description' => 'Mawar putih segar import grade kualitas terbaik.',
                    ],
                    [
                        'name' => 'Baby Breath Impor Million Star (Ikat 100gr)',
                        'serial_number' => 'FLW-BBR-100',
                        'price' => 48000,
                        'stock' => 30,
                        'description' => 'Bunga baby breath kering/segar putih sebagai pelengkap filler buket.',
                    ],
                ],
            ],
            [
                'name' => 'Vas & Keranjang (Vases & Baskets)',
                'slug' => 'vas-keranjang',
                'items' => [
                    [
                        'name' => 'Vas Keramik Silinder Putih Scandinavian',
                        'serial_number' => 'VAS-KRM-SCN',
                        'price' => 65000,
                        'stock' => 18,
                        'description' => 'Vas bunga keramik estetik minimalis untuk meja ruang tamu atau kafe.',
                    ],
                    [
                        'name' => 'Keranjang Anyaman Rotan Gagang Oval',
                        'serial_number' => 'BSK-RTN-OVL',
                        'price' => 38000,
                        'stock' => 22,
                        'description' => 'Keranjang rotan natural untuk parcel bunga meja atau buah.',
                    ],
                ],
            ],
        ];

        foreach ($categories as $catData) {
            $category = ItemCategory::query()->firstOrCreate(
                ['slug' => $catData['slug']],
                ['name' => $catData['name']]
            );

            foreach ($catData['items'] as $itemData) {
                ItemUnit::query()->updateOrCreate(
                    ['serial_number' => $itemData['serial_number']],
                    [
                        'category_id' => $category->id,
                        'name' => $itemData['name'],
                        'price' => $itemData['price'],
                        'stock' => $itemData['stock'],
                        'description' => $itemData['description'],
                        'individual' => true,
                    ]
                );
            }
        }
    }
}