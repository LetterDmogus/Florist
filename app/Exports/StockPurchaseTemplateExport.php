<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ItemUnit;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockPurchaseTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    public function array(): array
    {
        // Berikan beberapa baris contoh berdasarkan item yang ada di database agar user tahu formatnya
        $sampleItems = ItemUnit::query()->limit(3)->get(['serial_number', 'name', 'price']);

        $rows = [];
        if ($sampleItems->isNotEmpty()) {
            foreach ($sampleItems as $item) {
                $rows[] = [
                    $item->serial_number,
                    $item->name,
                    10,             // Qty Pembelian (contoh)
                    (float) $item->price, // Harga Beli Satuan (contoh)
                    'Pembelian restok barang', // Keterangan
                    now()->format('Y-m-d'), // Tanggal Pembelian
                    0,              // Biaya Ongkir (Opsional)
                    '',             // No Resi (Opsional)
                    '',             // Kode (Opsional)
                    '',             // Estimasi Tiba (Opsional)
                    '',             // Harga RMB Satuan (Opsional)
                ];
            }
        } else {
            $rows[] = [
                'SUP-001',
                'Contoh Nama Item',
                10,
                15000,
                'Pembelian restok barang',
                now()->format('Y-m-d'),
                0,
                '',
                '',
                '',
                '',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Kode Item (Wajib)',
            'Nama Item (Referensi/Opsional)',
            'Jumlah / Qty (Wajib)',
            'Harga Beli Satuan IDR (Wajib)',
            'Keterangan (Opsional)',
            'Tanggal Pembelian (YYYY-MM-DD)',
            'Ongkir IDR (Opsional)',
            'No Resi (Opsional)',
            'Kode Pengiriman (Opsional)',
            'Estimasi Tiba (YYYY-MM-DD)',
            'Harga Satuan RMB (Opsional)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'], // Emerald 600
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
            'D' => '#,##0',
            'F' => '@',
            'G' => '#,##0',
            'J' => '@',
        ];
    }
}
