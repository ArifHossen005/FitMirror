<?php

namespace Tests\Feature\Store;

use App\Enums\StoreStatus;
use App\Models\KioskDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Branch CRUD and, most importantly, the plan's `branches` cap — the one
 * rule here that depends on the tenant's other rows rather than on the
 * request alone. Free allows 1 branch, Pro 3, Max unlimited (PlanSeeder).
 */
class StoreCrudTest extends TestCase
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

    public function test_owner_can_create_a_branch(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/stores', [
            'name' => 'Gulshan Flagship',
            'code' => 'dhk-01',
            'city' => 'Dhaka',
            'socials' => ['facebook' => 'https://facebook.com/fitmirror'],
        ]);

        $response->assertCreated();
        // Codes are upper-cased on the way in so "dhk-01" and "DHK-01"
        // cannot both exist for one tenant.
        $response->assertJsonPath('data.code', 'DHK-01');
        // The first branch a tenant creates is always the main one.
        $response->assertJsonPath('data.is_main', true);
        $this->assertDatabaseHas('stores', ['tenant_id' => $tenant->id, 'code' => 'DHK-01', 'is_main' => true]);
    }

    public function test_second_branch_is_not_automatically_main(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->main()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/stores', [
            'name' => 'Banani Outlet',
            'code' => 'DHK-02',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_main', false);
    }

    public function test_promoting_a_branch_to_main_demotes_the_previous_one(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $first = Store::factory()->main()->create(['tenant_id' => $tenant->id]);
        $second = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/stores/{$second->id}", ['is_main' => true])
            ->assertOk()
            ->assertJsonPath('data.is_main', true);

        $this->assertFalse($first->fresh()->is_main);
    }

    public function test_branch_limit_is_enforced_against_the_plan(): void
    {
        // Free allows exactly one branch.
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->main()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/stores', [
            'name' => 'Second Branch',
            'code' => 'TWO',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_limit_exceeded');
        $response->assertJsonPath('errors.limit', 'branches');
        $response->assertJsonPath('errors.max', 1);
        $this->assertSame(1, Store::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_permanently_closed_branch_does_not_consume_a_plan_slot(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->main()->status(StoreStatus::Closed)->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/stores', ['name' => 'Relocated Shop', 'code' => 'NEW'])
            ->assertCreated();
    }

    public function test_reopening_a_closed_branch_is_blocked_when_it_would_exceed_the_plan(): void
    {
        $tenant = Tenant::factory()->onPlan('free')->create();
        $owner = $this->ownerFor($tenant);
        $closed = Store::factory()->status(StoreStatus::Closed)->create(['tenant_id' => $tenant->id]);
        Store::factory()->main()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/stores/{$closed->id}", ['status' => StoreStatus::Active->value]);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_limit_exceeded');
    }

    public function test_max_plan_has_no_branch_limit(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->count(6)->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/stores', ['name' => 'Seventh', 'code' => 'SEVEN'])
            ->assertCreated();

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonPath('data.branch_limit.max', null)
            ->assertJsonPath('data.branch_limit.can_create_more', true);
    }

    public function test_branch_code_must_be_unique_within_the_tenant_but_not_across_tenants(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->create(['tenant_id' => $tenant->id, 'code' => 'MAIN']);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/stores', ['name' => 'Duplicate', 'code' => 'main'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Another branch already uses this code.');

        // A different tenant using the same code is expected, not a conflict.
        $otherTenant = Tenant::factory()->onPlan('pro')->create();
        $otherOwner = $this->ownerFor($otherTenant);

        $this->withHeaders($this->bearer($otherOwner))
            ->postJson('/api/v1/stores', ['name' => 'Their Main', 'code' => 'MAIN'])
            ->assertCreated();
    }

    public function test_a_tenant_cannot_read_or_edit_another_tenants_branch(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreignStore = Store::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        // TenantScope makes the row invisible to route-model binding, so this
        // is a 404 rather than a 403 — a cross-tenant id must not be
        // distinguishable from an id that never existed.
        $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/stores/{$foreignStore->id}")
            ->assertNotFound();

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/stores/{$foreignStore->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_a_staff_member_cannot_create_a_branch(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $this->withHeaders($this->bearer($staff))
            ->postJson('/api/v1/stores', ['name' => 'Nope', 'code' => 'NOPE'])
            ->assertForbidden();
    }

    public function test_the_main_branch_cannot_be_deleted_while_others_exist(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $main = Store::factory()->main()->create(['tenant_id' => $tenant->id]);
        Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/stores/{$main->id}")
            ->assertStatus(422);

        $this->assertNull($main->fresh()->deleted_at);
    }

    public function test_deleting_a_branch_revokes_its_kiosk_device_tokens(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->paired()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/stores/{$store->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertNull($device->fresh()->device_token_hash);
    }

    public function test_branding_upload_stores_the_image_on_the_tenant_disk(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->post("/api/v1/stores/{$store->id}/branding", [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ], ['Accept' => 'application/json']);

        $response->assertOk();

        $logoPath = $store->fresh()->logo;
        $this->assertNotNull($logoPath);
        $this->assertStringStartsWith("tenants/{$tenant->id}/stores/{$store->id}/", $logoPath);
        Storage::disk('tenant')->assertExists($logoPath);
    }

    public function test_replacing_a_logo_deletes_the_file_it_supersedes(): void
    {
        Storage::fake('tenant');

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))->post("/api/v1/stores/{$store->id}/branding", [
            'logo' => UploadedFile::fake()->image('first.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $firstPath = $store->fresh()->logo;

        $this->withHeaders($this->bearer($owner))->post("/api/v1/stores/{$store->id}/branding", [
            'logo' => UploadedFile::fake()->image('second.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $secondPath = $store->fresh()->logo;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('tenant')->assertMissing($firstPath);
        Storage::disk('tenant')->assertExists($secondPath);
    }

    public function test_an_unsupported_social_network_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/stores', [
                'name' => 'Shop',
                'code' => 'SHOP',
                'socials' => ['myspace' => 'https://myspace.com/shop'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('socials');
    }
}
