<?php

namespace App\Services\Product;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Media\StorageQuotaService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Product + variant CRUD, the plan's `skus` limit, and the variant
 * add/update/remove diffing PROGRESS.md's 5.B checklist calls for.
 * Mirrors StoreService's shape throughout: the plan check happens before
 * any write (assertWithinLimit + countTowardSkuLimit, same call shape as
 * StoreService::create()), uniqueness checks are asserted here rather than
 * as `unique` form-request rules because they must also cover soft-deleted
 * rows (Store::assertCodeIsUnique()'s own reasoning), and a status change
 * that re-activates a SKU slot is re-checked against the plan the same way
 * StoreService::update() re-checks a re-opened branch.
 */
class ProductService extends BaseService
{
    /** Sub-path under the tenant's own storage prefix that product imagery lives in. */
    private const IMAGE_DIRECTORY = 'products';

    public function __construct(
        private readonly PlanService $plans,
        private readonly PriceHistoryService $priceHistory,
        private readonly ProductImageService $productImages,
        private readonly StorageQuotaService $quota,
    ) {}

    /**
     * No $actor parameter, unlike update() — a brand-new product has no
     * prior price to diff against, so nothing is ever recorded to
     * price_history at creation time.
     *
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Product
    {
        $this->plans->assertWithinLimit($tenant, 'skus', $this->countTowardSkuLimit($tenant));
        $this->assertSkuIsUnique($tenant, $data['sku']);

        return $this->transaction(function () use ($tenant, $data) {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->generateUniqueSlug($tenant, $data['name']),
                'sku' => Str::upper($data['sku']),
                'description' => $data['description'] ?? null,
                'brand' => $data['brand'] ?? null,
                'base_price' => $data['base_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'status' => $data['status'] ?? ProductStatus::Draft->value,
                'season' => $data['season'] ?? null,
                'publish_at' => $data['publish_at'] ?? null,
                'unpublish_at' => $data['unpublish_at'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            foreach ($data['variants'] as $variantData) {
                $this->createVariant($product, $variantData);
            }

            $this->syncTaxonomy($product, $data);

            return $product->refresh()->load(['variants', 'images']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Product $product, array $data, ?User $actor = null): Product
    {
        if (array_key_exists('sku', $data) && Str::upper($data['sku']) !== $product->sku) {
            $this->assertSkuIsUnique($product->tenant, $data['sku'], $product->id);
            $data['sku'] = Str::upper($data['sku']);
        }

        if (array_key_exists('name', $data) && $data['name'] !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug($product->tenant, $data['name'], $product->id);
        }

        // Re-publishing an archived product consumes a SKU slot again, the
        // same "reopening a closed branch" shape as StoreService::update().
        if (array_key_exists('status', $data) && $this->reactivatesSku($product, (string) $data['status'])) {
            $this->plans->assertWithinLimit($product->tenant, 'skus', $this->countTowardSkuLimit($product->tenant));
        }

        return $this->transaction(function () use ($product, $data, $actor) {
            if (array_key_exists('base_price', $data)) {
                $this->priceHistory->recordIfChanged(
                    $product,
                    null,
                    'base_price',
                    $product->base_price,
                    (string) $data['base_price'],
                    $actor,
                );
            }

            if (array_key_exists('sale_price', $data) && $data['sale_price'] !== null) {
                $this->priceHistory->recordIfChanged(
                    $product,
                    null,
                    'sale_price',
                    $product->sale_price,
                    (string) $data['sale_price'],
                    $actor,
                );
            }

            $product->fill($data)->save();

            if (array_key_exists('variants', $data)) {
                $this->diffVariants($product, $data['variants'], $actor);
            }

            $this->syncTaxonomy($product, $data);

            return $product->refresh()->load(['variants', 'images']);
        });
    }

    /**
     * Soft delete, plus removing every image's physical file — nothing
     * else will clean these up (the orphan sweeper Phase 5.C's checklist
     * describes does not exist yet), and unlike Store's branding assets, a
     * deleted product's images serve no purpose to keep against its now
     * hidden row. Variants are left to the model's own SoftDeletes cascade
     * expectation (they remain queryable via product_id for historical
     * order/price-history rows, same reasoning as Store's kiosk devices
     * surviving a soft-deleted branch).
     */
    public function delete(Product $product): void
    {
        $this->transaction(function () use ($product) {
            foreach ($product->images as $image) {
                $this->productImages->deleteFiles($image);
                $this->quota->decrement($product->tenant, $image->size_bytes);
                $image->delete();
            }

            $product->delete();
        });
    }

    /**
     * Clones a product as a new Draft — variants are copied with their
     * axis/price but zeroed stock (a duplicate is a new listing, not a
     * transfer of existing inventory) and fresh SKUs; images are copied to
     * new storage paths (never the same path two products' rows point at,
     * so deleting one product's images via delete() can never break the
     * other's).
     */
    public function duplicate(Product $product): Product
    {
        $this->plans->assertWithinLimit($product->tenant, 'skus', $this->countTowardSkuLimit($product->tenant));

        return $this->transaction(function () use ($product) {
            $copy = $product->replicate(['sku', 'slug', 'status', 'is_tryon_ready']);
            $copy->sku = $this->uniqueSkuFrom($product->tenant, $product->sku, 'products');
            $copy->slug = $this->generateUniqueSlug($product->tenant, $product->name . ' Copy');
            $copy->status = ProductStatus::Draft;
            $copy->is_tryon_ready = false;
            $copy->save();

            $variantIdMap = [];

            foreach ($product->variants as $variant) {
                $newVariant = ProductVariant::query()->create([
                    'tenant_id' => $copy->tenant_id,
                    'product_id' => $copy->id,
                    'sku' => $this->uniqueSkuFrom($product->tenant, $variant->sku, 'product_variants'),
                    'color_attr_id' => $variant->color_attr_id,
                    'size_attr_id' => $variant->size_attr_id,
                    'price' => $variant->price,
                    'stock' => 0,
                    'barcode' => null,
                    'status' => $variant->status,
                ]);

                $variantIdMap[$variant->id] = $newVariant->id;
            }

            foreach ($product->images as $image) {
                $newPath = $this->copyImageFile($image);

                // Duplicating a product duplicates its storage footprint
                // too — a real new file at a new path (see copyImageFile()'s
                // own docblock), so it counts against the quota exactly
                // like a fresh upload would. Derivatives are not copied;
                // ProcessProductImageJob regenerates them for the new row.
                ProductImage::query()->create([
                    'tenant_id' => $copy->tenant_id,
                    'product_id' => $copy->id,
                    'variant_id' => $image->variant_id ? ($variantIdMap[$image->variant_id] ?? null) : null,
                    'disk' => $image->disk,
                    'path' => $newPath,
                    'type' => $image->type,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                    'size_bytes' => $image->size_bytes,
                ]);

                $this->quota->increment($copy->tenant, $image->size_bytes);
            }

            $copy->occasions()->sync($product->occasions()->pluck('occasions.id')->all());
            $copy->tags()->sync($product->tags()->pluck('tags.id')->all());
            $copy->attributeValues()->sync($product->attributeValues()->pluck('attribute_values.id')->all());
            $copy->sizeCharts()->sync($product->sizeCharts()->pluck('size_charts.id')->all());

            return $copy->fresh(['variants', 'images']);
        });
    }

    public function setStatus(Product $product, ProductStatus $status): Product
    {
        if ($this->reactivatesSku($product, $status->value)) {
            $this->plans->assertWithinLimit($product->tenant, 'skus', $this->countTowardSkuLimit($product->tenant));
        }

        $product->status = $status;
        $product->save();

        return $product->refresh();
    }

    /**
     * @param list<int> $tagIds
     */
    public function syncTags(Product $product, array $tagIds): Product
    {
        $product->tags()->sync($tagIds);

        return $product->refresh()->load('tags');
    }

    /**
     * @param list<int> $occasionIds
     */
    public function syncOccasions(Product $product, array $occasionIds): Product
    {
        $product->occasions()->sync($occasionIds);

        return $product->refresh()->load('occasions');
    }

    /**
     * Products the plan's `skus` limit counts against —
     * ProductStatus::countsTowardSkuLimit().
     */
    public function countTowardSkuLimit(Tenant $tenant): int
    {
        return Product::query()->where('tenant_id', $tenant->id)->countingTowardSkuLimit()->count();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createVariant(Product $product, array $data): ProductVariant
    {
        $this->assertVariantSkuIsUnique($product->tenant, $data['sku']);
        $this->assertVariantAxisTypes(
            $product->tenant_id,
            $data['color_attr_id'] ?? null,
            $data['size_attr_id'] ?? null,
        );

        return ProductVariant::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'sku' => Str::upper($data['sku']),
            'color_attr_id' => $data['color_attr_id'] ?? null,
            'size_attr_id' => $data['size_attr_id'] ?? null,
            'price' => $data['price'] ?? null,
            'stock' => $data['stock'] ?? 0,
            'barcode' => $data['barcode'] ?? null,
            'status' => $data['status'] ?? ProductVariantStatus::Active->value,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $variantsPayload
     */
    private function diffVariants(Product $product, array $variantsPayload, ?User $actor): void
    {
        $existingIds = $product->variants()->pluck('id')->all();
        $keptIds = [];

        foreach ($variantsPayload as $variantData) {
            if (isset($variantData['id'])) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantData['id']);
                $this->updateVariant($product, $variant, $variantData, $actor);
                $keptIds[] = $variant->id;
            } else {
                $keptIds[] = $this->createVariant($product, $variantData)->id;
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);

        if ($removedIds !== []) {
            ProductVariant::query()->whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateVariant(Product $product, ProductVariant $variant, array $data, ?User $actor): void
    {
        if (isset($data['sku']) && Str::upper($data['sku']) !== $variant->sku) {
            $this->assertVariantSkuIsUnique($product->tenant, $data['sku'], $variant->id);
            $data['sku'] = Str::upper($data['sku']);
        }

        if (array_key_exists('color_attr_id', $data) || array_key_exists('size_attr_id', $data)) {
            $this->assertVariantAxisTypes(
                $product->tenant_id,
                array_key_exists('color_attr_id', $data) ? $data['color_attr_id'] : $variant->color_attr_id,
                array_key_exists('size_attr_id', $data) ? $data['size_attr_id'] : $variant->size_attr_id,
            );
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $this->priceHistory->recordIfChanged(
                $product,
                $variant,
                'price',
                $variant->price,
                (string) $data['price'],
                $actor,
            );
        }

        $variant->fill($data)->save();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function syncTaxonomy(Product $product, array $data): void
    {
        if (array_key_exists('occasion_ids', $data)) {
            $product->occasions()->sync($data['occasion_ids']);
        }

        if (array_key_exists('tag_ids', $data)) {
            $product->tags()->sync($data['tag_ids']);
        }

        if (array_key_exists('attribute_value_ids', $data)) {
            $product->attributeValues()->sync($data['attribute_value_ids']);
        }

        if (array_key_exists('size_chart_ids', $data)) {
            $product->sizeCharts()->sync($data['size_chart_ids']);
        }
    }

    private function reactivatesSku(Product $product, string $newStatus): bool
    {
        $wasCounting = $product->status->countsTowardSkuLimit();
        $willCount = ProductStatus::from($newStatus)->countsTowardSkuLimit();

        return !$wasCounting && $willCount;
    }

    /**
     * Confirms $colorAttrId/$sizeAttrId (already known to exist, per the
     * form request's Rule::exists()) actually belong to an attribute typed
     * Color/Size respectively — a check no static rule can express, since
     * it depends on the referenced row's own parent.
     */
    private function assertVariantAxisTypes(int $tenantId, ?int $colorAttrId, ?int $sizeAttrId): void
    {
        if ($colorAttrId !== null) {
            $this->assertAttributeValueType($tenantId, $colorAttrId, AttributeType::Color, 'color_attr_id');
        }

        if ($sizeAttrId !== null) {
            $this->assertAttributeValueType($tenantId, $sizeAttrId, AttributeType::Size, 'size_attr_id');
        }
    }

    private function assertAttributeValueType(int $tenantId, int $attributeValueId, AttributeType $expected, string $field): void
    {
        $value = AttributeValue::query()
            ->with('attribute')
            ->where('tenant_id', $tenantId)
            ->find($attributeValueId);

        if ($value === null || $value->attribute->type !== $expected) {
            throw ValidationException::withMessages([
                $field => ["The selected value is not a {$expected->label()} attribute value."],
            ]);
        }
    }

    private function assertSkuIsUnique(Tenant $tenant, string $sku, ?int $ignoreId = null): void
    {
        $query = Product::query()->where('tenant_id', $tenant->id)->where('sku', Str::upper($sku));

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->withTrashed()->exists()) {
            throw ValidationException::withMessages(['sku' => ['Another product already uses this SKU.']]);
        }
    }

    private function assertVariantSkuIsUnique(Tenant $tenant, string $sku, ?int $ignoreId = null): void
    {
        $query = ProductVariant::query()->where('tenant_id', $tenant->id)->where('sku', Str::upper($sku));

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->withTrashed()->exists()) {
            throw ValidationException::withMessages(['variants' => ['A variant with this SKU already exists.']]);
        }
    }

    private function generateUniqueSlug(Tenant $tenant, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($tenant, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(Tenant $tenant, string $slug, ?int $ignoreId): bool
    {
        $query = Product::query()->where('tenant_id', $tenant->id)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->withTrashed()->exists();
    }

    private function uniqueSkuFrom(Tenant $tenant, string $baseSku, string $table): string
    {
        do {
            $candidate = Str::upper($baseSku . '-COPY-' . Str::random(4));
        } while (
            DB::table($table)
                ->where('tenant_id', $tenant->id)
                ->where('sku', $candidate)
                ->exists()
        );

        return $candidate;
    }

    private function copyImageFile(ProductImage $image): string
    {
        $extension = pathinfo($image->path, PATHINFO_EXTENSION) ?: 'jpg';
        $newPath = self::IMAGE_DIRECTORY . '/' . Str::random(24) . '.' . $extension;

        Storage::disk($image->disk)->copy($image->path, $newPath);

        return $newPath;
    }
}
