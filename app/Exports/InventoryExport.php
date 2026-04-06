<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InventoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly \Illuminate\Support\Collection $items
    ) {}

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Name',
            'Harga Jual',
            'Sheets',
            'Size',
            'Sisa',
        ];
    }

    /**
     * @param \App\Models\ItemUnit $item
     */
    public function map($item): array
    {
        // Extract size from description if it follows "Size: {value}" format
        $size = '';
        if ($item->description && str_starts_with($item->description, 'Size: ')) {
            $size = substr($item->description, 6);
        }

        return [
            $item->serial_number,
            $item->name,
            $item->price,
            $item->individual,
            $size,
            $item->stock,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DB2777'], // pink-600
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
