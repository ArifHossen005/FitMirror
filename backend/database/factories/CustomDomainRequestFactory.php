<?php

namespace Database\Factories;

use App\Enums\CustomDomainStatus;
use App\Models\CustomDomainRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomDomainRequest>
 */
class CustomDomainRequestFactory extends Factory
{
    protected $model = CustomDomainRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'domain' => 'shop-' . fake()->unique()->numberBetween(1000, 999999) . '.example.com',
            'verification_token' => CustomDomainRequest::generateVerificationToken(),
            'status' => CustomDomainStatus::Pending,
            'attempts' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomDomainStatus::Verified,
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);
    }
}
