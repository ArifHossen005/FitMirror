<?php

namespace Database\Factories;

use App\Enums\OccasionStatus;
use App\Models\Occasion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Occasion>
 */
class OccasionFactory extends Factory
{
    protected $model = Occasion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Wedding', 'Eid', 'Office', 'Casual', 'Party', 'Puja', 'Pohela Boishakh']);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => null,
            'sort_order' => 0,
            'status' => OccasionStatus::Active,
        ];
    }
}
