<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrderDetail extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id',
        'item_type',
        'quantity',
        'subtotal',
        'bouquet_unit_id',
        'inventory_item_id',
        'money_bouquet',
        'greeting_card',
        'sender_name',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'money_bouquet' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bouquetUnit(): BelongsTo
    {
        return $this->belongsTo(BouquetUnit::class, 'bouquet_unit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class, 'inventory_item_id');
    }
}
