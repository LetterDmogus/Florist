<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockMovement extends Model
{
    use LogsActivity;

    public const TYPE_LABELS = [
        'in' => 'Pembelian',
        'out' => 'Pemakaian',
        'sold' => 'Jual',
        'damaged' => 'Rusak',
    ];

    protected $fillable = [
        'item_id',
        'user_id',
        'quantity',
        'price_at_the_time',
        'total',
        'description',
        'type',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_at_the_time' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
