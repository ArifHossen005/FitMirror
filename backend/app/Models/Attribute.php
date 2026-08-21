<?php

namespace App\Models;

use App\Enums\AttributeStatus;
use App\Enums\AttributeType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A named axis of product variation (Color, Size) or a descriptive facet
 * (Fabric, Custom) — see App\Enums\AttributeType for the distinction that
 * governs how App\Models\Product/ProductVariant consume its values.
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'type',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'sort_order' => 'integer',
            'status' => AttributeStatus::class,
        ];
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attribute')
            ->logOnly(['name', 'slug', 'type', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
