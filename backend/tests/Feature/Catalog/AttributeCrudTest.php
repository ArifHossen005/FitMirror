<?php

namespace Tests\Feature\Catalog;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attribute + nested value CRUD, and the two rules that depend on a
 * loaded parent/relation rather than the request body alone: hex_color is
 * only accepted for a Color-type attribute, and a value already selected
 * by a live variant blocks its whole attribute from being deleted.
 */
class AttributeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    public function test_owner_can_create_a_color_attribute_with_values(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $attribute = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/attributes', ['name' => 'Color', 'type' => 'color'])
            ->assertCreated()
            ->json('data');

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/attributes/{$attribute['id']}/values", ['value' => 'Red', 'hex_color' => '#FF0000'])
            ->assertCreated()
            ->assertJsonPath('data.hex_color', '#FF0000');
    }

    public function test_hex_color_is_rejected_for_a_non_color_attribute(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $attribute = Attribute::factory()->type(AttributeType::Size)->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/attributes/{$attribute->id}/values", ['value' => 'XL', 'hex_color' => '#FFFFFF']);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.hex_color.0', 'Only Color attributes may have a hex color value.');
    }

    public function test_an_attribute_value_in_use_by_a_variant_blocks_deleting_its_attribute(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $attribute = Attribute::factory()->type(AttributeType::Color)->create(['tenant_id' => $tenant->id]);
        $value = AttributeValue::factory()->forAttribute($attribute)->create();
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'color_attr_id' => $value->id]);

        $response = $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/attributes/{$attribute->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
    }

    public function test_a_duplicate_value_within_the_same_attribute_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $attribute = Attribute::factory()->create(['tenant_id' => $tenant->id]);
        AttributeValue::factory()->forAttribute($attribute)->create(['value' => 'Medium']);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/attributes/{$attribute->id}/values", ['value' => 'Medium']);

        $response->assertStatus(422);
    }
}
