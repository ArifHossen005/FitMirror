<?php

namespace Tests\Feature\Inventory;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "auto-hide out-of-stock products from try-on and catalog" mechanism
 * (Product::scopeVisibleInCatalog()) — no customer-facing endpoint
 * consumes it yet (Phase 6/9), so this tests the model-level primitive
 * directly rather than through an HTTP route. See the scope's own
 * docblock for why.
 *
 * Every assertion runs inside TenantContext::runAs($tenant, ...) — this
 * test never authenticates over HTTP, so there is no ResolveTenant
 * middleware to set an active TenantContext. Product::withoutTenantScope()
 * alone is not enough here: scopeVisibleInCatalog()'s whereHas('variants',
 * ...) builds a *nested* subquery against ProductVariant, which carries
 * its own independent TenantScope that bypassing the outer Product query
 * never touches — with no active context, that nested subquery still
 * fails closed to zero rows (Decision D-13), so "in stock" can never be
 * true no matter what the real data says. runAs() makes both the outer
 * query and the nested one resolve normally, the same as they would
 * inside a real authenticated request. The first version of this file
 * used withoutTenantScope() only, and three of its four assertFalse()
 * cases passed for the wrong reason — an empty result set is also "not
 * visible" — until the one assertTrue() case caught it.
 */
class VisibleInCatalogScopeTest extends TestCase
{
    use RefreshDatabase;

    private function categoryFor(Tenant $tenant): Category
    {
        return Category::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function isVisible(Tenant $tenant, Product $product): bool
    {
        return app(TenantContext::class)->runAs(
            $tenant,
            fn () => Product::query()->visibleInCatalog()->whereKey($product->id)->exists(),
        );
    }

    public function test_a_published_product_with_in_stock_active_variant_is_visible(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->published()->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 5, 'status' => ProductVariantStatus::Active]);

        $this->assertTrue($this->isVisible($tenant, $product));
    }

    public function test_a_product_with_every_variant_out_of_stock_is_hidden(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->published()->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 0, 'status' => ProductVariantStatus::Active]);

        $this->assertFalse($this->isVisible($tenant, $product));
    }

    public function test_a_draft_product_is_hidden_regardless_of_stock(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->status(ProductStatus::Draft)->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 50, 'status' => ProductVariantStatus::Active]);

        $this->assertFalse($this->isVisible($tenant, $product));
    }

    public function test_stock_on_an_inactive_variant_does_not_count(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->published()->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 50, 'status' => ProductVariantStatus::Inactive]);

        $this->assertFalse($this->isVisible($tenant, $product));
    }
}
