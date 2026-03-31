<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchaseReportExport implements FromArray, ShouldAutoSize, WithTitle
{
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
            ['Bees Fleur'],
            ["Laporan Pembelian {$monthLabel} {$this->year}"],
            [],
            ['No', 'Date', 'Item', 'RMB', 'IDR', 'Freight', 'No Resi', 'Kode', 'Estimate Arrived', 'Total'],
        ];

        foreach ($this->purchaseRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['item'] ?? ''),
                (float) ($row['rmb'] ?? 0),
                (float) ($row['idr'] ?? 0),
                (float) ($row['freight'] ?? 0),
                (string) ($row['tracking_number'] ?? ''),
                (string) ($row['code'] ?? ''),
                (string) ($row['estimate_arrived'] ?? ''),
                (float) ($row['total'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Supply Purchase'];
        $rows[] = ['No', 'Date', 'Item', 'RMB', 'IDR', 'Freight', 'No Resi', 'Kode', 'Estimate Arrived', 'Total'];
        foreach ($this->supplyPurchaseRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['item'] ?? ''),
                (float) ($row['rmb'] ?? 0),
                (float) ($row['idr'] ?? 0),
                (float) ($row['freight'] ?? 0),
                (string) ($row['tracking_number'] ?? ''),
                (string) ($row['code'] ?? ''),
                (string) ($row['estimate_arrived'] ?? ''),
                (float) ($row['total'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Biaya Toko'];
        $rows[] = ['No', 'Date', 'Description', 'Amount'];
        foreach ($this->storeExpenseRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['description'] ?? ''),
                (float) ($row['amount'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Biaya Bahan Baku'];
        $rows[] = ['No', 'Date', 'Description', 'Amount'];
        foreach ($this->rawMaterialRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['description'] ?? ''),
                (float) ($row['amount'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Biaya Shipping'];
        $rows[] = ['No', 'Date', 'Description', 'Amount'];
        foreach ($this->shippingRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['description'] ?? ''),
                (float) ($row['amount'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Refund'];
        $rows[] = ['No', 'Date', 'Description', 'RMB', 'Rate', 'IDR'];
        foreach ($this->refundRows as $row) {
            $rows[] = [
                (int) ($row['no'] ?? 0),
                (string) ($row['date'] ?? ''),
                (string) ($row['description'] ?? ''),
                (float) ($row['rmb'] ?? 0),
                (float) ($row['rate'] ?? 0),
                (float) ($row['idr'] ?? 0),
            ];
        }

        $rows[] = [];
        $rows[] = ['Rekap'];
        $rows[] = ['Total Pembelian', (float) ($this->purchaseSummary['purchase_total'] ?? 0)];
        $rows[] = ['Total Supply Purchase', (float) ($this->purchaseSummary['supply_purchase_total'] ?? 0)];
        $rows[] = ['Total Biaya Toko', (float) ($this->purchaseSummary['store_expense_total'] ?? 0)];
        $rows[] = ['Total Biaya Bahan Baku', (float) ($this->purchaseSummary['raw_material_expense_total'] ?? 0)];
        $rows[] = ['Total Biaya', (float) ($this->purchaseSummary['total_expense'] ?? 0)];
        $rows[] = ['Refund RMB', (float) ($this->purchaseSummary['refund_rmb_total'] ?? 0)];
        $rows[] = ['Refund IDR', (float) ($this->purchaseSummary['refund_idr_total'] ?? 0)];
        $rows[] = ['Grand Total', (float) ($this->purchaseSummary['grand_total'] ?? 0)];

        return $rows;
    }

    public function title(): string
    {
        return 'Pembelian';
    }
}
