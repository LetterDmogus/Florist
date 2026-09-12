<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BouquetType;
use App\Models\BouquetUnit;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BouquetTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    public function array(): array
    {
        // Berikan contoh data dari tipe bouquet dan unit yang ada
        $sampleTypes = BouquetType::where('is_custom', false)->limit(2)->get();
        $rows = [];

        if ($sampleTypes->isNotEmpty()) {
            foreach ($sampleTypes as $index => $type) {
                $rows[] = [
                    'BQT-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), // Kode / SKU
                    $type->name,                                                    // Tipe Bouquet
                    'Bouquet ' . $type->name . ' Cantik ' . ($index + 1),          // Nama Bouquet
                    150000 + ($index * 50000),                                     // Harga Jual IDR
                    'Bunga mawar segar dan aksesoris pita satin',                   // Deskripsi
                    'AKTIF',                                                       // Status (AKTIF / NONAKTIF)
                ];
            }
        } else {
            $rows[] = [
                'BQT-001',
                'Standing Flower',
                'Bouquet Standing Mawar Merah',
                250000,
                'Rangkaian bunga standing acara spesial',
                'AKTIF',
            ];
            $rows[] = [
                'BQT-002',
                'Hand Bouquet',
                'Hand Bouquet Lily Putih',
                175000,
                'Rangkaian bunga tangan wisuda',
                'AKTIF',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'SKU / Kode Bouquet (Wajib)',
            'Tipe Bouquet (Wajib - Nama Tipe)',
            'Nama Bouquet (Wajib)',
            'Harga Jual IDR (Wajib)',
            'Deskripsi (Opsional)',
            'Status (AKTIF / NONAKTIF)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E11D48'], // Rose 600
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@',
            'B' => '@',
            'C' => '@',
            'D' => '#,##0',
            'E' => '@',
            'F' => '@',
        ];
    }
}
