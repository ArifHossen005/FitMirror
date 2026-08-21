<?php

namespace Database\Factories;

use App\Enums\ProductVariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'sku' => Str::upper(Str::random(12)),
            'color_attr_id' => null,
            'size_attr_id' => null,
            'price' => null,
            'stock' => fake()->numberBetween(0, 100),
            'barcode' => fake()->ean13(),
            'status' => ProductVariantStatus::Active,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);
    }

    public function stock(int $stock): static
    {
        return $this->state(fn (array $attributes) => ['stock' => $stock]);
    }
}
