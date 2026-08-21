<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(['Dhaka', 'Chattogram', 'Sylhet', 'Khulna', 'Rajshahi']);

        return [
            // Explicit rather than relying on BelongsToTenant's creating
            // hook: a factory usually runs outside any request, so there is
            // no ambient TenantContext for that hook to read (Decision
            // D-13's fail-closed rule applies to writes too).
            'tenant_id' => Tenant::factory(),
            'name' => $city . ' ' . fake()->randomElement(['Flagship', 'Outlet', 'Showroom', 'Store']),
            'code' => Str::upper(Str::random(6)),
            'is_main' => false,
            'phone' => '+8801' . fake()->numerify('#########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'area' => fake()->randomElement(['Gulshan', 'Banani', 'Dhanmondi', 'Uttara', 'Mirpur']),
            'lat' => fake()->latitude(20.5, 26.5),
            'lng' => fake()->longitude(88.0, 92.5),
            'map_url' => null,
            'socials' => null,
            'timezone' => 'Asia/Dhaka',
            'status' => StoreStatus::Active,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => ['is_main' => true]);
    }

    public function status(StoreStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
