<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Media\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload-time size tracking, the plan's `storage_gb` quota, and the async
 * WebP-derivative pipeline (App\Jobs\ProcessProductImageJob) — the
 * PROGRESS.md 5.C items layered on top of Phase 5.B's plain image upload.
 * QUEUE_CONNECTION=sync in testing (phpunit.xml), so a dispatched job runs
 * inline and its result is asserted directly rather than via Queue::fake().
 */
class MediaProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    private function productFor(Tenant $tenant): Product
    {
        return Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
    }

    public function test_uploading_an_image_records_its_size_and_increments_tenant_storage_usage(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = $this->productFor($tenant);

        $file = UploadedFile::fake()->image('front.jpg', 200, 200)->size(500);

        $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/images", [
            'images' => [$file],
        ], ['Accept' => 'application/json'])->assertCreated();

        $image = $product->images()->first();
        $this->assertGreaterThan(0, $image->size_bytes);
        $this->assertSame($image->size_bytes, $tenant->fresh()->storage_bytes_used);
    }

    public function test_upload_is_rejected_when_it_would_exceed_the_storage_quota(): void
    {
        Storage::fake('tenant');

        // Free plan: 5 GB.
        $tenant = Tenant::factory()->onPlan('free')->create(['storage_bytes_used' => 5 * 1_073_741_824 - 100]);
        $owner = $this->ownerFor($tenant);
        $product = $this->productFor($tenant);

        $file = UploadedFile::fake()->image('front.jpg', 200, 200)->size(1024);

        $response = $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/images", [
            'images' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('images');
        $this->assertSame(0, $product->images()->count());
    }

    public function test_deleting_an_image_decrements_tenant_storage_usage(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = $this->productFor($tenant);

        $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg', 200, 200)->size(500)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $image = $product->images()->first();
        $this->assertGreaterThan(0, $tenant->fresh()->storage_bytes_used);

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/products/{$product->id}/images/{$image->id}")
            ->assertNoContent();

        $this->assertSame(0, $tenant->fresh()->storage_bytes_used);
    }

    public function test_uploading_generates_sm_md_lg_webp_derivatives(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = $this->productFor($tenant);

        $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg', 1200, 1200)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $image = $product->images()->first()->fresh();

        $this->assertNotNull($image->derivatives);
        $this->assertArrayHasKey('sm', $image->derivatives);
        $this->assertArrayHasKey('md', $image->derivatives);
        $this->assertArrayHasKey('lg', $image->derivatives);

        foreach ($image->derivatives as $path) {
            Storage::disk('tenant')->assertExists($path);
            $this->assertStringEndsWith('.webp', $path);
        }
    }

    public function test_recalculate_command_corrects_a_drifted_running_total(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = $this->productFor($tenant);
        ProductImage::factory()->forProduct($product)->create(['size_bytes' => 12345]);

        // Simulate drift: the running total disagrees with the real rows.
        $tenant->update(['storage_bytes_used' => 999]);

        $this->artisan('storage:recalculate', ['tenant' => $tenant->id])->assertSuccessful();

        $this->assertSame(12345, $tenant->fresh()->storage_bytes_used);
    }

    public function test_recalculate_service_matches_the_real_sum_directly(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $product = $this->productFor($tenant);
        ProductImage::factory()->forProduct($product)->create(['size_bytes' => 500]);
        ProductImage::factory()->forProduct($product)->create(['size_bytes' => 700]);

        $total = app(StorageQuotaService::class)->recalculate($tenant);

        $this->assertSame(1200, $total);
    }
}
