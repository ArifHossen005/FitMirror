<?php

namespace Database\Factories;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Panjabi', 'Shirt', 'T-Shirt', 'Pant', 'Coat', 'Jacket',
            'Saree', 'Threepiece', 'Kurti', 'Orna', 'Frock', 'Lehenga',
        ]) . ' ' . fake()->numerify('##');

        return [
            // Explicit, same reasoning as StoreFactory: no ambient
            // TenantContext exists when a factory runs outside a request.
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => null,
            'image' => null,
            'gender' => fake()->randomElement(CategoryGender::cases()),
            'sort_order' => 0,
            'status' => CategoryStatus::Active,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $parent->tenant_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function status(CategoryStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
