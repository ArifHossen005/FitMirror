<?php

namespace Tests\Feature\Catalog;

use App\Models\Occasion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Occasion and Tag CRUD — both are flat, tenant-scoped taxonomies with an
 * identical shape (slugged name, no plan limit), so one test file covers
 * both rather than duplicating the same assertions twice.
 */
class TaxonomyCrudTest extends TestCase
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

    public function test_owner_can_create_and_list_occasions(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/occasions', ['name' => 'Eid'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'eid');

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/occasions')
            ->assertOk()
            ->assertJsonPath('data.occasions.0.name', 'Eid');
    }

    public function test_deleting_an_occasion_detaches_it_from_every_product(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $occasion = Occasion::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/occasions/{$occasion->id}")->assertNoContent();

        $this->assertSoftDeleted('occasions', ['id' => $occasion->id]);
    }

    public function test_owner_can_create_and_list_tags(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tags', ['name' => 'Bestseller', 'color' => '#00FF00'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'bestseller');

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('data.tags.0.color', '#00FF00');
    }

    public function test_an_invalid_hex_color_is_rejected_for_a_tag(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tags', ['name' => 'Sale', 'color' => 'not-a-color'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('color');
    }

    public function test_a_tenant_cannot_edit_another_tenants_tag(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreign = Tag::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/tags/{$foreign->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }
}
