<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'enabled' => false,
            'description' => fake()->sentence(),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => true]);
    }
}
