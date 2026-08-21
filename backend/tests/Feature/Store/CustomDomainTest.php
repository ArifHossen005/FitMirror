<?php

namespace Tests\Feature\Store;

use App\Enums\CustomDomainStatus;
use App\Models\CustomDomainRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dns\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Subdomain assignment and the custom-domain DNS TXT challenge.
 *
 * DNS goes through FakeDnsResolver rather than the real resolver — the
 * seam exists precisely so verification can be tested without depending on
 * propagation (see the DnsResolver interface's docblock).
 */
class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver;
        $this->app->instance(DnsResolver::class, $this->dns);
    }

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

    public function test_owner_can_check_and_assign_a_subdomain(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/tenant/subdomain/check?subdomain=zara-bd')
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/subdomain', ['subdomain' => 'Zara-BD'])
            ->assertOk()
            ->assertJsonPath('data.subdomain', 'zara-bd');

        $tenant->refresh();
        // Both columns move together — ResolveTenant matches on `slug`
        // while the dashboard displays `subdomain`.
        $this->assertSame('zara-bd', $tenant->subdomain);
        $this->assertSame('zara-bd', $tenant->slug);
    }

    public function test_reserved_and_taken_subdomains_are_refused_with_the_same_wording_as_the_check(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        Tenant::factory()->create(['slug' => 'takenshop', 'subdomain' => 'takenshop']);

        foreach (['admin', 'takenshop', 'ab', 'not_valid', '-leading'] as $candidate) {
            $check = $this->withHeaders($this->bearer($owner))
                ->getJson('/api/v1/tenant/subdomain/check?subdomain=' . urlencode($candidate));

            $check->assertOk();
            $check->assertJsonPath('data.available', false);

            $assign = $this->withHeaders($this->bearer($owner))
                ->postJson('/api/v1/tenant/subdomain', ['subdomain' => $candidate]);

            // 'ab' is short enough to be caught by the request's own min
            // rule; the rest reach the service. Either way it is a 422.
            $assign->assertStatus(422);
        }
    }

    public function test_a_manager_cannot_change_the_shops_address(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $this->withHeaders($this->bearer($manager))
            ->postJson('/api/v1/tenant/subdomain', ['subdomain' => 'hijacked'])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_custom_domain_is_gated_to_plans_that_include_it(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com']);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_feature_unavailable');
        $response->assertJsonPath('errors.feature', 'custom_domain');
    }

    public function test_requesting_a_domain_returns_the_exact_dns_record_to_publish(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'HTTPS://Shop.Example.com/']);

        $response->assertCreated();
        // A pasted URL is normalised rather than rejected.
        $response->assertJsonPath('data.domain', 'shop.example.com');
        $response->assertJsonPath('data.dns.type', 'TXT');
        $response->assertJsonPath('data.dns.name', '_fitmirror-verification.shop.example.com');
        $response->assertJsonPath('data.is_verified', false);

        $this->assertNull($tenant->fresh()->custom_domain);
    }

    public function test_verification_fails_cleanly_while_dns_has_not_propagated(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);
        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com'])
            ->assertCreated();

        // Not an error response — the dashboard polls this, and propagation
        // delay is the expected answer, not a failure.
        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain/verify');

        $response->assertOk();
        $response->assertJsonPath('data.status', CustomDomainStatus::Failed->value);
        $response->assertJsonPath('data.is_verified', false);
        $response->assertJsonPath('data.attempts', 1);
        $this->assertStringContainsString('No TXT record found', $response->json('data.last_error'));
        $this->assertNull($tenant->fresh()->custom_domain);
    }

    public function test_publishing_the_token_verifies_the_domain_and_activates_it(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $created = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com']);
        $created->assertCreated();

        $token = CustomDomainRequest::withoutTenantScope()
            ->where('domain', 'shop.example.com')
            ->firstOrFail()
            ->verification_token;

        $this->dns->publish('_fitmirror-verification.shop.example.com', ['some-other-record', $token]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain/verify');

        $response->assertOk();
        $response->assertJsonPath('data.is_verified', true);
        $response->assertJsonPath('data.status', CustomDomainStatus::Verified->value);

        // Only now is the host actually served — this is what makes
        // ResolveTenant answer on the domain.
        $this->assertSame('shop.example.com', $tenant->fresh()->custom_domain);
    }

    public function test_the_verification_token_survives_a_retry(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com'])
            ->assertCreated();

        $first = CustomDomainRequest::withoutTenantScope()->firstOrFail()->verification_token;

        $this->withHeaders($this->bearer($owner))->postJson('/api/v1/tenant/custom-domain/verify')->assertOk();

        // Re-requesting the same domain must not rotate a token the tenant
        // has already pasted into their DNS panel.
        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com'])
            ->assertCreated();

        $this->assertSame($first, CustomDomainRequest::withoutTenantScope()->firstOrFail()->verification_token);
    }

    public function test_a_fitmirror_owned_domain_cannot_be_claimed(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        foreach (['fitmirror.com', 'anything.fitmirror.com', 'localhost'] as $blocked) {
            $this->withHeaders($this->bearer($owner))
                ->postJson('/api/v1/tenant/custom-domain', ['domain' => $blocked])
                ->assertStatus(422)
                ->assertJsonValidationErrors('domain');
        }
    }

    public function test_a_domain_already_claimed_by_another_tenant_is_refused(): void
    {
        $other = Tenant::factory()->onPlan('max')->create();
        CustomDomainRequest::factory()->create(['tenant_id' => $other->id, 'domain' => 'contested.example.com']);

        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'contested.example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    public function test_removing_a_verified_domain_stops_serving_it(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/tenant/custom-domain', ['domain' => 'shop.example.com'])
            ->assertCreated();

        $token = CustomDomainRequest::withoutTenantScope()->firstOrFail()->verification_token;
        $this->dns->publish('_fitmirror-verification.shop.example.com', [$token]);
        $this->withHeaders($this->bearer($owner))->postJson('/api/v1/tenant/custom-domain/verify')->assertOk();

        $this->withHeaders($this->bearer($owner))
            ->deleteJson('/api/v1/tenant/custom-domain')
            ->assertNoContent();

        $this->assertNull($tenant->fresh()->custom_domain);
        $this->assertSame(0, CustomDomainRequest::withoutTenantScope()->count());
    }
}
