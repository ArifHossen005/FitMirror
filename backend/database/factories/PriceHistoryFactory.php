<?php

namespace Database\Factories;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    protected $model = PriceHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'field' => 'base_price',
            'old_value' => fake()->randomFloat(2, 500, 1000),
            'new_value' => fake()->randomFloat(2, 500, 1000),
            'user_id' => null,
            'created_at' => now(),
        ];
    }
}
