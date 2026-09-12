<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BouquetUnit;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BouquetUnitExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    public function __construct(
        private readonly array $filters = []
    ) {}

    public function query(): Builder
    {
        $activeStatus = $this->filters['active_status'] ?? 'active';
        $itemType = $this->filters['item_type'] ?? 'catalog';

        return BouquetUnit::query()
            ->with(['type.category'])
            ->when($this->filters['search'] ?? null, fn ($q, $search) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%"))
            ->when($this->filters['type_id'] ?? null, fn ($q, $typeId) => $q->where('type_id', $typeId))
            ->when($activeStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($activeStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($itemType === 'custom', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('is_custom', true)))
            ->when($itemType === 'catalog', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('is_custom', false)))
            ->when(!empty($this->filters['trashed']), fn ($q) => $q->onlyTrashed())
            ->orderBy($this->filters['sort_by'] ?? 'created_at', $this->filters['sort_dir'] ?? 'desc');
    }

    public function headings(): array
    {
        return [
            'SKU / Kode Bouquet',
            'Nama Bouquet',
            'Kategori',
            'Tipe Bouquet',
            'Harga Jual (IDR)',
            'Status',
            'Deskripsi',
        ];
    }

    /**
     * @param BouquetUnit $unit
     */
    public function map($unit): array
    {
        return [
            $unit->serial_number,
            $unit->name,
            $unit->type?->category?->name ?? '-',
            $unit->type?->name ?? '-',
            (float) $unit->price,
            $unit->is_active ? 'Aktif' : 'Non-aktif',
            $unit->description ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E11D48'], // Rose 600
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@',
            'E' => '#,##0',
        ];
    }
}
