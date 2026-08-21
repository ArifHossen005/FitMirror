<?php

namespace Tests\Feature\Inventory;

use App\Mail\LowStockAlertMail;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * `php artisan inventory:check-low-stock` — one digest email per tenant
 * that has any variant at or below its threshold, per
 * App\Jobs\NotifyLowStockJob's own docblock.
 */
class LowStockDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_with_a_low_stock_variant_receives_one_digest_email(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');
        $tenant->update(['owner_id' => $owner->id]);

        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 1, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->forProduct($product)->create(['stock' => 1, 'low_stock_threshold' => 5]);

        $this->artisan('inventory:check-low-stock')->assertSuccessful();

        Mail::assertSent(LowStockAlertMail::class, 1);
        Mail::assertSent(LowStockAlertMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_a_tenant_with_no_low_stock_variants_receives_nothing(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->update(['owner_id' => $owner->id]);
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();
        ProductVariant::factory()->forProduct($product)->create(['stock' => 100, 'low_stock_threshold' => 5]);

        $this->artisan('inventory:check-low-stock')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
