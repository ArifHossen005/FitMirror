<?php

namespace Database\Factories;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SAVE-####')),
            'type' => CouponType::Percentage,
            'value' => 10,
            'applies_to_plans' => null,
            'max_redemptions' => null,
            'per_tenant_limit' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'status' => CouponStatus::Active,
        ];
    }

    public function type(CouponType $type, int $value): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type, 'value' => $value]);
    }

    public function status(CouponStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    /**
     * @param list<string> $slugs
     */
    public function appliesTo(array $slugs): static
    {
        return $this->state(fn (array $attributes) => ['applies_to_plans' => $slugs]);
    }

    public function maxRedemptions(int $max): static
    {
        return $this->state(fn (array $attributes) => ['max_redemptions' => $max]);
    }

    public function perTenantLimit(?int $limit): static
    {
        return $this->state(fn (array $attributes) => ['per_tenant_limit' => $limit]);
    }
}
