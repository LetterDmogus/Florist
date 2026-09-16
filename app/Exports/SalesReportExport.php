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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    /** @var array Data baris yang sudah di-generate */
    private array $dataRows = [];

    // Indeks baris Excel (1-indexed) yang dihitung secara matematis
    private int $summaryStartRow = 0;
    private int $summaryEndRow = 0;
    private int $tableHeaderRow = 0;
    private int $dataStartRow = 0;
    private int $dataEndRow = 0;
    private int $tableTotalRow = 0;
    private int $profitTitleRow = 0;
    private int $profitStartRow = 0;
    private int $netProfitRow = 0;

    /** @var int[] Daftar baris data yang belum lunas / DP untuk di-highlight */
    private array $unpaidDataRows = [];

    public function __construct(
        private readonly array $salesSummary,
        private readonly array $salesRows,
        private readonly array $profitSummary,
        private readonly int $month,
        private readonly int $year,
    ) {
        // Generate data dan hitung posisi baris langsung di Constructor!
        // Ini menjamin posisi baris sudah 100% siap sebelum styles() atau array() dipanggil.
        $this->buildExportData();
    }

    /**
     * Membangun susunan baris dan memetakan letak indeks Excel (1-indexed).
     */
    private function buildExportData(): void
    {
        $monthLabel = CarbonImmutable::create($this->year, $this->month, 1)->translatedFormat('F');
        $rows = [];

        // Helper untuk memasukkan baris sekaligus mendapatkan nomor baris Excel (1-based)
        $appendRow = function (array $row) use (&$rows): int {
            $rows[] = $row;
            return count($rows); // Nomor baris di Excel
        };

        // 1. JUDUL LAPORAN (Row 1 - 2)
        $appendRow(['BEES FLEUR FLORIST']);
        $appendRow(["LAPORAN PENJUALAN - {$monthLabel} {$this->year}"]);
        $appendRow(['']); // Row 3 (Pemisah kosong)

        // 2. SUMMARY PENJUALAN (Row 4 s/d 11)
        $this->summaryStartRow = $appendRow(['', 'SUMMARY PENJUALAN']); // Row 4
        $appendRow(['', 'Money', (float) ($this->salesSummary['money'] ?? 0)]);
        $appendRow(['', 'Fee', (float) ($this->salesSummary['fee'] ?? 0)]);
        $appendRow(['', 'Diskon', (float) ($this->salesSummary['discount'] ?? 0)]);
        $appendRow(['', 'Gosend', (float) ($this->salesSummary['gosend'] ?? 0)]);
        $appendRow(['', 'TOTAL PENJUALAN', (float) ($this->salesSummary['total'] ?? 0)]);
        $appendRow(['', 'TOTAL DP DITERIMA', (float) ($this->salesSummary['dp'] ?? 0)]);
        $this->summaryEndRow = $appendRow(['', 'TOTAL PIUTANG (BELUM LUNAS / DP)', (float) ($this->salesSummary['unpaid_total'] ?? 0)]);
        
        $appendRow(['']); // Pemisah kosong

        // 3. TABEL DATA TRANSAKSI
        $this->tableHeaderRow = $appendRow(['No', 'Tanggal', 'Model Bouquet / Item', 'Status', 'DP Diterima', 'Belum Dibayar', 'Money', 'Fee', 'Diskon', 'Gosend', 'Total']);
        $this->dataStartRow = $this->tableHeaderRow + 1;
        $this->unpaidDataRows = [];

        if (empty($this->salesRows)) {
            $appendRow(['-', '-', 'Tidak ada data penjualan pada periode ini', '-', 0, 0, 0, 0, 0, 0, 0]);
        } else {
            foreach ($this->salesRows as $item) {
                $isUnpaid = !empty($item['is_unpaid']) || in_array($item['payment_status'] ?? '', ['dp', 'unpaid'], true);
                $statusLabel = match ($item['payment_status'] ?? '') {
                    'dp' => 'DP',
                    'unpaid' => 'Belum Lunas',
                    default => 'Lunas',
                };

                $rowNum = $appendRow([
                    (int) ($item['no'] ?? 0),
                    (string) ($item['date'] ?? ''),
                    (string) ($item['model'] ?? ''),
                    $statusLabel,
                    (float) ($item['dp'] ?? 0),
                    (float) ($item['unpaid_amount'] ?? 0),
                    (float) ($item['money'] ?? 0),
                    (float) ($item['fee'] ?? 0),
                    (float) ($item['discount'] ?? 0),
                    (float) ($item['gosend'] ?? 0),
                    (float) ($item['total'] ?? 0),
                ]);

                if ($isUnpaid) {
                    $this->unpaidDataRows[] = $rowNum;
                }
            }
        }
        $this->dataEndRow = count($rows);

        // 4. BARIS TOTAL TABEL DATA
        $this->tableTotalRow = $appendRow([
            'TOTAL',
            '',
            '',
            '',
            (float) ($this->salesSummary['dp'] ?? 0),
            (float) ($this->salesSummary['unpaid_total'] ?? 0),
            (float) ($this->salesSummary['money'] ?? 0),
            (float) ($this->salesSummary['fee'] ?? 0),
            (float) ($this->salesSummary['discount'] ?? 0),
            (float) ($this->salesSummary['gosend'] ?? 0),
            (float) ($this->salesSummary['total'] ?? 0),
        ]);

        $appendRow(['']); // Spacing
        $appendRow(['']); // Spacing

        // 5. RINGKASAN LABA
        $this->profitTitleRow = $appendRow(['', 'RINGKASAN LABA']);
        $this->profitStartRow = $this->profitTitleRow + 1;

        $appendRow(['', 'Pendapatan Florist (Fee)', (float) ($this->profitSummary['florist_income'] ?? 0)]);
        $appendRow(['', 'Pendapatan Supply', (float) ($this->profitSummary['supply_income'] ?? 0)]);
        $appendRow(['', 'Pembelian Stok (Cost)', (float) ($this->profitSummary['purchase_total'] ?? 0)]);
        $appendRow(['', 'Biaya Operasional Toko', (float) ($this->profitSummary['store_expense_total'] ?? 0)]);
        $appendRow(['', 'Biaya Bahan Baku', (float) ($this->profitSummary['raw_material_expense_total'] ?? 0)]);
        $appendRow(['', 'LABA KOTOR', (float) ($this->profitSummary['gross_profit'] ?? 0)]);
        $appendRow(['', 'Penyesuaian (Adjustment)', (float) ($this->profitSummary['adjustment_total'] ?? 0)]);
        $appendRow(['', 'DP Diterima', (float) ($this->salesSummary['dp'] ?? 0)]);
        $appendRow(['', 'Sisa Piutang Penjualan (DP / Belum Lunas)', (float) ($this->salesSummary['unpaid_total'] ?? 0)]);
        $this->netProfitRow = $appendRow(['', 'LABA BERSIH (NET)', (float) ($this->profitSummary['net_profit'] ?? 0)]);

        $this->dataRows = $rows;
    }

    public function array(): array
    {
        return $this->dataRows;
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Judul Utama (Row 1-2)
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:K1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A2:K2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('374151');

        // 2. Kotak Summary Penjualan (Row 4 s/d 11)
        $sheet->getStyle("B{$this->summaryStartRow}")->getFont()->setBold(true)->getColor()->setRGB('9D174D');
        $sheet->getStyle("B" . ($this->summaryEndRow - 1) . ":C" . ($this->summaryEndRow - 1))->getFont()->setBold(true);
        $sheet->getStyle("B{$this->summaryEndRow}:C{$this->summaryEndRow}")->getFont()->setBold(true)->getColor()->setRGB('9F1239');
        $sheet->getStyle("B{$this->summaryStartRow}:C{$this->summaryEndRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('F472B6');
        $sheet->getStyle("C" . ($this->summaryStartRow + 1) . ":C{$this->summaryEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // 3. Header Tabel Data (Row tableHeaderRow)
        $headerRange = "A{$this->tableHeaderRow}:K{$this->tableHeaderRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DB2777']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // 4. Data Rows & Highlight
        for ($r = $this->dataStartRow; $r <= $this->dataEndRow; $r++) {
            // Zebra striping untuk baris genap
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:K{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F7');
            }

            // Alignment standar kolom
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Highlight merah lembut untuk baris yang belum lunas / DP
        foreach ($this->unpaidDataRows as $r) {
            $sheet->getStyle("A{$r}:K{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE4E6']], // Soft Rose
                'font' => ['color' => ['rgb' => '9F1239'], 'bold' => true],
            ]);
        }

        // 5. Baris Total Tabel Data
        $sheet->mergeCells("A{$this->tableTotalRow}:D{$this->tableTotalRow}");
        $sheet->getStyle("A{$this->tableTotalRow}:K{$this->tableTotalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '831843']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE7F3']],
        ]);
        $sheet->getStyle("A{$this->tableTotalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Border seluruh tabel data (Header s/d Total)
        $tableRange = "A{$this->tableHeaderRow}:K{$this->tableTotalRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('F472B6');
        $sheet->getStyle("A{$this->tableTotalRow}:K{$this->tableTotalRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('DB2777');

        // 6. Ringkasan Laba
        $sheet->getStyle("B{$this->profitTitleRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('9D174D');
        $sheet->getStyle("B{$this->profitTitleRow}:C{$this->netProfitRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('F472B6');
        $sheet->getStyle("C{$this->profitStartRow}:C{$this->netProfitRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Highlight Laba Bersih
        $sheet->getStyle("B{$this->netProfitRow}:C{$this->netProfitRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '831843']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE7F3']],
        ]);

        return [];
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 14,  // Tanggal
            'C' => 45,  // Model Bouquet / Item
            'D' => 14,  // Status (DP / Belum Lunas / Lunas)
            'E' => 16,  // DP Diterima
            'F' => 16,  // Belum Dibayar
            'G' => 14,  // Money
            'H' => 14,  // Fee
            'I' => 14,  // Diskon
            'J' => 14,  // Gosend
            'K' => 16,  // Total
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
