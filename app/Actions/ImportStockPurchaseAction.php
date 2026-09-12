<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ItemUnit;
use App\Models\ReportEntry;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class ImportStockPurchaseAction
{
    /**
     * @return array{
     *     success_count: int,
     *     skipped_count: int,
     *     errors: array<int, string>,
     * }
     */
    public function handle(string $filePath, User $user): array
    {
        // Read raw sheets via Maatwebsite Excel
        $sheets = Excel::toArray((object) [], $filePath);

        if (empty($sheets) || empty($sheets[0])) {
            throw new RuntimeException('File Excel kosong atau tidak terbaca.');
        }

        $rows = $sheets[0];
        if (count($rows) <= 1) {
            throw new RuntimeException('File Excel tidak memiliki baris data pembelian.');
        }

        // Row 0 is header, rows 1..N are data
        $header = array_shift($rows);

        // Preload items keyed by uppercase serial number
        $itemsBySerial = ItemUnit::query()
            ->get()
            ->keyBy(fn (ItemUnit $item) => strtoupper(trim((string) $item->serial_number)));

        $successCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $itemsBySerial, $user, &$successCount, &$skippedCount, &$errors): void {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // 1-indexed, skipping header row 1

                // Extract values according to StockPurchaseTemplateExport
                // 0: Kode Item (Wajib)
                // 1: Nama Item (Referensi/Opsional)
                // 2: Qty (Wajib)
                // 3: Harga Beli Satuan IDR (Wajib)
                // 4: Keterangan (Opsional)
                // 5: Tanggal Pembelian (YYYY-MM-DD / Opsional)
                // 6: Ongkir IDR (Opsional)
                // 7: No Resi (Opsional)
                // 8: Kode Pengiriman (Opsional)
                // 9: Estimasi Tiba (YYYY-MM-DD / Opsional)
                // 10: Harga Satuan RMB (Opsional)

                $serialNumber = trim((string) ($row[0] ?? ''));
                $qtyRaw = $row[2] ?? null;
                $priceRaw = $row[3] ?? null;

                // Skip entirely empty row
                if ($serialNumber === '' && empty($qtyRaw) && empty($priceRaw)) {
                    continue;
                }

                if ($serialNumber === '') {
                    $errors[] = "Baris {$rowNumber}: Kode item kosong.";
                    $skippedCount++;
                    continue;
                }

                $quantity = (int) round((float) ($qtyRaw ?? 0));
                if ($quantity <= 0) {
                    $errors[] = "Baris {$rowNumber} (Kode: {$serialNumber}): Jumlah / Qty harus lebih besar dari 0.";
                    $skippedCount++;
                    continue;
                }

                $unitPrice = (float) ($priceRaw ?? 0.0);
                if ($unitPrice < 0) {
                    $errors[] = "Baris {$rowNumber} (Kode: {$serialNumber}): Harga beli satuan tidak boleh negatif.";
                    $skippedCount++;
                    continue;
                }

                $serialKey = strtoupper($serialNumber);
                /** @var ItemUnit|null $item */
                $item = $itemsBySerial->get($serialKey);

                if (! $item) {
                    $errors[] = "Baris {$rowNumber}: Item dengan kode '{$serialNumber}' tidak ditemukan di sistem.";
                    $skippedCount++;
                    continue;
                }

                $description = trim((string) ($row[4] ?? ''));
                $purchaseDate = $this->parseDate($row[5] ?? null) ?? now()->format('Y-m-d');
                $freight = !empty($row[6]) ? (float) $row[6] : null;
                $noResi = !empty($row[7]) ? trim((string) $row[7]) : null;
                $kodePengiriman = !empty($row[8]) ? trim((string) $row[8]) : null;
                $estimateArrived = $this->parseDate($row[9] ?? null);
                $priceRmb = !empty($row[10]) ? (float) $row[10] : null;

                $total = $unitPrice * $quantity;

                // 1. Create StockMovement
                $movement = StockMovement::create([
                    'item_id' => $item->id,
                    'user_id' => $user->id,
                    'quantity' => $quantity,
                    'price_at_the_time' => $unitPrice,
                    'total' => $total,
                    'description' => $description !== '' ? $description : "Import pembelian item {$item->name}",
                    'type' => 'in',
                    'order_id' => null,
                    'created_at' => Carbon::parse($purchaseDate)->setTimeFrom(now()),
                    'updated_at' => Carbon::parse($purchaseDate)->setTimeFrom(now()),
                ]);

                // 2. Increment item stock
                $item->increment('stock', $quantity);

                // 3. Create ReportEntry (purchase_supply)
                $reportDescription = sprintf('Pembelian %d item %s.', $quantity, $item->name);
                if ($description !== '') {
                    $reportDescription .= ' ' . $description;
                }

                ReportEntry::create([
                    'category' => 'purchase_supply',
                    'description' => $reportDescription,
                    'occurred_on' => $purchaseDate,
                    'amount_idr' => $total,
                    'amount_rmb' => $priceRmb !== null ? $priceRmb * $quantity : null,
                    'exchange_rate' => null,
                    'freight_idr' => $freight,
                    'tracking_number' => $noResi,
                    'code' => $kodePengiriman,
                    'estimated_arrived_on' => $estimateArrived,
                    'notes' => "Generated from Stock Movement #{$movement->id} (Import Excel)",
                ]);

                $successCount++;
            }
        });

        return [
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
            'errors' => array_slice($errors, 0, 10), // Batasi pesan error maksimal 10
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
