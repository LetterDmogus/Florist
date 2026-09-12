<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class ImportBouquetUnitAction
{
    /**
     * @return array{
     *     success_count: int,
     *     updated_count: int,
     *     skipped_count: int,
     *     errors: array<int, string>,
     * }
     */
    public function handle(string $filePath, ?int $defaultTypeId, User $user): array
    {
        $sheets = Excel::toArray((object) [], $filePath);

        if (empty($sheets) || empty($sheets[0])) {
            throw new RuntimeException('File Excel kosong atau tidak terbaca.');
        }

        $rows = $sheets[0];
        if (count($rows) <= 1) {
            throw new RuntimeException('File Excel tidak memiliki baris data bouquet.');
        }

        // Header
        array_shift($rows);

        // Preload BouquetTypes keyed by normalized lowercase name
        $typesByName = BouquetType::all()->keyBy(fn (BouquetType $type) => strtolower(trim($type->name)));

        // Preload default type if provided
        $defaultType = $defaultTypeId ? BouquetType::find($defaultTypeId) : null;

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::transaction(function () use (
            $rows,
            $typesByName,
            $defaultType,
            $user,
            &$createdCount,
            &$updatedCount,
            &$skippedCount,
            &$errors
        ): void {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // Expected columns:
                // 0: SKU / Kode Bouquet (Wajib)
                // 1: Tipe Bouquet (Wajib jika tidak memilih default / Opsional)
                // 2: Nama Bouquet (Wajib)
                // 3: Harga Jual IDR (Wajib)
                // 4: Deskripsi (Opsional)
                // 5: Status (Opsional, AKTIF / NONAKTIF)

                $serialNumber = trim((string) ($row[0] ?? ''));
                $typeName = trim((string) ($row[1] ?? ''));
                $name = trim((string) ($row[2] ?? ''));
                $priceRaw = $row[3] ?? null;
                $description = trim((string) ($row[4] ?? ''));
                $statusRaw = strtoupper(trim((string) ($row[5] ?? '')));

                // Skip blank row
                if ($serialNumber === '' && $name === '' && empty($priceRaw)) {
                    continue;
                }

                if ($serialNumber === '') {
                    $errors[] = "Baris {$rowNumber}: SKU / Kode bouquet wajib diisi.";
                    $skippedCount++;
                    continue;
                }

                if ($name === '') {
                    $errors[] = "Baris {$rowNumber} (SKU: {$serialNumber}): Nama bouquet wajib diisi.";
                    $skippedCount++;
                    continue;
                }

                if (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors[] = "Baris {$rowNumber} (SKU: {$serialNumber}): Harga jual harus berupa angka dan >= 0.";
                    $skippedCount++;
                    continue;
                }

                $price = (float) $priceRaw;

                // Resolve Bouquet Type
                $type = null;
                if ($typeName !== '') {
                    $type = $typesByName->get(strtolower($typeName));
                }
                if (! $type && $defaultType) {
                    $type = $defaultType;
                }

                if (! $type) {
                    $errors[] = "Baris {$rowNumber} (SKU: {$serialNumber}): Tipe bouquet '{$typeName}' tidak ditemukan.";
                    $skippedCount++;
                    continue;
                }

                $isActive = true;
                if ($statusRaw === 'NONAKTIF' || $statusRaw === 'INAKTIF' || $statusRaw === '0' || $statusRaw === 'FALSE' || $statusRaw === 'TIDAK') {
                    $isActive = false;
                }

                /** @var BouquetUnit|null $existingUnit */
                $existingUnit = BouquetUnit::withTrashed()
                    ->where('serial_number', $serialNumber)
                    ->first();

                if ($existingUnit) {
                    // Update existing
                    if ($existingUnit->trashed()) {
                        $existingUnit->restore();
                    }

                    $existingUnit->update([
                        'type_id' => $type->id,
                        'name' => $name,
                        'price' => $price,
                        'description' => $description !== '' ? $description : $existingUnit->description,
                        'is_active' => $isActive,
                    ]);

                    $updatedCount++;
                } else {
                    // Create new
                    BouquetUnit::create([
                        'type_id' => $type->id,
                        'serial_number' => $serialNumber,
                        'name' => $name,
                        'price' => $price,
                        'description' => $description !== '' ? $description : null,
                        'is_active' => $isActive,
                    ]);

                    $createdCount++;
                }
            }
        });

        return [
            'success_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'errors' => array_slice($errors, 0, 10),
        ];
    }
}
