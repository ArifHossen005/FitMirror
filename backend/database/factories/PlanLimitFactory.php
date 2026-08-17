<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanLimit>
 */
class PlanLimitFactory extends Factory
{
    protected $model = PlanLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'key' => fake()->randomElement(['try_on_sessions_per_day', 'categories', 'skus', 'staff_accounts', 'storage_gb', 'branches']),
            'value' => fake()->numberBetween(1, 100),
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => ['value' => null]);
    }
}
