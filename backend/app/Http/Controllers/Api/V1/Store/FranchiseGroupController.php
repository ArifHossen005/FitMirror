<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Store\StoreFranchiseGroupRequest;
use App\Models\FranchiseGroup;
use App\Services\Store\FranchiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Franchisor roll-up across the franchisee tenants in a group. Every route
 * is behind `plan.feature:franchise_management`, so only a plan carrying
 * that entitlement (Max, per PlanSeeder) reaches this controller at all —
 * a Free or Pro tenant gets PlanGateResponse::featureUnavailable() with an
 * upgrade CTA, not a 404.
 *
 * {group} resolves through TenantScope on FranchiseGroup, so a franchisor
 * can only ever address a group it owns; the members inside it are read
 * cross-tenant by FranchiseService, explicitly and by design.
 */
class FranchiseGroupController extends BaseApiController
{
    public function __construct(private readonly FranchiseService $franchise) {}

    public function index(Request $request): JsonResponse
    {
        $groups = FranchiseGroup::query()
            ->withCount('members')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->paginated($groups->through(fn (FranchiseGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'member_count' => $group->members_count ?? 0,
            'created_at' => $group->created_at?->toIso8601String(),
        ]));
    }

    public function store(StoreFranchiseGroupRequest $request): JsonResponse
    {
        $group = $this->franchise->create($request->user()->tenant, $request->validated());

        return $this->created($this->franchise->consolidatedView($group), 'Franchise group created successfully.');
    }

    /**
     * The consolidated view — one row per member tenant with the counts a
     * franchisor monitors. See FranchiseService::consolidatedView() for why
     * try-on and revenue figures are absent rather than zeroed.
     */
    public function overview(FranchiseGroup $group): JsonResponse
    {
        return $this->success($this->franchise->consolidatedView($group));
    }

    public function addMember(Request $request, FranchiseGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'member_tenant_id' => ['required', 'integer', 'min:1'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $this->franchise->addMember($group, (int) $validated['member_tenant_id'], $validated['label'] ?? null);

        return $this->success($this->franchise->consolidatedView($group->refresh()), 'Shop added to the group.');
    }

    public function removeMember(FranchiseGroup $group, int $memberTenantId): JsonResponse
    {
        $this->franchise->removeMember($group, $memberTenantId);

        return $this->success($this->franchise->consolidatedView($group->refresh()), 'Shop removed from the group.');
    }

    public function destroy(FranchiseGroup $group): JsonResponse
    {
        $this->franchise->delete($group);

        return $this->noContent();
    }
}
