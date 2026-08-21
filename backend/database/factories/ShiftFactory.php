<?php

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'shift_date' => now()->addDay()->format('Y-m-d'),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'status' => ShiftStatus::Scheduled,
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Shift $shift) {
            if ($shift->tenant_id === null && $shift->store_id !== null) {
                $store = Store::withoutTenantScope()->find($shift->store_id);
                $shift->tenant_id = $store?->tenant_id;
            }
        });
    }

    public function on(string $date, string $startsAt = '09:00:00', string $endsAt = '17:00:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_date' => $date,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    /** 22:00 to 06:00 — ends "before" it starts, the wrapping case. */
    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => '22:00:00',
            'ends_at' => '06:00:00',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ShiftStatus::Cancelled]);
    }
}
