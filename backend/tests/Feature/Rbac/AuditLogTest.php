<?php

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_updating_a_user_creates_a_visible_audit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);
        $headers = $this->bearer($owner);

        // Via the real profile endpoint, not a raw forceFill()->save() —
        // TenantContext is only ever populated by ResolveTenant middleware
        // inside an actual HTTP request, and App\Models\Activity (like any
        // BelongsToTenant model) needs that context live at write time to
        // auto-fill tenant_id. A direct model mutation in test code runs
        // with no tenant context at all, which would silently write the
        // resulting activity row with tenant_id = null — invisible to this
        // same query afterwards, since TenantScope fails closed.
        $this->withHeaders($headers)->patchJson('/api/v1/auth/profile', ['name' => 'Renamed Owner'])->assertOk();

        $response = $this->withHeaders($headers)->getJson('/api/v1/audit-log?module=user&action=updated');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertSame('user', $response->json('data.0.module'));
        $this->assertSame('updated', $response->json('data.0.action'));
    }

    public function test_audit_log_is_scoped_to_the_callers_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);
        $ownerB = $this->ownerFor($tenantB);

        $ownerB->forceFill(['name' => 'Renamed In Tenant B'])->save();

        $response = $this->withHeaders($this->bearer($ownerA))->getJson('/api/v1/audit-log');

        $response->assertOk();
        foreach ($response->json('data') as $entry) {
            $this->assertNotSame('Renamed In Tenant B', $entry['description'] ?? null);
        }
    }

    public function test_date_range_filter_excludes_entries_outside_the_range(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);
        $owner->forceFill(['name' => 'Renamed Today'])->save();

        $response = $this->withHeaders($this->bearer($owner))->getJson(
            '/api/v1/audit-log?date_from=' . now()->addDay()->toDateString(),
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
