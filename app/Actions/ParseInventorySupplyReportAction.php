<?php

declare(strict_types=1);

namespace App\Actions;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\RichText\RichText;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ParseInventorySupplyReportAction
{
    public function handle(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(false);

        $spreadsheet = $reader->load($filePath);
        $rows = [];
        $sheetUsed = false;

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $headerMap = $this->findHeaderMap($worksheet);
            if ($headerMap === null) {
                continue;
            }

            $sheetUsed = true;
            $headerRow = $headerMap['__row'];
            $highestRow = (int) $worksheet->getHighestDataRow();

            for ($rowIndex = $headerRow + 1; $rowIndex <= $highestRow; $rowIndex++) {
                $serial = $this->cellText($worksheet, $headerMap['kode'], $rowIndex);
                $name = $this->cellText($worksheet, $headerMap['name'], $rowIndex);

                if ($serial === '' && $name === '') {
                    continue;
                }

                if ($serial === '' || $name === '') {
                    continue;
                }

                $price = $this->cellNumber($worksheet, $headerMap['hargajual'], $rowIndex) ?? 0.0;
                $individual = $this->cellText($worksheet, $headerMap['sheets'] ?? null, $rowIndex);
                $size = $this->cellText($worksheet, $headerMap['size'] ?? null, $rowIndex);
                $stockSisa = $this->cellNumber($worksheet, $headerMap['sisa'] ?? null, $rowIndex);

                if ($stockSisa === null) {
                    $stockAwal = $this->cellNumber($worksheet, $headerMap['stockawal'] ?? null, $rowIndex) ?? 0.0;
                    $stokMasuk = $this->cellNumber($worksheet, $headerMap['stokmasuk'] ?? null, $rowIndex) ?? 0.0;
                    $stokKeluar = $this->cellNumber($worksheet, $headerMap['stokkeluar'] ?? null, $rowIndex) ?? 0.0;
                    $stockSisa = $stockAwal + $stokMasuk - $stokKeluar;
                }

                $rows[] = [
                    'serial_number' => mb_substr($serial, 0, 255),
                    'name' => mb_substr($name, 0, 255),
                    'price' => max(0.0, round($price, 2)),
                    'individual' => mb_substr($individual !== '' ? $individual : 'unit', 0, 100),
                    'description' => $size !== '' ? "Size: {$size}" : null,
                    'stock' => max(0, (int) round($stockSisa)),
                ];
            }
        }

        if (! $sheetUsed) {
            throw new RuntimeException('Format file tidak sesuai. Pastikan ada kolom Kode, Name, Harga Jual, dan Sisa.');
        }

        return $rows;
    }

    private function findHeaderMap(Worksheet $worksheet): ?array
    {
        $highestRow = min((int) $worksheet->getHighestDataRow(), 30);
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $headers = [];

            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = $worksheet->getCell([$column, $row])->getValue();
                if ($value instanceof RichText) {
                    $value = $value->getPlainText();
                }

                $normalized = $this->normalizeHeader((string) $value);
                if ($normalized === '') {
                    continue;
                }

                $headers[$column] = $normalized;
            }

            if ($headers === []) {
                continue;
            }

            $map = [
                'kode' => $this->resolveHeaderColumn($headers, ['kode', 'code']),
                'name' => $this->resolveHeaderColumn($headers, ['name', 'nama']),
                'hargajual' => $this->resolveHeaderColumn($headers, ['hargajual', 'harga']),
                'sheets' => $this->resolveHeaderColumn($headers, ['sheets', 'sheet']),
                'size' => $this->resolveHeaderColumn($headers, ['size', 'ukuran']),
                'stockawal' => $this->resolveHeaderColumn($headers, ['stockawal', 'stokawal']),
                'stokmasuk' => $this->resolveHeaderColumn($headers, ['stokmasuk', 'stockmasuk']),
                'stokkeluar' => $this->resolveHeaderColumn($headers, ['stokkeluar', 'stockkeluar']),
                'sisa' => $this->resolveHeaderColumn($headers, ['sisa', 'stocksisa', 'stoksisa']),
                '__row' => $row,
            ];

            if ($map['kode'] !== null && $map['name'] !== null && $map['hargajual'] !== null && $map['sisa'] !== null) {
                return $map;
            }
        }

        return null;
    }

    private function resolveHeaderColumn(array $headers, array $aliases): ?int
    {
        foreach ($headers as $column => $header) {
            if (in_array($header, $aliases, true)) {
                return $column;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
    }

    private function cellText(Worksheet $worksheet, ?int $column, int $row): string
    {
        if ($column === null) {
            return '';
        }

        $cell = $worksheet->getCell([$column, $row]);
        $value = $cell->getValue();

        if (is_string($value) && str_starts_with($value, '=')) {
            $value = $cell->getOldCalculatedValue();
        }

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        return trim((string) ($value ?? ''));
    }

    private function cellNumber(Worksheet $worksheet, ?int $column, int $row): ?float
    {
        if ($column === null) {
            return null;
        }

        $cell = $worksheet->getCell([$column, $row]);
        $value = $cell->getValue();

        if (is_string($value) && str_starts_with($value, '=')) {
            $value = $cell->getOldCalculatedValue();
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return $this->parseNumberString($value);
    }

    private function parseNumberString(string $value): ?float
    {
        $raw = trim(str_replace(['Rp', 'rp', ' '], '', $value));
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/[^0-9,.-]/', '', $raw) ?? '';
        if ($raw === '' || $raw === '-' || $raw === ',' || $raw === '.') {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $raw);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $raw);
            if (! (count($parts) === 2 && strlen($parts[1]) <= 2)) {
                $raw = str_replace('.', '', $raw);
            }
        }

        return is_numeric($raw) ? (float) $raw : null;
    }
}
