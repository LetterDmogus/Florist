<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use Illuminate\Database\Seeder;

class BouquetSeeder extends Seeder
{
    public function run(): void
    {
        $catalogs = [
            [
                'name' => 'Bunga Segar (Fresh Flowers)',
                'slug' => 'fresh-flowers',
                'types' => [
                    [
                        'name' => 'Rose Bouquet',
                        'description' => 'Buket mawar segar aneka warna elegan',
                        'is_custom' => false,
                        'units' => [
                            [
                                'serial_number' => 'BQT-FRS-RS-001',
                                'name' => 'Red Rose Classic (10 Tangkai)',
                                'description' => 'Buket 10 tangkai mawar merah segar dengan wrapping premium hitam-emas.',
                                'price' => 150000,
                            ],
                            [
                                'serial_number' => 'BQT-FRS-RS-002',
                                'name' => 'Pink Romance Rose (20 Tangkai)',
                                'description' => 'Buket 20 tangkai mawar merah muda pastel dengan sentuhan baby breath.',
                                'price' => 275000,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Sunflower Bouquet',
                        'description' => 'Buket bunga matahari cerah untuk wisuda dan perayaan',
                        'is_custom' => false,
                        'units' => [
                            [
                                'serial_number' => 'BQT-FRS-SF-001',
                                'name' => 'Sunlight Cheer (3 Bunga Matahari)',
                                'description' => 'Buket 3 kuntum bunga matahari segar kombinasi foliage hijau.',
                                'price' => 125000,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Money Bouquet',
                'slug' => 'money-bouquet',
                'types' => [
                    [
                        'name' => 'Money Bouquet Lembaran',
                        'description' => 'Buket rangkaian uang kertas asli dengan dekorasi bunga',
                        'is_custom' => true,
                        'units' => [
                            [
                                'serial_number' => 'BQT-MNY-001',
                                'name' => 'Money Bouquet 10 Lembar (Jasa & Rangkaian)',
                                'description' => 'Rangkaian 10 slot lembaran uang kertas (di luar nominal uang isi).',
                                'price' => 85000,
                            ],
                            [
                                'serial_number' => 'BQT-MNY-002',
                                'name' => 'Money Bouquet 20 Lembar Deluxe',
                                'description' => 'Rangkaian 20 slot lembaran uang kertas dengan hiasan bunga kering & pita satin.',
                                'price' => 150000,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Snack & Gift Bouquet',
                'slug' => 'snack-gift',
                'types' => [
                    [
                        'name' => 'Snack Bouquet',
                        'description' => 'Buket aneka jajanan dan cokelat',
                        'is_custom' => false,
                        'units' => [
                            [
                                'serial_number' => 'BQT-SNK-001',
                                'name' => 'Choco Sweet Bouquet',
                                'description' => 'Kombinasi cokelat SilverQueen, KitKat, dan Pocky dengan wrapping senada.',
                                'price' => 95000,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Graduation Doll Bouquet',
                        'description' => 'Buket boneka wisuda dan buket bunga artifisial',
                        'is_custom' => false,
                        'units' => [
                            [
                                'serial_number' => 'BQT-GRD-001',
                                'name' => 'Wisuda Teddy Bear Pink Pastel',
                                'description' => 'Boneka beruang toga wisuda dengan rangkaian bunga mawar sabun/artifisial.',
                                'price' => 110000,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Bunga Kering & Artifisial (Dried & Artificial)',
                'slug' => 'dried-artificial',
                'types' => [
                    [
                        'name' => 'Rustic Dried Flower',
                        'description' => 'Buket bunga kering tahan lama bergaya estetik rustic',
                        'is_custom' => false,
                        'units' => [
                            [
                                'serial_number' => 'BQT-DRD-001',
                                'name' => 'Rustic Lavender & Edelweiss Petite',
                                'description' => 'Bunga kering lavender & edelweiss budidaya dibungkus kertas cokelat kraft.',
                                'price' => 75000,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($catalogs as $catData) {
            $category = BouquetCategory::query()->updateOrCreate(
                ['slug' => $catData['slug']],
                ['name' => $catData['name']]
            );

            foreach ($catData['types'] as $typeData) {
                $type = BouquetType::query()->updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $typeData['name'],
                    ],
                    [
                        'description' => $typeData['description'],
                        'is_custom' => $typeData['is_custom'],
                    ]
                );

                foreach ($typeData['units'] as $unitData) {
                    BouquetUnit::query()->updateOrCreate(
                        ['serial_number' => $unitData['serial_number']],
                        [
                            'type_id' => $type->id,
                            'name' => $unitData['name'],
                            'description' => $unitData['description'],
                            'price' => $unitData['price'],
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
