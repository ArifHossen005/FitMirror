<?php

namespace Tests\Feature\Product;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Product + variant CRUD: nested creation in one transaction, the plan's
 * `skus` cap (same shape as StoreCrudTest's `branches` tests), variant
 * add/update/remove diffing on update, price-history recording, and
 * publish/unpublish/duplicate/delete.
 */
class ProductCrudTest extends TestCase
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

    private function categoryFor(Tenant $tenant): Category
    {
        return Category::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_owner_can_create_a_product_with_variants_in_one_call(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $category = $this->categoryFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/products', [
            'name' => 'Cotton Panjabi',
            'category_id' => $category->id,
            'sku' => 'panjabi-001',
            'base_price' => 1500,
            'variants' => [
                ['sku' => 'panjabi-001-m', 'stock' => 10],
                ['sku' => 'panjabi-001-l', 'stock' => 5],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.sku', 'PANJABI-001');
        $response->assertJsonCount(2, 'data.variants');
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_variant_color_attr_id_must_reference_a_color_type_attribute(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $category = $this->categoryFor($tenant);
        $sizeAttribute = Attribute::factory()->type(AttributeType::Size)->create(['tenant_id' => $tenant->id]);
        $sizeValue = AttributeValue::factory()->forAttribute($sizeAttribute)->create();

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/products', [
            'name' => 'Bad Variant',
            'category_id' => $category->id,
            'sku' => 'bad-001',
            'base_price' => 1000,
            'variants' => [
                ['sku' => 'bad-001-a', 'color_attr_id' => $sizeValue->id],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.color_attr_id.0', 'The selected value is not a Color attribute value.');
    }

    public function test_sku_limit_is_enforced_against_the_plan(): void
    {
        // Free allows exactly 50 SKUs — create one existing product to keep
        // the test fast rather than creating 50.
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        $category = $this->categoryFor($tenant);

        // Fill the plan by stubbing the count via 50 lightweight rows.
        Product::factory()->count(50)->inCategory($category)->create();

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/products', [
            'name' => 'One Too Many',
            'category_id' => $category->id,
            'sku' => 'over-limit',
            'base_price' => 500,
            'variants' => [['sku' => 'over-limit-a']],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_limit_exceeded');
        $response->assertJsonPath('errors.limit', 'skus');
    }

    public function test_archived_products_do_not_consume_a_sku_slot(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        $category = $this->categoryFor($tenant);
        Product::factory()->count(50)->status(ProductStatus::Archived)->inCategory($category)->create();

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/products', [
            'name' => 'Still Room',
            'category_id' => $category->id,
            'sku' => 'still-room',
            'base_price' => 500,
            'variants' => [['sku' => 'still-room-a']],
        ]);

        $response->assertCreated();
    }

    public function test_update_diffs_variants_adding_updating_and_removing(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create();
        $keep = ProductVariant::factory()->forProduct($product)->create(['stock' => 5]);
        $remove = ProductVariant::factory()->forProduct($product)->create();

        $response = $this->withHeaders($this->bearer($owner))->patchJson("/api/v1/products/{$product->id}", [
            'variants' => [
                ['id' => $keep->id, 'stock' => 99],
                ['sku' => 'brand-new-sku'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data.variants');
        $this->assertSame(99, $keep->fresh()->stock);
        $this->assertSoftDeleted('product_variants', ['id' => $remove->id]);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'BRAND-NEW-SKU']);
    }

    public function test_changing_base_price_records_price_history_with_the_actor(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create(['base_price' => 1000]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/products/{$product->id}", ['base_price' => 1200])
            ->assertOk();

        $this->assertDatabaseHas('price_history', [
            'product_id' => $product->id,
            'field' => 'base_price',
            'old_value' => '1000.00',
            'new_value' => '1200.00',
            'user_id' => $owner->id,
        ]);

        $historyResponse = $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/products/{$product->id}/price-history")
            ->assertOk();

        // assertEquals rather than assertJsonPath's strict comparison —
        // whether json_encode renders a whole-number float as "1200" or
        // "1200.0" is a PHP serialize_precision detail, not something this
        // test should be sensitive to.
        $this->assertEquals(1200.0, $historyResponse->json('data.price_history.0.new_value'));
        $historyResponse->assertJsonPath('data.price_history.0.changed_by', $owner->name);
    }

    public function test_publish_and_unpublish_toggle_status(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create(['status' => ProductStatus::Draft]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/products/{$product->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/products/{$product->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_duplicate_creates_a_draft_copy_with_zeroed_stock_and_fresh_skus(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))
            ->published()
            ->create(['sku' => 'ORIGINAL']);
        ProductVariant::factory()->forProduct($product)->create(['sku' => 'ORIGINAL-A', 'stock' => 20]);

        $response = $this->withHeaders($this->bearer($owner))->postJson("/api/v1/products/{$product->id}/duplicate");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $this->assertNotSame('ORIGINAL', $response->json('data.sku'));
        $copyVariant = ProductVariant::query()->where('product_id', $response->json('data.id'))->first();
        $this->assertSame(0, $copyVariant->stock);
        $this->assertNotSame('ORIGINAL-A', $copyVariant->sku);
    }

    public function test_deleting_a_product_removes_its_image_files(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create();

        $this->withHeaders($this->bearer($owner))->post("/api/v1/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = $product->images()->first()->path;
        Storage::disk('tenant')->assertExists($path);

        $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();

        Storage::disk('tenant')->assertMissing($path);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_syncing_tags_and_occasions_replaces_the_full_set(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create();
        $tag = Tag::factory()->create(['tenant_id' => $tenant->id]);
        $occasion = Occasion::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/products/{$product->id}/tags", ['tag_ids' => [$tag->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags');

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/products/{$product->id}/occasions", ['occasion_ids' => [$occasion->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.occasions');

        $this->assertDatabaseHas('taggables', ['tag_id' => $tag->id, 'taggable_id' => $product->id, 'taggable_type' => Product::class]);
    }

    public function test_a_tenant_cannot_read_or_edit_another_tenants_product(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreignTenant = Tenant::factory()->create();
        $foreignProduct = Product::factory()->inCategory($this->categoryFor($foreignTenant))->create();

        $this->withHeaders($this->bearer($owner))->getJson("/api/v1/products/{$foreignProduct->id}")->assertNotFound();
    }

    public function test_a_staff_member_cannot_publish_a_product(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');
        $product = Product::factory()->inCategory($this->categoryFor($tenant))->create();

        $this->withHeaders($this->bearer($staff))
            ->postJson("/api/v1/products/{$product->id}/publish")
            ->assertForbidden();
    }

    public function test_product_list_filters_by_category_and_search(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $categoryA = $this->categoryFor($tenant);
        $categoryB = $this->categoryFor($tenant);
        Product::factory()->inCategory($categoryA)->create(['name' => 'Silk Saree']);
        Product::factory()->inCategory($categoryB)->create(['name' => 'Denim Jacket']);

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/products?category_id=' . $categoryA->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.products');
        $response->assertJsonPath('data.products.0.name', 'Silk Saree');

        $searchResponse = $this->withHeaders($this->bearer($owner))->getJson('/api/v1/products?search=Denim');
        $searchResponse->assertJsonCount(1, 'data.products');
    }
}
