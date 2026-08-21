<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Enums\ProductStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\SyncProductOccasionsRequest;
use App\Http\Requests\Product\SyncProductTagsRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Plan\PlanService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product CRUD, the plan's `skus` headroom (same shape as StoreController's
 * `branch_limit`), and the filtered list PROGRESS.md's 5.B checklist asks
 * for. `search` here is a plain `LIKE` on name/sku — real typo-tolerant,
 * faceted search is Phase 5.E's MeiliSearch endpoint; this list stays a
 * straightforward Eloquent query, matching BaseService's own "simple reads
 * go straight through Eloquent in the controller" convention.
 */
class ProductController extends BaseApiController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $tenant = $request->user()->tenant;

        $query = Product::query()
            ->with(['category:id,name,slug'])
            ->with(['images' => fn ($q) => $q->where('is_primary', true)])
            ->withCount('variants')
            ->withSum('variants as total_stock', 'stock');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('tag_id')) {
            $tagId = (int) $request->query('tag_id');
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        if ($request->filled('occasion_id')) {
            $occasionId = (int) $request->query('occasion_id');
            $query->whereHas('occasions', fn ($q) => $q->where('occasions.id', $occasionId));
        }

        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', (float) $request->query('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        $products = $query->orderByDesc('created_at')->paginate($this->perPage($request));
        $used = $this->products->countTowardSkuLimit($tenant);
        $limit = $this->plans->limit($tenant, 'skus');

        return $this->success([
            'products' => $products->through(fn (Product $product) => $this->presentSummary($product))->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
            'sku_limit' => [
                'used' => $used,
                'max' => $limit,
                'can_create_more' => $limit === null || $used < $limit,
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->products->create($request->user()->tenant, $request->validated());

        return $this->created($this->presentDetail($product), 'Product created successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success($this->presentDetail($this->loadDetail($product)));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updated = $this->products->update($product, $request->validated(), $request->user());

        return $this->success($this->presentDetail($updated), 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return $this->noContent();
    }

    public function duplicate(Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $copy = $this->products->duplicate($product);

        return $this->created($this->presentDetail($copy), 'Product duplicated successfully.');
    }

    public function publish(Product $product): JsonResponse
    {
        $this->authorize('publish', $product);

        $updated = $this->products->setStatus($product, ProductStatus::Published);

        return $this->success($this->presentDetail($updated), 'Product published successfully.');
    }

    public function unpublish(Product $product): JsonResponse
    {
        $this->authorize('publish', $product);

        $updated = $this->products->setStatus($product, ProductStatus::Draft);

        return $this->success($this->presentDetail($updated), 'Product unpublished successfully.');
    }

    public function syncTags(SyncProductTagsRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updated = $this->products->syncTags($product, $request->validated()['tag_ids']);

        return $this->success(['tags' => $updated->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])]);
    }

    public function syncOccasions(SyncProductOccasionsRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updated = $this->products->syncOccasions($product, $request->validated()['occasion_ids']);

        return $this->success(['occasions' => $updated->occasions->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])]);
    }

    private function loadDetail(Product $product): Product
    {
        return $product->load(['category:id,name,slug', 'variants.color', 'variants.size', 'images', 'occasions', 'tags', 'sizeCharts']);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSummary(Product $product): array
    {
        // index() eager-loads only the primary image, so first() is correct
        // there; presentDetail() eager-loads the full gallery instead, so
        // this falls back to searching it for the flagged one.
        $primaryImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
            'base_price' => (float) $product->base_price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'effective_price' => (float) $product->effectivePrice(),
            'status' => $product->status->value,
            'status_label' => $product->status->label(),
            'is_tryon_ready' => $product->is_tryon_ready,
            'variant_count' => $product->variants_count ?? 0,
            'total_stock' => (int) ($product->total_stock ?? 0),
            'primary_image_url' => $primaryImage instanceof ProductImage ? $primaryImage->url() : null,
            'created_at' => $product->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(Product $product): array
    {
        return [
            ...$this->presentSummary($product->loadMissing(['category:id,name,slug', 'images'])),
            'description' => $product->description,
            'brand' => $product->brand,
            'season' => $product->season,
            'publish_at' => $product->publish_at?->toIso8601String(),
            'unpublish_at' => $product->unpublish_at?->toIso8601String(),
            'meta' => $product->meta ?? [],
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'color' => $variant->relationLoaded('color') ? $variant->color?->only(['id', 'value', 'hex_color']) : null,
                    'size' => $variant->relationLoaded('size') ? $variant->size?->only(['id', 'value']) : null,
                    'price' => $variant->price !== null ? (float) $variant->price : null,
                    'effective_price' => (float) $variant->effectivePrice(),
                    'stock' => $variant->stock,
                    'barcode' => $variant->barcode,
                    'status' => $variant->status->value,
                ])->values()
                : [],
            'images' => $product->relationLoaded('images')
                ? $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'variant_id' => $image->variant_id,
                    'url' => $image->url(),
                    'type' => $image->type->value,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ])->values()
                : [],
            'occasions' => $product->relationLoaded('occasions')
                ? $product->occasions->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values()
                : [],
            'tags' => $product->relationLoaded('tags')
                ? $product->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
            'size_charts' => $product->relationLoaded('sizeCharts')
                ? $product->sizeCharts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()
                : [],
        ];
    }
}
