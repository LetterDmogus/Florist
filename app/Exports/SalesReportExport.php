<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    private int $tableHeaderRow = 10;

    private int $dataStartRow = 11;

    private int $dataEndRow = 11;

    private int $profitTitleRow = 0;

    private int $netProfitRow = 0;

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
            ['BEES FLEUR FLORIST'],
            ["LAPORAN PENJUALAN - {$monthLabel} {$this->year}"],
            [],
            ['','SUMMARY PENJUALAN'],
            ['','Money', (float) ($this->salesSummary['money'] ?? 0)],
            ['','Fee', (float) ($this->salesSummary['fee'] ?? 0)],
            ['','Gosend', (float) ($this->salesSummary['gosend'] ?? 0)],
            ['','TOTAL PENJUALAN', (float) ($this->salesSummary['total'] ?? 0)],
            [],
            ['No', 'Tanggal', 'Model Bouquet / Item', 'Money', 'Fee', 'Gosend', 'Total'],
        ];

        $currentRow = count($rows);

        if ($this->salesRows === []) {
            $rows[] = ['-', '-', 'Tidak ada data penjualan pada periode ini', 0, 0, 0, 0];
            $currentRow++;
        } else {
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
                $currentRow++;
            }
        }

        $this->dataEndRow = $currentRow-=2;

        $rows[] = [];
        $currentRow++;

        $this->profitTitleRow = $currentRow -=9;
        $rows[] = ['','RINGKASAN LABA'];
        $currentRow++;

        $rows[] = ['','Pendapatan Florist (Fee)', (float) ($this->profitSummary['florist_income'] ?? 0)];
        $rows[] = ['','Pendapatan Supply', (float) ($this->profitSummary['supply_income'] ?? 0)];
        $rows[] = ['','Pembelian Stok (Cost)', (float) ($this->profitSummary['purchase_total'] ?? 0)];
        $rows[] = ['','Biaya Operasional Toko', (float) ($this->profitSummary['store_expense_total'] ?? 0)];
        $rows[] = ['','Biaya Bahan Baku', (float) ($this->profitSummary['raw_material_expense_total'] ?? 0)];
        $rows[] = ['','LABA KOTOR', (float) ($this->profitSummary['gross_profit'] ?? 0)];
        $rows[] = ['','Penyesuaian (Adjustment)', (float) ($this->profitSummary['adjustment_total'] ?? 0)];
        $rows[] = ['','LABA BERSIH (NET)', (float) ($this->profitSummary['net_profit'] ?? 0)];

        $this->netProfitRow = $currentRow + 8;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');

        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A1:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:G2')->getFont()->setBold(true)->setSize(12);

        $sheet->getStyle('A3')->getFont()->setBold(true)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(11);

        $sheet->getStyle("A8:G8")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DB2777']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $tableRange = "A{$this->tableHeaderRow}:G{$this->dataEndRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->getColor()->setRGB('F472B6');
        $sheet->getStyle($tableRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        for ($i = $this->dataStartRow; $i <= $this->dataEndRow; $i++) {
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$i}:G{$i}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FFF1F7');
            }
        }

        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("C{$this->tableHeaderRow}:C{$highestRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A{$this->tableHeaderRow}:B{$this->dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('A11');

        if ($this->profitTitleRow > 0) {
            $sheet->getStyle("B{$this->profitTitleRow}")
                ->getFont()
                ->setBold(true)
                ->setColor(new Color('9D174D'));
        }

        if ($this->netProfitRow > 0) {
            $sheet->getStyle("A{$this->netProfitRow}:G{$this->netProfitRow}")
                ->getFont()
                ->setBold(true)
                ->setSize(12);
            $sheet->getStyle("A{$this->netProfitRow}:G{$this->netProfitRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('FCE7F3');
        }

        return [];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 13,
            'C' => 44,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 16,
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
