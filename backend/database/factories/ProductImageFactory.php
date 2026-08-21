<?php

namespace Database\Factories;

use App\Enums\ProductImageType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'disk' => 'tenant',
            'path' => 'products/' . Str::random(20) . '.jpg',
            'cdn_url' => null,
            'type' => ProductImageType::Gallery,
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
