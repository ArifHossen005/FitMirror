<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'attribute_id' => Attribute::factory(),
            'value' => fake()->unique()->word(),
            'hex_color' => null,
            'sort_order' => 0,
        ];
    }

    public function forAttribute(Attribute $attribute): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $attribute->tenant_id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function color(string $value, string $hex): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
            'hex_color' => $hex,
        ]);
    }
}
