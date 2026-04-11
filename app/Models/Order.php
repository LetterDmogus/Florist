<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasAuditTrail, HasFactory, LogsActivity, SoftDeletes;

    public const ORDER_STATUSES = [
        'pending',
        'ready',
        'on_delivery',
        'completed',
        'canceled',
    ];

    public const ORDER_STATUS_LABELS = [
        'pending' => 'Belum diproses',
        'ready' => 'Siap di-pickup',
        'on_delivery' => 'sedang diantar',
        'completed' => 'Selesai (History)',
        'canceled' => 'Dibatalkan',
    ];

    public const ORDER_STATUS_NEXT = [
        'pending' => ['ready', 'canceled'],
        'ready' => ['on_delivery', 'completed', 'canceled'],
        'on_delivery' => ['completed', 'canceled'],
        'completed' => [],
        'canceled' => [],
    ];

    protected $fillable = [
        'user_id',
        'customer_id',
        'request_id',
        'total',
        'shipping_date',
        'shipping_time',
        'shipping_type',
        'shipping_fee',
        'down_payment',
        'payment_status',
        'order_status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
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

    public static function allowedNextStatuses(string $currentStatus, ?string $shippingType = null): array
    {
        $candidates = self::ORDER_STATUS_NEXT[$currentStatus] ?? [];

        if ($currentStatus === 'ready' && $shippingType === 'pickup') {
            return array_values(array_filter($candidates, fn (string $status): bool => $status !== 'on_delivery'));
        }

        if ($currentStatus === 'ready' && $shippingType === 'delivery') {
            return array_values(array_filter($candidates, fn (string $status): bool => $status !== 'completed'));
        }

        return $candidates;
    }

    public static function canTransition(string $from, string $to, ?string $shippingType = null): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::allowedNextStatuses($from, $shippingType), true);
    }
}
