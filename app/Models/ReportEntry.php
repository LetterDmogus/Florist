<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReportEntry extends Model
{
    use LogsActivity, SoftDeletes;

    public const CATEGORIES = [
        'supply_income',
        'purchase_supply',
        'store_expense',
        'raw_material_expense',
        'shipping_expense',
        'refund',
        'profit_adjustment',
    ];

    public const CATEGORY_LABELS = [
        'supply_income' => 'Pendapatan Supply',
        'purchase_supply' => 'Pembelian Supply',
        'store_expense' => 'Biaya Toko',
        'raw_material_expense' => 'Biaya Bahan Baku',
        'shipping_expense' => 'Biaya Gosend/Ongkir',
        'refund' => 'Refund',
        'profit_adjustment' => 'Penyesuaian Laba Bersih',
    ];

    protected $fillable = [
        'user_id',
        'order_id',
        'occurred_on',
        'category',
        'description',
        'amount_idr',
        'amount_rmb',
        'exchange_rate',
        'freight_idr',
        'tracking_number',
        'code',
        'estimated_arrived_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'estimated_arrived_on' => 'date',
            'amount_idr' => 'decimal:2',
            'amount_rmb' => 'decimal:2',
            'exchange_rate' => 'decimal:2',
            'freight_idr' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolveAmountIdr(): float
    {
        $amountIdr = (float) ($this->amount_idr ?? 0);
        if ($amountIdr !== 0.0) {
            return $amountIdr;
        }

        $amountRmb = (float) ($this->amount_rmb ?? 0);
        $rate = (float) ($this->exchange_rate ?? 0);

        if ($amountRmb > 0 && $rate > 0) {
            return $amountRmb * $rate;
        }

        return 0.0;
    }

    public function resolveTotalWithFreight(): float
    {
        return $this->resolveAmountIdr() + (float) ($this->freight_idr ?? 0);
    }
}
