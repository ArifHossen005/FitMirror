<?php

namespace Database\Factories;

use App\Models\FranchiseGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FranchiseGroup>
 */
class FranchiseGroupFactory extends Factory
{
    protected $model = FranchiseGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company() . ' Group';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
        ];
    }
}
