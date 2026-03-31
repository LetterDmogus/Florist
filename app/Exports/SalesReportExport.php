<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly array $salesSummary,
        private readonly array $salesRows,
        private readonly array $profitSummary,
        private readonly int $month,
        private readonly int $year,
    ) {}

    public function array(): array
    {
        $monthLabel = CarbonImmutable::create($this->year, $this->month, 1)->translatedFormat('F');

        $rows = [
            ['Bees Fleur'],
            ["Laporan Penjualan {$monthLabel} {$this->year}"],
            [],
            ['Summary Penjualan'],
            ['Money', (float) ($this->salesSummary['money'] ?? 0)],
            ['Fee', (float) ($this->salesSummary['fee'] ?? 0)],
            ['Gosend', (float) ($this->salesSummary['gosend'] ?? 0)],
            ['Total', (float) ($this->salesSummary['total'] ?? 0)],
            [],
            ['No', 'Date', 'Model', 'Money', 'Fee', 'Gosend', 'Total'],
        ];

        foreach ($this->salesRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['model'] ?? ''),
                (float) ($row['money'] ?? 0),
                (float) ($row['fee'] ?? 0),
                (float) ($row['gosend'] ?? 0),
                (float) ($row['total'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Ringkasan Laba'];
        $rows[] = ['Pendapatan Florist', (float) ($this->profitSummary['florist_income'] ?? 0)];
        $rows[] = ['Pendapatan Supply', (float) ($this->profitSummary['supply_income'] ?? 0)];
        $rows[] = ['Pembelian', (float) ($this->profitSummary['purchase_total'] ?? 0)];
        $rows[] = ['Biaya Toko', (float) ($this->profitSummary['store_expense_total'] ?? 0)];
        $rows[] = ['Biaya Bahan Baku', (float) ($this->profitSummary['raw_material_expense_total'] ?? 0)];
        $rows[] = ['Laba Kotor', (float) ($this->profitSummary['gross_profit'] ?? 0)];
        $rows[] = ['Penyesuaian', (float) ($this->profitSummary['adjustment_total'] ?? 0)];
        $rows[] = ['Laba Bersih', (float) ($this->profitSummary['net_profit'] ?? 0)];

        return $rows;
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
