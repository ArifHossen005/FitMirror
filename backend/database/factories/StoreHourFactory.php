<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreHour>
 */
class StoreHourFactory extends Factory
{
    protected $model = StoreHour::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            // tenant_id is filled from the store in configure() below —
            // a StoreHour whose tenant differs from its store's would be
            // invisible to the branch that owns it once TenantScope ran.
            'day_of_week' => fake()->numberBetween(0, 6),
            'is_closed' => false,
            'opens_at' => '09:00:00',
            'closes_at' => '21:00:00',
            'kiosk_opens_at' => null,
            'kiosk_closes_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (StoreHour $hour) {
            if ($hour->tenant_id === null && $hour->store_id !== null) {
                $store = Store::withoutTenantScope()->find($hour->store_id);
                $hour->tenant_id = $store?->tenant_id;
            }
        });
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
            'kiosk_opens_at' => null,
            'kiosk_closes_at' => null,
        ]);
    }

    public function forDay(int $dayOfWeek): static
    {
        return $this->state(fn (array $attributes) => ['day_of_week' => $dayOfWeek]);
    }

    public function kioskWindow(string $opensAt, string $closesAt): static
    {
        return $this->state(fn (array $attributes) => [
            'kiosk_opens_at' => $opensAt,
            'kiosk_closes_at' => $closesAt,
        ]);
    }
}
