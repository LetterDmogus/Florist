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

    protected $fillable = [
        'item_id',
        'quantity',
        'price_at_the_time',
        'total',
        'description',
        'type',
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
}
