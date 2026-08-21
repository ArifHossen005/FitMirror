<?php

namespace App\Services\Store;

use App\Models\FranchiseGroup;
use App\Models\FranchiseGroupMember;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Franchisor-side roll-up across the franchisee tenants in a group.
 *
 * Every read here is a deliberate cross-tenant query — that is the whole
 * point of the feature — so each one is written as an explicit
 * withoutTenantScope() call with the group membership as the authorisation
 * boundary, rather than by switching TenantContext per member. Decision
 * D-13's rule is that a bypass must be visible at its call site; these are.
 *
 * Access is gated one level up by `plan.feature:franchise_management`
 * (EnforcePlanFeature), so a Free or Pro tenant never reaches this class.
 */
class FranchiseService extends BaseService
{
    /**
     * @param array{name: string, description?: string|null, member_tenant_ids?: list<int>} $data
     */
    public function create(Tenant $tenant, array $data): FranchiseGroup
    {
        $slug = $this->uniqueSlug($tenant, $data['name']);

        return $this->transaction(function () use ($tenant, $data, $slug) {
            $group = FranchiseGroup::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
            ]);

            // The franchisor's own tenant is always a member — a roll-up
            // that excluded the flagship it was created from would be
            // missing the one shop the owner most expects to see.
            $this->addMember($group, $tenant->id);

            foreach ($data['member_tenant_ids'] ?? [] as $memberTenantId) {
                $this->addMember($group, (int) $memberTenantId);
            }

            return $group->refresh();
        });
    }

    public function addMember(FranchiseGroup $group, int $memberTenantId, ?string $label = null): FranchiseGroupMember
    {
        // withoutTenantScope() is unnecessary here — Tenant is the one
        // model that does not carry BelongsToTenant (see its docblock) —
        // but the membership target genuinely is another tenant, so it is
        // validated as existing and usable before being linked.
        $memberTenant = Tenant::query()->find($memberTenantId);

        if (!$memberTenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'member_tenant_ids' => ['One of the selected shops no longer exists.'],
            ]);
        }

        $existing = FranchiseGroupMember::query()
            ->where('franchise_group_id', $group->id)
            ->where('member_tenant_id', $memberTenant->id)
            ->first();

        if ($existing instanceof FranchiseGroupMember) {
            return $existing;
        }

        return FranchiseGroupMember::query()->create([
            'franchise_group_id' => $group->id,
            'member_tenant_id' => $memberTenant->id,
            'label' => $label,
            'joined_at' => now(),
        ]);
    }

    public function removeMember(FranchiseGroup $group, int $memberTenantId): void
    {
        if ($memberTenantId === $group->tenant_id) {
            throw ValidationException::withMessages([
                'member_tenant_id' => ['The group owner cannot be removed from its own group.'],
            ]);
        }

        FranchiseGroupMember::query()
            ->where('franchise_group_id', $group->id)
            ->where('member_tenant_id', $memberTenantId)
            ->delete();
    }

    public function delete(FranchiseGroup $group): void
    {
        // Memberships cascade at the database level; only the group row
        // itself is deleted. No franchisee tenant is affected — membership
        // is a reporting relationship, never ownership.
        $group->delete();
    }

    /**
     * The consolidated view: one row per member tenant with the counts a
     * franchisor actually monitors.
     *
     * Aggregated with two grouped queries rather than a loop over members,
     * so a 200-shop franchise is three queries, not four hundred. Try-on
     * and revenue columns are deliberately absent — try_on_sessions does
     * not exist until Phase 6, and inventing a zero for it would read as a
     * real measurement of nothing rather than as an absent metric.
     *
     * @return array{group: array<string, mixed>, members: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function consolidatedView(FranchiseGroup $group): array
    {
        $members = $group->members()->with('memberTenant:id,name,slug,status,plan_id')->get();
        $tenantIds = $members->pluck('member_tenant_id')->all();

        $storeCounts = Store::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->operational()
            ->selectRaw('tenant_id, COUNT(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $staffCounts = User::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, COUNT(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $rows = [];

        foreach ($members as $member) {
            $memberTenant = $member->memberTenant;

            if (!$memberTenant instanceof Tenant) {
                continue;
            }

            $rows[] = [
                'tenant_id' => $memberTenant->id,
                'name' => $memberTenant->name,
                'slug' => $memberTenant->slug,
                'label' => $member->label,
                'status' => $memberTenant->status->value,
                'is_group_owner' => $memberTenant->id === $group->tenant_id,
                'stores' => (int) ($storeCounts[$memberTenant->id] ?? 0),
                'staff' => (int) ($staffCounts[$memberTenant->id] ?? 0),
                'joined_at' => $member->joined_at->toIso8601String(),
            ];
        }

        return [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
            ],
            'members' => $rows,
            'totals' => [
                'tenants' => count($rows),
                'stores' => array_sum(array_column($rows, 'stores')),
                'staff' => array_sum(array_column($rows, 'staff')),
            ],
        ];
    }

    private function uniqueSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $suffix = 2;

        while (FranchiseGroup::query()->where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
