<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A free-form label (New Arrival, Bestseller, Eid Special, Sale) applicable
 * to any taggable model via the polymorphic `taggables` pivot. Only Product
 * consumes this today (Phase 5.B); the polymorphic shape exists so a future
 * module (e.g. Campaign, Phase 7) can reuse the same tag library without a
 * schema change.
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'color',
    ];

    /**
     * @return MorphToMany<Product, $this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
}
