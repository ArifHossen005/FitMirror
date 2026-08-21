<?php

namespace App\Models;

use App\Enums\ProductVariantStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One buyable color/size combination of a Product. `color_attr_id`/
 * `size_attr_id` each point at an App\Models\AttributeValue whose parent
 * Attribute has type Color/Size respectively — enforced in
 * App\Services\Product\ProductVariantValidator, not a DB constraint, since
 * MySQL cannot express "the referenced row's parent has type X".
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'sku',
        'color_attr_id',
        'size_attr_id',
        'price',
        'stock',
        'low_stock_threshold',
        'barcode',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'status' => ProductVariantStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<AttributeValue, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'color_attr_id');
    }

    /**
     * @return BelongsTo<AttributeValue, $this>
     */
    public function size(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'size_attr_id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        // ->latest('id'), not the default ->latest() (created_at) — two
        // movements created within the same second (routine in a fast
        // adjust-then-adjust flow, and in tests) tie on created_at, and MySQL
        // gives no ordering guarantee among tied rows. id is monotonic and
        // always breaks the tie correctly.
        return $this->hasMany(StockMovement::class, 'variant_id')->latest('id');
    }

    /**
     * @param Builder<ProductVariant> $query
     * @return Builder<ProductVariant>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Compared against the tenant-wide aggregate `stock`, not a per-branch
     * on-hand figure — StockService's own docblock explains why that scope
     * was kept bounded rather than a per-(variant, store) threshold.
     */
    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null && $this->stock <= $this->low_stock_threshold;
    }

    /**
     * This variant's own price override, or the parent product's
     * effectivePrice() when it has none — see the class docblock on why
     * `price` is nullable.
     */
    public function effectivePrice(): string
    {
        if ($this->price !== null) {
            return (string) $this->price;
        }

        return $this->product->effectivePrice();
    }
}
