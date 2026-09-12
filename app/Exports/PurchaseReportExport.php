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
    /** @var array Data baris yang sudah di-generate */
    private array $dataRows = [];

    // Indeks baris Excel (1-indexed) yang dicatat dinamis
    private int $summaryStartRow = 0;
    private int $summaryEndRow = 0;
    private int $tableHeaderRow = 0;
    private int $dataStartRow = 0;
    private int $dataEndRow = 0;
    private int $totalExpenseRow = 0;
    private int $grandTotalRow = 0;

    /** @var int[] Baris header seksi (Pembelian Stok, Supply, dll) */
    private array $sectionHeaderRows = [];

    /** @var int[] Baris subtotal setiap seksi */
    private array $subtotalRows = [];

    /** @var int[] Baris zebra belang-belang */
    private array $zebraRows = [];

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
    ) {
        // Bangun data di Constructor agar semua nomor baris langsung terhitung sebelum styles() dipanggil
        $this->buildExportData();
    }

    private function buildExportData(): void
    {
        $monthLabel = CarbonImmutable::create($this->year, $this->month, 1)->translatedFormat('F');
        $rows = [];

        // Helper append row (1-indexed Excel row)
        $appendRow = function (array $row) use (&$rows): int {
            $rows[] = $row;
            return count($rows);
        };

        // 1. JUDUL LAPORAN (Row 1 - 2)
        $appendRow(['BEES FLEUR FLORIST']);
        $appendRow(["LAPORAN PEMBELIAN - {$monthLabel} {$this->year}"]);
        $appendRow(['']); // Row 3: Spacing

        // 2. SUMMARY PEMBELIAN (Row 4 - 11)
        $this->summaryStartRow = $appendRow(['', 'SUMMARY PEMBELIAN', '', '', '', '', '', '', '', '', '', '']);
        $appendRow(['', 'Total Pembelian Stok', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['purchase_total'] ?? 0)]);
        $appendRow(['', 'Total Supply Purchase', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)]);
        $appendRow(['', 'Total Biaya Toko', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)]);
        $appendRow(['', 'Total Biaya Bahan Baku', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)]);
        $appendRow(['', 'Total Biaya Ongkir', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['shipping_total'] ?? 0)]);
        $appendRow(['', 'Total Refund (IDR)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)]);
        $this->summaryEndRow = count($rows);

        $appendRow(['']); // Spacing

        // 3. TABEL DATA TRANSAKSI
        $this->tableHeaderRow = $appendRow(['No', 'Tanggal', 'Kategori', 'Deskripsi/Item', 'RMB', 'Rate', 'IDR', 'Freight', 'No Resi', 'Kode', 'Est. Arrived', 'Total']);
        $this->dataStartRow = $this->tableHeaderRow + 1;

        $zebraCounter = 0;

        // Seksi 1: Pembelian Stok
        if (!empty($this->purchaseRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Pembelian Stok (Fisik)'));
            foreach ($this->purchaseRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Pembelian Stok'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Pembelian Stok', (float) ($this->purchaseSummary['purchase_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        // Seksi 2: Supply Purchase
        if (!empty($this->supplyPurchaseRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Supply Purchase (Impor/Luar)'));
            foreach ($this->supplyPurchaseRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Supply Purchase'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Supply Purchase', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        // Seksi 3: Biaya Toko
        if (!empty($this->storeExpenseRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Biaya Toko'));
            foreach ($this->storeExpenseRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Biaya Toko'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Biaya Toko', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        // Seksi 4: Biaya Bahan Baku
        if (!empty($this->rawMaterialRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Biaya Bahan Baku'));
            foreach ($this->rawMaterialRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Biaya Bahan Baku'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Biaya Bahan Baku', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        // Seksi 5: Biaya Ongkir
        if (!empty($this->shippingRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Biaya Ongkir'));
            foreach ($this->shippingRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Biaya Ongkir'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Biaya Ongkir', (float) ($this->purchaseSummary['shipping_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        // Seksi 6: Refund
        if (!empty($this->refundRows)) {
            $this->sectionHeaderRows[] = $appendRow($this->makeSectionHeader('Refund'));
            foreach ($this->refundRows as $index => $item) {
                $rowNum = $appendRow($this->mapRow($index + 1, $item, 'Refund'));
                if ($zebraCounter++ % 2 === 1) $this->zebraRows[] = $rowNum;
            }
            $this->subtotalRows[] = $appendRow($this->makeSubtotalRow('Total Refund', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)));
            $appendRow(['']); // Spacing
        }

        $this->dataEndRow = count($rows);

        // Final Totals (Ringkasan Bawah)
        $this->totalExpenseRow = $appendRow(['', 'TOTAL PENGELUARAN (Semua Biaya)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['total_expense'] ?? 0)]);
        $this->grandTotalRow = $appendRow(['', 'GRAND TOTAL (Total Biaya + Stok - Refund)', '', '', '', '', '', '', '', '', '', (float) ($this->purchaseSummary['grand_total'] ?? 0)]);

        $this->dataRows = $rows;
    }

    public function array(): array
    {
        return $this->dataRows;
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Core Alignment & Merges
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->getStyle('A1:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 2. Title Typography
        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A2:L2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('374151');

        // 3. Summary Table Styles (Row summaryStartRow s/d summaryEndRow)
        $sheet->getStyle("B{$this->summaryStartRow}")->getFont()->setBold(true)->getColor()->setRGB('9D174D');
        for ($r = $this->summaryStartRow; $r <= $this->summaryEndRow; $r++) {
            $sheet->getStyle("B{$r}")->getFont()->setBold(true);
            $sheet->getStyle("L{$r}")->getFont()->setBold(true);
            $sheet->getStyle("L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("B{$this->summaryStartRow}:L{$this->summaryEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('F472B6');

        // 4. Main Table Header
        $headerRange = "A{$this->tableHeaderRow}:L{$this->tableHeaderRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DB2777']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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
        $footerRows = [$this->totalExpenseRow, $this->grandTotalRow];
        foreach ($footerRows as $row) {
            $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE7F3');
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // 9. General Formatting & Borders
        $tableRange = "A{$this->tableHeaderRow}:L{$this->grandTotalRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('F472B6');
        $sheet->getStyle("A{$this->dataStartRow}:A{$this->grandTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$this->tableHeaderRow}:D{$this->grandTotalRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("I{$this->tableHeaderRow}:K{$this->grandTotalRow}")->getAlignment()->setWrapText(true);

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
