<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    public function __construct(
        private readonly array $filters = []
    ) {}

    public function query(): Builder
    {
        return StockMovement::query()
            ->with([
                'item:id,name,serial_number,stock,price',
                'order:id,customer_id',
                'order.customer:id,name',
            ])
            ->when($this->filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($builder) use ($search): void {
                    if (is_numeric($search)) {
                        $builder
                            ->orWhere('id', (int) $search)
                            ->orWhere('order_id', (int) $search);
                    }

                    $builder
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when($this->filters['item_id'] ?? null, fn ($q, $itemId) => $q->where('item_id', $itemId))
            ->when($this->filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($this->filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($this->filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($this->filters['sort_by'] ?? 'created_at', $this->filters['sort_dir'] ?? 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal',
            'Kode Item',
            'Nama Item',
            'Tipe Pergerakan',
            'Qty',
            'Harga Satuan',
            'Total',
            'Referensi / Order',
            'Customer',
            'Keterangan',
        ];
    }

    /**
     * @param StockMovement $movement
     */
    public function map($movement): array
    {
        $typeLabel = StockMovement::TYPE_LABELS[$movement->type] ?? $movement->type;
        $qtyPrefix = $movement->type === 'in' ? '+' : '-';
        $reference = $movement->order_id ? "Order #{$movement->order_id}" : 'Manual';
        $customerName = $movement->order?->customer?->name ?? '-';

        return [
            $movement->id,
            $movement->created_at?->format('Y-m-d H:i'),
            $movement->item?->serial_number ?? '-',
            $movement->item?->name ?? '-',
            $typeLabel,
            $qtyPrefix . $movement->quantity,
            (float) $movement->price_at_the_time,
            (float) $movement->total,
            $reference,
            $customerName,
            $movement->description ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DB2777'], // Pink 600
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
            'B' => '@',
            'C' => '@',
            'G' => '#,##0',
            'H' => '#,##0',
        ];
    }
}
