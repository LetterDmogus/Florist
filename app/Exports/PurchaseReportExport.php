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

class PurchaseReportExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    private array $sectionHeaderRows = [];
    private array $subtotalRows = [];
    private array $zebraRows = [];
    private array $summaryRowIndices = [];
    private int $tableHeaderRow = 12;
    private int $dataStartRow = 13;
    private int $dataEndRow = 13;
    private int $totalExpenseRow = 0;
    private int $grandTotalRow = 0;

    public function __construct(
        private readonly array $purchaseSummary,
        private readonly array $purchaseRows,
        private readonly array $supplyPurchaseRows,
        private readonly array $storeExpenseRows,
        private readonly array $rawMaterialRows,
        private readonly array $shippingRows,
        private readonly array $refundRows,
        private readonly int $month,
        private readonly int $year,
    ) {}

    public function array(): array
    {
        $monthLabel = CarbonImmutable::create($this->year, $this->month, 1)->translatedFormat('F');

        $rows = [
            ['BEES FLEUR FLORIST'],
            ["LAPORAN PEMBELIAN - {$monthLabel} {$this->year}"],
            [],
            ['', 'SUMMARY PEMBELIAN', '', '', '', '', '', '', '', '', '', ''],
            ['', 'Total Pembelian Stok', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['purchase_total'] ?? 0)],
            ['', 'Total Supply Purchase', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)],
            ['', 'Total Biaya Toko', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)],
            ['', 'Total Biaya Bahan Baku', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)],
            ['', 'Total Biaya Ongkir', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['shipping_total'] ?? 0)],
            ['', 'Total Refund (IDR)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)],
            [],
            ['No', 'Tanggal', 'Kategori', 'Deskripsi/Item', 'RMB', 'Rate', 'IDR', 'Freight', 'No Resi', 'Kode', 'Est. Arrived', 'Total'],
        ];

        $this->summaryRowIndices = [3, 4, 5, 6, 7, 8, 9];
        $this->tableHeaderRow = 10;
        $this->dataStartRow = 9;

        $zebraCounter = 0;
        $fixer=2;
        $fixer2=4;

        // 1. Pembelian Stok
        if (!empty($this->purchaseRows)) {
            $rows[] = $this->makeSectionHeader('Pembelian Stok (Fisik)');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->purchaseRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Pembelian Stok');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Pembelian Stok', (float) ($this->purchaseSummary['purchase_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        // 2. Supply Purchase
        if (!empty($this->supplyPurchaseRows)) {
            $rows[] = $this->makeSectionHeader('Supply Purchase (Impor/Luar)');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->supplyPurchaseRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Supply Purchase');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Supply Purchase', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        // 3. Biaya Toko
        if (!empty($this->storeExpenseRows)) {
            $rows[] = $this->makeSectionHeader('Biaya Toko');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->storeExpenseRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Biaya Toko');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Biaya Toko', (float) ($this->purchaseSummary['store_expense_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        // 4. Biaya Bahan Baku
        if (!empty($this->rawMaterialRows)) {
            $rows[] = $this->makeSectionHeader('Biaya Bahan Baku');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->rawMaterialRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Biaya Bahan Baku');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Biaya Bahan Baku', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        // 5. Biaya Ongkir
        if (!empty($this->shippingRows)) {
            $rows[] = $this->makeSectionHeader('Biaya Ongkir');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->shippingRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Biaya Ongkir');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Biaya Ongkir', (float) ($this->purchaseSummary['shipping_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        // 6. Refund
        if (!empty($this->refundRows)) {
            $rows[] = $this->makeSectionHeader('Refund');
            $this->sectionHeaderRows[] = count($rows)-$fixer;
            $fixer++;
            
            foreach ($this->refundRows as $index => $item) {
                $rows[] = $this->mapRow($index + 1, $item, 'Refund');
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = count($rows);
            }
            $rows[] = $this->makeSubtotalRow('Total Refund', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0));
            $this->subtotalRows[] = count($rows)-$fixer+1;
            $rows[] = [];
        }

        $this->dataEndRow = count($rows)+4;

        // Final Totals
        $rows[] = ['', 'TOTAL PENGELUARAN (Semua Biaya)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['total_expense'] ?? 0)];
        $this->totalExpenseRow = count($rows);
        $rows[] = ['', 'GRAND TOTAL (Total Biaya + Stok - Refund)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['grand_total'] ?? 0)];
        $this->grandTotalRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Core Alignment & Merges
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A1:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 2. Title Typography
        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A2:L2')->getFont()->setBold(true)->setSize(12);

        // 3. Summary Table Styles
        $sheet->getStyle('B3')->getFont()->setBold(true)->getColor()->setRGB('9D174D');
        foreach ($this->summaryRowIndices as $row) {
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->getStyle("L{$row}")->getFont()->setBold(true);
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // 4. Main Table Header
        $headerRange = "A{$this->tableHeaderRow}:L{$this->tableHeaderRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DB2777']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // 5. Section Headers
        foreach ($this->sectionHeaderRows as $row) {
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '9D174D']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE7F3']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // 6. Zebra Stripes (Data Rows)
        foreach ($this->zebraRows as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF5F9');
        }

        // 7. Subtotal Rows
        foreach ($this->subtotalRows as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDF2F8');
            $sheet->getStyle("L{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
        }

        // 8. Footer Totals
        $footerRows = [$this->totalExpenseRow-5, $this->grandTotalRow-5];
        foreach ($footerRows as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE7F3');
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // 9. General Formatting & Borders
        $tableRange = "A{$this->tableHeaderRow}:L{$this->dataEndRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->getColor()->setRGB('F472B6');
        $sheet->getStyle("A{$this->dataStartRow}:A{$sheet->getHighestRow()}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$this->tableHeaderRow}:D{$sheet->getHighestRow()}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("I{$this->tableHeaderRow}:K{$sheet->getHighestRow()}")->getAlignment()->setWrapText(true);
        
        return [];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'L' => '#,##0',
            'B' => '@',
            'K' => '@',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 13,
            'C' => 20,
            'D' => 38,
            'E' => 12,
            'F' => 10,
            'G' => 14,
            'H' => 12,
            'I' => 16,
            'J' => 12,
            'K' => 14,
            'L' => 14,
        ];
    }

    public function title(): string
    {
        return 'Pembelian';
    }

    private function makeSectionHeader(string $title): array
    {
        return [$title, '', '', '', '', '', '', '', '', '', '', ''];
    }

    private function makeSubtotalRow(string $label, float $value): array
    {
        return ['', '', '', '', '', '', '', '', '', '', $label, $value];
    }

    private function mapRow(int $no, array $item, string $category): array
    {
        return [
            (int) $no,
            (string) ($item['date'] ?? ''),
            (string) $category,
            (string) ($item['item'] ?? ($item['description'] ?? '')),
            (float) ($item['rmb'] ?? 0),
            (float) ($item['rate'] ?? 0),
            (float) ($item['idr'] ?? ($item['amount'] ?? 0)),
            (float) ($item['freight'] ?? 0),
            (string) ($item['tracking_number'] ?? ''),
            (string) ($item['code'] ?? ''),
            (string) ($item['estimate_arrived'] ?? ''),
            (float) ($item['total'] ?? ($item['amount'] ?? 0)),
        ];
    }
}

