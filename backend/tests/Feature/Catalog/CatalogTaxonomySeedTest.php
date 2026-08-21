<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Occasion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\Catalog\CatalogTaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against a real bug found running `catalog:seed-defaults` by hand
 * against the dev database: outside a request, no ResolveTenant middleware
 * ever sets an active TenantContext, so TenantScope's fail-closed rule
 * (D-13) made every firstOrCreate() lookup in CatalogTaxonomyService::seed()
 * see zero rows regardless of what already existed — the class's own
 * "idempotent" docblock promise was false until seed() was wrapped in
 * TenantContext::runAs(). A second run threw a unique-constraint violation
 * instead of silently doing nothing, which is exactly what this test would
 * have caught before it ever reached a hand-run command.
 */
class CatalogTaxonomySeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_outside_any_active_tenant_context_creates_the_default_taxonomy(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();

        app(CatalogTaxonomyService::class)->seed($tenant);

        $this->assertSame(14, Category::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, Occasion::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(4, Tag::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count());
    }

    public function test_seeding_twice_is_idempotent_and_never_throws(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $service = app(CatalogTaxonomyService::class);

        $service->seed($tenant);
        $service->seed($tenant);

        $this->assertSame(14, Category::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_artisan_command_seeds_the_given_tenant(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();

        $this->artisan('catalog:seed-defaults', ['tenant' => $tenant->id])->assertSuccessful();

        $this->assertSame(14, Category::query()->withoutTenantScope()->where('tenant_id', $tenant->id)->count());
    }
}
