<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature_key' => fake()->randomElement(['campaign_manager', 'loyalty_program', 'social_media_post', 'analytics', 'custom_branding', 'api_access', 'sslcommerz_payment']),
            'enabled' => true,
            'meta' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function tier(string $tier): static
    {
        return $this->state(fn (array $attributes) => ['meta' => ['tier' => $tier]]);
    }
}
