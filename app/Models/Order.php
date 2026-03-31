<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity, SoftDeletes;

    public const ORDER_STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready',
        'on_delivery',
        'completed',
    ];

    public const ORDER_STATUS_LABELS = [
        'pending' => 'Belum diproses',
        'confirmed' => 'Sudah diproses',
        'processing' => 'Sedang diproses',
        'ready' => 'Siap di-pickup',
        'on_delivery' => 'Sedang diantar',
        'completed' => 'Selesai (History)',
    ];

    protected $fillable = [
        'user_id',
        'customer_id',
        'total',
        'shipping_date',
        'shipping_time',
        'shipping_type',
        'down_payment',
        'payment_status',
        'order_status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'shipping_date' => 'date',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
