<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category tree CRUD, the plan's `categories` cap (same shape as
 * StoreCrudTest's branch-limit tests), depth limiting, and cycle
 * prevention — the tree-walking rules that only CategoryService can check
 * since they depend on the whole tenant tree, not a single row.
 */
class CategoryCrudTest extends TestCase
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

    public function test_owner_can_create_a_root_category(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/categories', [
            'name' => 'Boys',
            'gender' => 'boys',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'boys');
        $response->assertJsonPath('data.parent_id', null);
        $this->assertDatabaseHas('categories', ['tenant_id' => $tenant->id, 'slug' => 'boys']);
    }

    public function test_a_child_category_can_be_created_under_a_parent(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $parent = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/categories', [
            'name' => 'Panjabi',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_category_names_are_slugged_uniquely_within_a_tenant(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Saree', 'slug' => 'saree']);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/categories', ['name' => 'Saree']);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'saree-2');
    }

    public function test_category_nesting_is_capped_at_the_max_depth(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $current = null;

        // Category::MAX_DEPTH is 4 — build a full chain at depths 0..3 (4
        // categories) so a 5th, at depth 4, is the one that gets rejected.
        for ($i = 0; $i < Category::MAX_DEPTH; $i++) {
            $current = Category::factory()
                ->when($current, fn ($f) => $f->childOf($current))
                ->create(['tenant_id' => $tenant->id]);
        }

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/categories', [
            'name' => 'Too Deep',
            'parent_id' => $current->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_id');
    }

    public function test_a_category_cannot_be_moved_under_its_own_descendant(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $parent = Category::factory()->create(['tenant_id' => $tenant->id]);
        $child = Category::factory()->childOf($parent)->create();

        $response = $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/categories/{$parent->id}", ['parent_id' => $child->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_id');
    }

    public function test_category_limit_is_enforced_against_the_plan(): void
    {
        // Free allows exactly 2 categories.
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        Category::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/categories', ['name' => 'Third']);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_limit_exceeded');
        $response->assertJsonPath('errors.limit', 'categories');
        $response->assertJsonPath('errors.max', 2);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $parent = Category::factory()->create(['tenant_id' => $tenant->id]);
        Category::factory()->childOf($parent)->create();

        $response = $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/categories/{$parent->id}");

        $response->assertStatus(422);
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);
        Product::factory()->inCategory($category)->create();

        $response = $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(422);
    }

    public function test_reordering_persists_new_sort_order_for_a_sibling_group(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $first = Category::factory()->create(['tenant_id' => $tenant->id, 'sort_order' => 0]);
        $second = Category::factory()->create(['tenant_id' => $tenant->id, 'sort_order' => 1]);

        $response = $this->withHeaders($this->bearer($owner))->patchJson('/api/v1/categories/reorder', [
            'order' => [
                ['id' => $first->id, 'sort_order' => 1],
                ['id' => $second->id, 'sort_order' => 0],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);
    }

    public function test_a_tenant_cannot_read_or_edit_another_tenants_category(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreign = Category::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->withHeaders($this->bearer($owner))->getJson("/api/v1/categories/{$foreign->id}")->assertNotFound();
        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/categories/{$foreign->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_a_staff_member_cannot_create_a_category(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $this->withHeaders($this->bearer($staff))
            ->postJson('/api/v1/categories', ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_index_returns_a_nested_tree(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $parent = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Boys']);
        Category::factory()->childOf($parent)->create(['name' => 'Panjabi']);

        $response = $this->withHeaders($this->bearer($owner))->getJson('/api/v1/categories');

        $response->assertOk();
        $response->assertJsonPath('data.categories.0.name', 'Boys');
        $response->assertJsonPath('data.categories.0.children.0.name', 'Panjabi');
    }
}
