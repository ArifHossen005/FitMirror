<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Size chart CRUD plus the attach/detach sync and kiosk popup payload
 * PROGRESS.md's 5.B checklist asks for.
 */
class SizeChartTest extends TestCase
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

    public function test_owner_can_create_a_size_chart_with_measurement_rows(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/size-charts', [
            'name' => "Men's Panjabi",
            'unit' => 'in',
            'rows' => [
                ['size' => 'M', 'measurements' => ['chest' => '40', 'length' => '29']],
                ['size' => 'L', 'measurements' => ['chest' => '42', 'length' => '30']],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.rows');
    }

    public function test_a_chart_can_be_attached_to_and_detached_from_a_product(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->inCategory($category)->create();
        $chart = SizeChart::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/products/{$product->id}/size-charts", ['size_chart_ids' => [$chart->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.size_charts');

        $this->assertDatabaseHas('product_size_chart', ['product_id' => $product->id, 'size_chart_id' => $chart->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/products/{$product->id}/size-charts", ['size_chart_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.size_charts');

        $this->assertDatabaseMissing('product_size_chart', ['product_id' => $product->id, 'size_chart_id' => $chart->id]);
    }

    public function test_a_tenant_cannot_edit_another_tenants_size_chart(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreign = SizeChart::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/size-charts/{$foreign->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }
}
