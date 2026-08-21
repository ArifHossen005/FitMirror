<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'variant_id' => ProductVariant::factory(),
            'store_id' => Store::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => fake()->numberBetween(-10, 50),
            'reference' => null,
            'note' => null,
            'user_id' => null,
        ];
    }

    public function forVariantAtStore(ProductVariant $variant, Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $variant->tenant_id,
            'variant_id' => $variant->id,
            'store_id' => $store->id,
        ]);
    }
}
