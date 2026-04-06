<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BouquetType extends Model
{
    use HasAuditTrail, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'is_custom',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BouquetCategory::class, 'category_id');
    }

    public function bouquetUnits(): HasMany
    {
        return $this->hasMany(BouquetUnit::class, 'type_id');
    }
}
