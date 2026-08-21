<?php

namespace Database\Factories;

use App\Enums\AttributeStatus;
use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AttributeType::cases());
        $name = ucfirst($type->value) . ' ' . fake()->numerify('##');

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => $type,
            'sort_order' => 0,
            'status' => AttributeStatus::Active,
        ];
    }

    public function type(AttributeType $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }
}
