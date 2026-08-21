<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Not unique()->randomElement() — that exhausts FakerPHP's unique
        // pool almost immediately against a 5-item list once a test creates
        // more than a handful of products (e.g. filling a plan's SKU cap).
        // fake()->numerify('####') below already makes the full name
        // practically unique.
        $name = fake()->randomElement(['Cotton Panjabi', 'Silk Saree', 'Denim Jacket', 'Linen Shirt', 'Wedding Sherwani'])
            . ' ' . fake()->unique()->numerify('########');
        $basePrice = fake()->randomFloat(2, 500, 8000);

        return [
            // Explicit for the same reason StoreFactory's tenant_id is:
            // factories run with no ambient TenantContext.
            'tenant_id' => Tenant::factory(),
            'store_id' => null,
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => Str::upper(Str::random(10)),
            'description' => fake()->paragraph(),
            'brand' => fake()->company(),
            'base_price' => $basePrice,
            'sale_price' => null,
            'status' => ProductStatus::Draft,
            'is_tryon_ready' => false,
            'season' => fake()->randomElement(['Summer', 'Winter', 'Eid', 'Wedding', null]),
            'publish_at' => null,
            'unpublish_at' => null,
            'meta' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Published]);
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $category->tenant_id,
            'category_id' => $category->id,
        ]);
    }
}
