<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ResolveTenant already runs globally (prepended to the 'api'
        // middleware group in bootstrap/app.php) — this route only adds
        // EnsureTenantIsActive on top, to exercise the "resolved but not
        // usable" / "not resolved at all" paths in isolation.
        Route::middleware(['api', 'tenant.active'])->get('/api/v1/__test/tenant-gate', function () {
            return response()->json(['tenant_id' => app(TenantContext::class)->id()]);
        });
    }

    public function test_a_request_with_no_resolvable_tenant_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/__test/tenant-gate');

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'tenant_not_found');
    }

    public function test_x_tenant_header_resolves_an_active_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);

        $response = $this->withHeader('X-Tenant', 'acme')->getJson('/api/v1/__test/tenant-gate');

        $response->assertOk();
        $response->assertJsonPath('tenant_id', $tenant->id);
    }

    public function test_a_suspended_tenant_is_rejected_with_a_clear_error(): void
    {
        Tenant::factory()->status(TenantStatus::Suspended)->create(['slug' => 'acme']);

        $response = $this->withHeader('X-Tenant', 'acme')->getJson('/api/v1/__test/tenant-gate');

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'tenant_suspended');
    }

    public function test_a_pending_tenant_is_rejected_with_a_clear_error(): void
    {
        Tenant::factory()->status(TenantStatus::Pending)->create(['slug' => 'acme']);

        $response = $this->withHeader('X-Tenant', 'acme')->getJson('/api/v1/__test/tenant-gate');

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'tenant_pending');
    }

    public function test_a_trialing_tenant_is_usable(): void
    {
        Tenant::factory()->trial()->create(['slug' => 'acme']);

        $response = $this->withHeader('X-Tenant', 'acme')->getJson('/api/v1/__test/tenant-gate');

        $response->assertOk();
    }

    public function test_subdomain_resolves_a_tenant_when_the_host_matches_the_root_domain(): void
    {
        config(['app.tenant_root_domain' => 'fitmirror.test']);
        $tenant = Tenant::factory()->create(['slug' => 'acme']);

        $response = $this->get('http://acme.fitmirror.test/api/v1/__test/tenant-gate', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('tenant_id', $tenant->id);
    }

    public function test_custom_domain_resolves_a_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'acme', 'custom_domain' => 'shop.example.com']);

        $response = $this->get('http://shop.example.com/api/v1/__test/tenant-gate', ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('tenant_id', $tenant->id);
    }
}
