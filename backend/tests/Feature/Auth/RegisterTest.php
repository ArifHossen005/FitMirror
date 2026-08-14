<?php

namespace Tests\Feature\Auth;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Password::uncompromised() calls the real Have I Been Pwned API —
        // faked so tests are deterministic and never depend on network
        // access. An empty 200 response means "not found in the breach
        // corpus", i.e. the submitted password is not compromised.
        Http::fake(['*' => Http::response('', 200)]);
    }

    public function test_registration_creates_a_pending_tenant_and_its_owner(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Dhaka Fashion House',
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tenant.status', 'pending');
        $response->assertJsonPath('data.user.email', 'ayesha@example.com');

        $tenant = Tenant::query()->where('slug', $response->json('data.tenant.slug'))->firstOrFail();
        $this->assertSame(TenantStatus::Pending, $tenant->status);

        $owner = User::withoutTenantScope()->where('email', 'ayesha@example.com')->firstOrFail();
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame($owner->id, $tenant->owner_id);
        $this->assertFalse($owner->hasVerifiedEmail());
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Dhaka Fashion House',
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ayesha@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Dhaka Fashion House',
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_two_tenants_registered_with_the_same_shop_name_get_distinct_slugs(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Fashion House',
            'name' => 'Owner One',
            'email' => 'one@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ])->assertCreated();

        $second = $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Fashion House',
            'name' => 'Owner Two',
            'email' => 'two@example.com',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $second->assertCreated();
        $this->assertNotSame(
            Tenant::query()->where('name', 'Fashion House')->first()->slug,
            $second->json('data.tenant.slug'),
        );
        $this->assertSame(2, Tenant::query()->where('name', 'Fashion House')->count());
    }
}
