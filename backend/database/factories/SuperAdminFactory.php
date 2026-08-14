<?php

namespace Database\Factories;

use App\Enums\SuperAdminRole;
use App\Enums\SuperAdminStatus;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<SuperAdmin>
 */
class SuperAdminFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => SuperAdminRole::SuperAdmin,
            'status' => SuperAdminStatus::Active,
        ];
    }

    public function role(SuperAdminRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SuperAdminStatus::Suspended]);
    }
}
