<?php

namespace Database\Factories;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => AddonType::Sms,
            'price' => 500,
            'currency' => 'BDT',
            'unit_amount' => 500,
            'status' => AddonStatus::Active,
            'sort_order' => 0,
        ];
    }

    public function status(AddonStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
