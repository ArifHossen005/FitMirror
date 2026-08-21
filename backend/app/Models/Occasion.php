<?php

namespace App\Models;

use App\Enums\OccasionStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OccasionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A flat merchandising tag applied to a product as a whole (Wedding, Eid,
 * Office, Casual, Party) — see App\Enums\AttributeType's own docblock for
 * why this is a separate table from the attribute system rather than an
 * AttributeType::Occasion case.
 */
class Occasion extends Model
{
    /** @use HasFactory<OccasionFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'icon',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => OccasionStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_occasion');
    }
}
