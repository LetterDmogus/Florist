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
    private int $tableHeaderRow = 9;

    private int $dataStartRow = 12;

    private int $dataEndRow = 12;

    private int $recapTitleRow = 0;

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
            ['','SUMMARY PEMBELIAN'],
            ['','Total Pembelian Stok', (float) ($this->purchaseSummary['purchase_total'] ?? 0)],
            ['','Total Supply Purchase', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)],
            ['','Total Biaya Toko', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)],
            ['','Total Biaya Bahan Baku', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)],
            ['','Total Refund (IDR)', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)],
            [],
            ['No', 'Tanggal', 'Kategori', 'Deskripsi/Item', 'RMB', 'Rate', 'IDR', 'Freight', 'No Resi', 'Kode', 'Est. Arrived', 'Total'],
        ];

        $detailRows = $this->buildDetailRows();
        if ($detailRows === []) {
            $detailRows[] = ['-', '-', 'Tidak ada data', 'Tidak ada data pada periode ini', 0, 0, 0, 0, '', '', '', 0];
        }

        foreach ($detailRows as $row) {
            $rows[] = $row;
        }

        $this->dataEndRow = $this->tableHeaderRow + count($detailRows);

        $rows[] = [];
        $this->recapTitleRow = count($rows) -2;
        $rows[] = ['','REKAPITULASI PENGELUARAN'];
        $rows[] = ['','Total Pembelian Stok', (float) ($this->purchaseSummary['purchase_total'] ?? 0)];
        $rows[] = ['','Total Supply Purchase', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)];
        $rows[] = ['','Total Biaya Toko', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)];
        $rows[] = ['','Total Biaya Bahan Baku', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)];
        $rows[] = ['','TOTAL BIAYA OPERASIONAL', (float) ($this->purchaseSummary['total_expense'] ?? 0)];
        $rows[] = ['','Total Refund (IDR)', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)];
        $rows[] = ['','GRAND TOTAL PENGELUARAN', (float) ($this->purchaseSummary['grand_total'] ?? 0)];

        $this->grandTotalRow = count($rows)-3;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');

        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A1:L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:L2')->getFont()->setBold(true)->setSize(12);

        $sheet->getStyle('B3')->getFont()->setBold(true)->getColor()->setRGB('9D174D');
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(11);

        $sheet->getStyle("A{$this->tableHeaderRow}:L{$this->tableHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DB2777']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $tableRange = "A{$this->tableHeaderRow}:L{$this->dataEndRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->getColor()->setRGB('F472B6');
        $sheet->getStyle($tableRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A{$this->dataStartRow}:C{$this->dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($row = $this->dataStartRow; $row <= $this->dataEndRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:L{$row}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FFF1F7');
            }
        }

        $sheet->getStyle("D{$this->tableHeaderRow}:D{$sheet->getHighestRow()}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("I{$this->tableHeaderRow}:K{$sheet->getHighestRow()}")->getAlignment()->setWrapText(true);
        $sheet->freezePane('A12');

        if ($this->recapTitleRow > 0) {
            $sheet->getStyle("B{$this->recapTitleRow}")->getFont()->setBold(true)->setColor(new Color('9D174D'));
        }

        if ($this->grandTotalRow > 0) {
            $sheet->getStyle("A{$this->grandTotalRow}:B{$this->grandTotalRow}")
                ->getFont()
                ->setBold(true)
                ->setSize(12);
            $sheet->getStyle("A{$this->grandTotalRow}:B{$this->grandTotalRow}")
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

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildDetailRows(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->purchaseRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Pembelian Stok',
                description: (string) ($row['item'] ?? ''),
                rmb: (float) ($row['rmb'] ?? 0),
                rate: 0,
                idr: (float) ($row['idr'] ?? 0),
                freight: (float) ($row['freight'] ?? 0),
                trackingNumber: (string) ($row['tracking_number'] ?? ''),
                code: (string) ($row['code'] ?? ''),
                estimateArrived: (string) ($row['estimate_arrived'] ?? ''),
                total: (float) ($row['total'] ?? 0),
            );
        }

        foreach ($this->supplyPurchaseRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Supply Purchase',
                description: (string) ($row['item'] ?? ''),
                rmb: (float) ($row['rmb'] ?? 0),
                rate: 0,
                idr: (float) ($row['idr'] ?? 0),
                freight: (float) ($row['freight'] ?? 0),
                trackingNumber: (string) ($row['tracking_number'] ?? ''),
                code: (string) ($row['code'] ?? ''),
                estimateArrived: (string) ($row['estimate_arrived'] ?? ''),
                total: (float) ($row['total'] ?? 0),
            );
        }

        foreach ($this->storeExpenseRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Biaya Toko',
                description: (string) ($row['description'] ?? ''),
                rmb: 0,
                rate: 0,
                idr: (float) ($row['amount'] ?? 0),
                freight: 0,
                trackingNumber: '',
                code: '',
                estimateArrived: '',
                total: (float) ($row['amount'] ?? 0),
            );
        }

        foreach ($this->rawMaterialRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Biaya Bahan Baku',
                description: (string) ($row['description'] ?? ''),
                rmb: 0,
                rate: 0,
                idr: (float) ($row['amount'] ?? 0),
                freight: 0,
                trackingNumber: '',
                code: '',
                estimateArrived: '',
                total: (float) ($row['amount'] ?? 0),
            );
        }

        foreach ($this->shippingRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Biaya Ongkir',
                description: (string) ($row['description'] ?? ''),
                rmb: 0,
                rate: 0,
                idr: (float) ($row['amount'] ?? 0),
                freight: 0,
                trackingNumber: '',
                code: '',
                estimateArrived: '',
                total: (float) ($row['amount'] ?? 0),
            );
        }

        foreach ($this->refundRows as $row) {
            $rows[] = $this->mapRow(
                no: $no++,
                date: (string) ($row['date'] ?? ''),
                category: 'Refund',
                description: (string) ($row['description'] ?? ''),
                rmb: (float) ($row['rmb'] ?? 0),
                rate: (float) ($row['rate'] ?? 0),
                idr: (float) ($row['idr'] ?? 0),
                freight: 0,
                trackingNumber: '',
                code: '',
                estimateArrived: '',
                total: (float) ($row['idr'] ?? 0),
            );
        }

        return $rows;
    }

    /**
     * @return array<int, mixed>
     */
    private function mapRow(
        int $no,
        string $date,
        string $category,
        string $description,
        float $rmb,
        float $rate,
        float $idr,
        float $freight,
        string $trackingNumber,
        string $code,
        string $estimateArrived,
        float $total,
    ): array {
        return [
            $no,
            $date,
            $category,
            $description,
            $rmb,
            $rate,
            $idr,
            $freight,
            $trackingNumber,
            $code,
            $estimateArrived,
            $total,
        ];
    }
}

