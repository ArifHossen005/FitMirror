<?php

namespace Tests\Feature\Media;

use App\Enums\BackgroundRemovalStatus;
use App\Enums\ProductImageType;
use App\Exceptions\MediaProcessingException;
use App\Jobs\RemoveBackgroundJob;
use App\Mail\BackgroundRemovalFailedMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Media\BackgroundRemovalService;
use App\Services\Media\StorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Jobs\RemoveBackgroundJob end to end: success creates a `tryon`-type
 * ProductImage and flips `is_tryon_ready`; failure records the error and
 * sends BackgroundRemovalFailedMail (App\Jobs\NotifyBackgroundRemovalFailedJob).
 * The provider contract is asserted against config('background_removal')
 * via Http::fake() — see that config file's own docblock for why it
 * can't be verified against a real account yet (PROGRESS.md Decision D-18
 * precedent).
 *
 * The failure path calls handle()/failed() directly rather than through
 * RemoveBackgroundJob::dispatch() — Laravel's `sync` queue connection
 * (phpunit.xml) re-throws a job's exception to the dispatching caller
 * after invoking failed(), which would otherwise turn this test's HTTP
 * request into an unhandled 500 instead of exercising the real worker
 * behaviour (a queue driver never re-throws into the original request).
 */
class BackgroundRemovalTest extends TestCase
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

    private function galleryImageFor(Product $product): ProductImage
    {
        return ProductImage::factory()->forProduct($product)->create([
            'type' => ProductImageType::Gallery,
            'path' => 'products/' . $product->id . '/original.jpg',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'background_removal.endpoint' => 'https://bg-removal.test/v1/remove',
            'background_removal.key' => 'test-key',
        ]);
    }

    public function test_successful_removal_creates_a_tryon_image_and_marks_the_product_ready(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        $source = $this->galleryImageFor($product);
        Storage::disk('tenant')->put($source->path, 'fake-original-bytes');

        Http::fake(['bg-removal.test/*' => Http::response('fake-transparent-png-bytes', 200)]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/products/{$product->id}/images/{$source->id}/remove-background");

        $response->assertOk();

        $this->assertTrue($product->fresh()->is_tryon_ready);
        $this->assertSame(BackgroundRemovalStatus::Completed, $source->fresh()->background_removal_status);

        $tryon = $product->images()->where('type', ProductImageType::Tryon->value)->first();
        $this->assertNotNull($tryon);
        Storage::disk('tenant')->assertExists($tryon->path);
        $this->assertSame('fake-transparent-png-bytes', Storage::disk('tenant')->get($tryon->path));
    }

    public function test_a_failed_removal_records_the_error_and_notifies_the_owner(): void
    {
        Storage::fake('tenant');
        Mail::fake();

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');
        $tenant->update(['owner_id' => $owner->id]);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        $source = $this->galleryImageFor($product);
        Storage::disk('tenant')->put($source->path, 'fake-original-bytes');

        Http::fake(['bg-removal.test/*' => Http::response('provider error', 500)]);

        $job = new RemoveBackgroundJob($source->id);

        try {
            $job->handle(app(BackgroundRemovalService::class), app(StorageQuotaService::class));
            $this->fail('Expected MediaProcessingException.');
        } catch (MediaProcessingException $e) {
            $job->failed($e);
        }

        $source->refresh();
        $this->assertSame(BackgroundRemovalStatus::Failed, $source->background_removal_status);
        $this->assertNotNull($source->background_removal_error);
        $this->assertFalse($product->fresh()->is_tryon_ready);

        Mail::assertSent(BackgroundRemovalFailedMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_removal_is_rejected_when_the_provider_is_not_configured(): void
    {
        config(['background_removal.endpoint' => null, 'background_removal.key' => null]);
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        $source = $this->galleryImageFor($product);
        Storage::disk('tenant')->put($source->path, 'fake-original-bytes');

        $job = new RemoveBackgroundJob($source->id);

        $this->expectException(MediaProcessingException::class);
        $job->handle(app(BackgroundRemovalService::class), app(StorageQuotaService::class));
    }

    public function test_only_a_gallery_image_can_be_submitted_for_removal(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        $swatch = ProductImage::factory()->forProduct($product)->create(['type' => ProductImageType::Swatch]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/products/{$product->id}/images/{$swatch->id}/remove-background");

        $response->assertStatus(422);
    }

    public function test_owner_can_manually_upload_a_tryon_asset_replacing_any_existing_one(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        $old = ProductImage::factory()->forProduct($product)->create(['type' => ProductImageType::Tryon, 'variant_id' => null]);
        Storage::disk('tenant')->put($old->path, 'old-bytes');

        $response = $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/tryon-asset", [
            // ->image(), not ->create() — UploadTryonAssetRequest's `image`
            // rule runs getimagesize() on the upload, which only a real
            // GD-generated image (not a dummy-content ->create() file)
            // satisfies. The .png extension makes the factory encode it as
            // a real PNG.
            'image' => UploadedFile::fake()->image('corrected.png', 100, 100),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertTrue($product->fresh()->is_tryon_ready);
        $this->assertDatabaseMissing('product_images', ['id' => $old->id]);
        $this->assertSame(1, $product->images()->where('type', ProductImageType::Tryon->value)->count());
    }
}
