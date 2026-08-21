<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stock ledger's HTTP surface: adjustment updates the tenant-wide
 * aggregate, transfer redistributes between branches without changing it,
 * and on-hand-per-branch is always derived from stock_movements, never a
 * cached column — StockService's own docblock explains the design.
 */
class StockServiceTest extends TestCase
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

    private function variantFor(Tenant $tenant): ProductVariant
    {
        $product = Product::factory()->inCategory(Category::factory()->create(['tenant_id' => $tenant->id]))->create();

        return ProductVariant::factory()->forProduct($product)->create(['stock' => 0]);
    }

    public function test_adjusting_stock_increases_the_aggregate_and_logs_a_movement(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
                'store_id' => $store->id,
                'quantity' => 25,
                'note' => 'Initial stock',
            ]);

        $response->assertCreated();
        $this->assertSame(25, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'variant_id' => $variant->id,
            'store_id' => $store->id,
            'type' => 'adjustment',
            'quantity' => 25,
            'user_id' => $owner->id,
        ]);
    }

    public function test_a_negative_adjustment_cannot_take_a_branch_below_zero_on_hand(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $store->id,
            'quantity' => 5,
        ])->assertCreated();

        $response = $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $store->id,
            'quantity' => -10,
        ]);

        $response->assertStatus(422);
    }

    public function test_transferring_stock_moves_it_between_branches_without_changing_the_total(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $storeA = Store::factory()->create(['tenant_id' => $tenant->id]);
        $storeB = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $storeA->id,
            'quantity' => 20,
        ])->assertCreated();

        $response = $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/transfer", [
            'from_store_id' => $storeA->id,
            'to_store_id' => $storeB->id,
            'quantity' => 8,
        ]);

        $response->assertCreated();
        // Total stayed the same — a transfer redistributes, it doesn't create or destroy.
        $this->assertSame(20, $variant->fresh()->stock);

        /** @var array<int, array{store_id: int, on_hand: int}> $breakdown */
        $breakdown = $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/variants/{$variant->id}/stock")
            ->assertOk()
            ->json('data.by_store');

        $byStoreId = collect($breakdown)->keyBy('store_id');
        $this->assertSame(12, $byStoreId[$storeA->id]['on_hand']);
        $this->assertSame(8, $byStoreId[$storeB->id]['on_hand']);
    }

    public function test_transferring_more_than_on_hand_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $storeA = Store::factory()->create(['tenant_id' => $tenant->id]);
        $storeB = Store::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/transfer", [
            'from_store_id' => $storeA->id,
            'to_store_id' => $storeB->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_low_stock_threshold_can_be_set_and_reflects_in_variant_status(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $variant->update(['stock' => 3]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/variants/{$variant->id}/low-stock-threshold", ['low_stock_threshold' => 5])
            ->assertOk();

        $this->assertTrue($variant->fresh()->isLowStock());
    }

    public function test_movement_history_is_paginated_and_ordered_newest_first(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $variant = $this->variantFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $store->id, 'quantity' => 10,
        ])->assertCreated();
        $this->withHeaders($this->bearer($owner))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $store->id, 'quantity' => 5,
        ])->assertCreated();

        $response = $this->withHeaders($this->bearer($owner))->getJson("/api/v1/variants/{$variant->id}/stock/movements");

        $response->assertOk();
        $response->assertJsonCount(2, 'data.movements');
        $response->assertJsonPath('data.movements.0.quantity', 5);
    }

    public function test_a_staff_member_cannot_adjust_stock(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');
        $variant = $this->variantFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($staff))->postJson("/api/v1/variants/{$variant->id}/stock/adjust", [
            'store_id' => $store->id,
            'quantity' => 5,
        ])->assertForbidden();
    }
}
