<?php

namespace Tests\Feature\Inventory;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Services\Product\ProductScheduleService, run per tenant by
 * `php artisan products:apply-schedule` — publishes/unpublishes based on
 * publish_at/unpublish_at. Runs outside any request, so it must wrap its
 * work in TenantContext::runAs() (Decision D-26) or TenantScope's
 * fail-closed rule would silently update zero rows for every tenant.
 */
class ProductScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function categoryFor(Tenant $tenant): Category
    {
        return Category::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_a_draft_product_is_published_once_its_publish_at_has_passed(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()
            ->inCategory($this->categoryFor($tenant))
            ->status(ProductStatus::Draft)
            ->create(['publish_at' => now()->subMinute()]);

        $this->artisan('products:apply-schedule')->assertSuccessful();

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
    }

    public function test_a_published_product_is_unpublished_once_its_unpublish_at_has_passed(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()
            ->inCategory($this->categoryFor($tenant))
            ->status(ProductStatus::Published)
            ->create(['unpublish_at' => now()->subMinute()]);

        $this->artisan('products:apply-schedule')->assertSuccessful();

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_a_product_with_a_future_publish_at_is_left_alone(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()
            ->inCategory($this->categoryFor($tenant))
            ->status(ProductStatus::Draft)
            ->create(['publish_at' => now()->addDay()]);

        $this->artisan('products:apply-schedule')->assertSuccessful();

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_the_schedule_is_applied_across_every_tenant(): void
    {
        $tenantA = Tenant::factory()->onPlan('pro')->create();
        $tenantB = Tenant::factory()->onPlan('pro')->create();

        $productA = Product::factory()->inCategory($this->categoryFor($tenantA))
            ->status(ProductStatus::Draft)->create(['publish_at' => now()->subMinute()]);
        $productB = Product::factory()->inCategory($this->categoryFor($tenantB))
            ->status(ProductStatus::Draft)->create(['publish_at' => now()->subMinute()]);

        $this->artisan('products:apply-schedule')->assertSuccessful();

        $this->assertSame(ProductStatus::Published, $productA->fresh()->status);
        $this->assertSame(ProductStatus::Published, $productB->fresh()->status);
    }
}
