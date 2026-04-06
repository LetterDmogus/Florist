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

class BouquetUnit extends Model implements HasMedia
{
    use HasAuditTrail, HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'type_id',
        'serial_number',
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected $appends = ['image_url', 'money_bouquet'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('media')) {
            return null;
        }

        $media = $this->media->first(fn ($item) => $item->collection_name === 'images');

        return $media?->getFullUrl();
    }

    public function getMoneyBouquetAttribute(): float
    {
        return (float) $this->price;
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BouquetType::class, 'type_id');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'bouquet_unit_id');
    }
}
