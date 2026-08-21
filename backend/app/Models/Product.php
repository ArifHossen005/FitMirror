<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A catalog listing. The plan's `skus` limit counts published/draft
 * products (ProductStatus::countsTowardSkuLimit()), enforced in
 * App\Services\Product\ProductService — never here, matching StoreService's
 * own reasoning for why limits are checked at the service call site.
 *
 * `base_price`/`sale_price` live on the product; a variant only needs its
 * own `price` row when it genuinely differs (ProductVariant::effectivePrice()).
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'description',
        'brand',
        'base_price',
        'sale_price',
        'status',
        'is_tryon_ready',
        'season',
        'publish_at',
        'unpublish_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'status' => ProductStatus::class,
            'is_tryon_ready' => 'boolean',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Occasion, $this>
     */
    public function occasions(): BelongsToMany
    {
        return $this->belongsToMany(Occasion::class, 'product_occasion');
    }

    /**
     * Descriptive (non-variant-defining) attribute values — Fabric/Custom
     * only, see AttributeType::definesVariantAxis().
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * @return BelongsToMany<SizeChart, $this>
     */
    public function sizeCharts(): BelongsToMany
    {
        return $this->belongsToMany(SizeChart::class, 'product_size_chart');
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest('created_at');
    }

    /**
     * @param Builder<Product> $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published->value);
    }

    /**
     * @param Builder<Product> $query
     * @return Builder<Product>
     */
    public function scopeCountingTowardSkuLimit(Builder $query): Builder
    {
        return $query->whereNot('status', ProductStatus::Archived->value);
    }

    /**
     * Published **and** carrying at least one in-stock, active variant —
     * the "auto-hide out-of-stock products from try-on and catalog"
     * mechanism PROGRESS.md's 5.D checklist asks for. No customer-facing
     * catalog-browsing endpoint exists yet to call this from (Phase 6's
     * kiosk try-on flow and Phase 9's portal are the two that will); the
     * dashboard's own `GET /products` deliberately does not apply it by
     * default, since an owner restocking a product needs to see it while
     * it's still at zero.
     *
     * Requires an active TenantContext, same as any other query against a
     * BelongsToTenant model (Decision D-13) — but worth calling out
     * explicitly here because the failure mode is easy to misdiagnose:
     * the whereHas('variants', ...) below builds a *nested* subquery
     * against ProductVariant, which carries its own independent
     * TenantScope. Bypassing only the outer Product query (e.g.
     * ::withoutTenantScope()) does not reach that nested one — with no
     * active context it still fails closed to zero rows, so every product
     * would silently read as "not visible" regardless of real stock.
     * Found writing VisibleInCatalogScopeTest, not by inspection.
     *
     * @param Builder<Product> $query
     * @return Builder<Product>
     */
    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        return $query->published()->whereHas('variants', function (Builder $variants) {
            /** @var Builder<ProductVariant> $variants */
            return $variants->inStock()->where('status', ProductVariantStatus::Active->value);
        });
    }

    /**
     * The price a customer actually pays — sale_price when set and lower
     * than base_price, otherwise base_price. Centralised here so the
     * dashboard list, the kiosk catalog, and price-history diffing never
     * compute this differently.
     */
    public function effectivePrice(): string
    {
        if ($this->sale_price !== null && (float) $this->sale_price < (float) $this->base_price) {
            return (string) $this->sale_price;
        }

        return (string) $this->base_price;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product')
            ->logOnly(['name', 'sku', 'category_id', 'base_price', 'sale_price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
