<?php

namespace App\Services\Auth;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Events\TenantRegistered;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Creates a brand-new tenant + its owner user together, atomically. This is
 * narrower than the "tenant provisioning service" PROGRESS.md Phase 2.A
 * describes and leaves unbuilt — that service also seeds default roles
 * (Phase 2.C) and a default store (Phase 4.A), neither of which exist yet.
 * This is registration's actual, real dependency: a tenant record and its
 * owner, nothing more.
 */
class RegistrationService extends BaseService
{
    /**
     * @param array{name: string, email: string, password: string, phone: ?string, tenant_name: string} $data
     * @return array{tenant: Tenant, owner: User}
     */
    public function register(array $data): array
    {
        return $this->transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data['tenant_name'],
                'slug' => $this->generateUniqueSlug($data['tenant_name']),
                'subdomain' => $this->generateUniqueSlug($data['tenant_name']),
                'status' => TenantStatus::Pending,
                'settings' => [],
            ]);

            $owner = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            $tenant->forceFill(['owner_id' => $owner->id])->save();

            event(new TenantRegistered($tenant, $owner));

            $owner->sendEmailVerificationNotification();

            return ['tenant' => $tenant, 'owner' => $owner];
        });
    }

    private function generateUniqueSlug(string $tenantName): string
    {
        $base = Str::slug($tenantName);
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->orWhere('subdomain', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
