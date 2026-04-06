<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ItemUnit extends Model implements HasMedia
{
    use HasAuditTrail, HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'serial_number',
        'name',
        'price',
        'individual',
        'description',
        'stock',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('media')) {
            return null;
        }

        $media = $this->media->first(fn ($item) => $item->collection_name === 'images');

        return $media?->getFullUrl();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id')->withTrashed();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'inventory_item_id');
    }
}
