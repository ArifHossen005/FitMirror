<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999);

        return [
            'name' => $name,
            'slug' => $slug,
            'subdomain' => $slug,
            'status' => TenantStatus::Active,
            'settings' => [],
        ];
    }

    public function status(TenantStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
