<?php

namespace Database\Factories;

use App\Enums\StaffInvitationStatus;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffInvitation>
 */
class StaffInvitationFactory extends Factory
{
    protected $model = StaffInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $hash = StaffInvitation::hashToken(fake()->unique()->uuid());

        return [
            'tenant_id' => $tenant->id,
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => 'staff',
            'token_hash' => $hash,
            'invited_by' => User::factory()->for($tenant),
            'status' => StaffInvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function status(StaffInvitationStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
