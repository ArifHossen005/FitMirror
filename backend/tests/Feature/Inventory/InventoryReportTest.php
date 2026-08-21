<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportTest extends TestCase
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

    public function test_report_lists_low_stock_variants_and_leaves_dead_stock_explicitly_empty(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->inCategory($category)->create();

        ProductVariant::factory()->forProduct($product)->create(['stock' => 2, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->forProduct($product)->create(['stock' => 50, 'low_stock_threshold' => 5]);
        ProductVariant::factory()->forProduct($product)->create(['stock' => 0, 'low_stock_threshold' => null]);

        $response = $this->withHeaders($this->bearer($owner))->getJson('/api/v1/inventory/report');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.low_stock');
        $response->assertJsonPath('data.summary.low_stock_count', 1);
        $response->assertJsonPath('data.summary.out_of_stock_count', 1);
        $response->assertJsonPath('data.dead_stock', []);
        $this->assertNotEmpty($response->json('data.dead_stock_note'));
    }

    public function test_a_tenants_report_never_includes_another_tenants_variants(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $otherTenant = Tenant::factory()->onPlan('pro')->create();
        $otherProduct = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $otherTenant->id]))->create();
        ProductVariant::factory()->forProduct($otherProduct)->create(['stock' => 1, 'low_stock_threshold' => 10]);

        $response = $this->withHeaders($this->bearer($owner))->getJson('/api/v1/inventory/report');

        $response->assertOk();
        $response->assertJsonCount(0, 'data.low_stock');
    }
}
