<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'price_monthly' => fake()->numberBetween(0, 2000),
            'price_yearly' => fake()->numberBetween(0, 20000),
            'currency' => 'BDT',
            'trial_days' => 0,
            'is_public' => true,
            'sort_order' => 0,
            'status' => PlanStatus::Active,
        ];
    }

    public function status(PlanStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
