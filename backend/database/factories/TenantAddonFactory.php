<?php

namespace Database\Factories;

use App\Models\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantAddon>
 */
class TenantAddonFactory extends Factory
{
    protected $model = TenantAddon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'addon_id' => Addon::factory(),
            'invoice_id' => null,
            'remaining_balance' => 500,
            'purchased_at' => now(),
            'expires_at' => null,
        ];
    }

    public function balance(int $balance): static
    {
        return $this->state(fn (array $attributes) => ['remaining_balance' => $balance]);
    }

    public function purchasedAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes) => ['purchased_at' => $when]);
    }

    public function expiresAt(?\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => $when]);
    }
}
