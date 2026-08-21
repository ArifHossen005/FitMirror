<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use App\Services\Media\OrphanedMediaSweeper;
use App\Support\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrphanedMediaSweeperTest extends TestCase
{
    use RefreshDatabase;

    public function test_sweep_deletes_only_files_no_product_images_row_references(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();

        $referenced = ProductImage::factory()->forProduct($product)->create([
            'path' => TenantStorage::path($tenant, 'products/' . $product->id . '/kept.jpg'),
        ]);
        Storage::disk('tenant')->put($referenced->path, 'kept');

        $orphanPath = TenantStorage::path($tenant, 'products/' . $product->id . '/orphan.jpg');
        Storage::disk('tenant')->put($orphanPath, 'orphaned');

        $deleted = app(OrphanedMediaSweeper::class)->sweep($tenant);

        $this->assertSame(1, $deleted);
        Storage::disk('tenant')->assertExists($referenced->path);
        Storage::disk('tenant')->assertMissing($orphanPath);
    }

    public function test_sweep_never_touches_another_tenants_files(): void
    {
        Storage::fake('tenant');

        $tenantA = Tenant::factory()->onPlan('pro')->create();
        $tenantB = Tenant::factory()->onPlan('pro')->create();

        $pathB = TenantStorage::path($tenantB, 'products/999/photo.jpg');
        Storage::disk('tenant')->put($pathB, 'tenant-b-file');

        app(OrphanedMediaSweeper::class)->sweep($tenantA);

        Storage::disk('tenant')->assertExists($pathB);
    }

    public function test_command_sweeps_every_tenant_when_none_specified(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $orphanPath = TenantStorage::path($tenant, 'products/1/orphan.jpg');
        Storage::disk('tenant')->put($orphanPath, 'orphaned');

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        Storage::disk('tenant')->assertMissing($orphanPath);
    }
}
