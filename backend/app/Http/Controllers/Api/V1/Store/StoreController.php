<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Store\StoreBrandingRequest;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Models\Store;
use App\Services\Plan\PlanService;
use App\Services\Store\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Branch CRUD. Every {store} route parameter resolves through implicit
 * model binding, which runs through TenantScope, so a cross-tenant id 404s
 * before any method body executes — StorePolicy's own tenant comparison is
 * defence in depth on top of that, matching StaffController's reasoning.
 *
 * The list endpoint returns the tenant's plan headroom alongside the rows
 * so the dashboard can disable "Add branch" without a second request, the
 * same shape Phase 3.E's plan-limit modal already consumes.
 */
class StoreController extends BaseApiController
{
    public function __construct(
        private readonly StoreService $stores,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);

        $tenant = $request->user()->tenant;

        $stores = Store::query()
            ->withCount(['kioskDevices'])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        $used = $this->stores->countTowardLimit($tenant);
        $limit = $this->plans->limit($tenant, 'branches');

        return $this->success([
            'stores' => $stores->through(fn (Store $store) => $this->present($store))->items(),
            'meta' => [
                'current_page' => $stores->currentPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
                'last_page' => $stores->lastPage(),
            ],
            'branch_limit' => [
                'used' => $used,
                'max' => $limit,
                'can_create_more' => $limit === null || $used < $limit,
            ],
        ]);
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Store::class);

        $store = $this->stores->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($store), 'Branch created successfully.');
    }

    public function show(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        return $this->success($this->present($store->loadCount('kioskDevices')));
    }

    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $updated = $this->stores->update($store, $request->validated());

        return $this->success($this->present($updated), 'Branch updated successfully.');
    }

    /**
     * Separate from update() because a multipart body only reaches PHP over
     * POST — see StoreBrandingRequest's own docblock.
     */
    public function branding(StoreBrandingRequest $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $payload = $request->validated();

        if ($request->hasFile('logo')) {
            $payload['logo'] = $request->file('logo');
        }

        if ($request->hasFile('banner')) {
            $payload['banner'] = $request->file('banner');
        }

        $updated = $this->stores->updateBranding($store, $payload);

        return $this->success($this->present($updated), 'Branch branding updated successfully.');
    }

    public function destroy(Store $store): JsonResponse
    {
        $this->authorize('delete', $store);

        $this->stores->delete($store);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'code' => $store->code,
            'is_main' => $store->is_main,
            'phone' => $store->phone,
            'email' => $store->email,
            'address' => $store->address,
            'city' => $store->city,
            'area' => $store->area,
            'lat' => $store->lat !== null ? (float) $store->lat : null,
            'lng' => $store->lng !== null ? (float) $store->lng : null,
            'map_url' => $store->map_url,
            'logo_url' => $store->assetUrl($store->logo),
            'banner_url' => $store->assetUrl($store->banner),
            'socials' => $store->socials ?? [],
            'timezone' => $store->timezone,
            'status' => $store->status->value,
            'status_label' => $store->status->label(),
            'kiosk_device_count' => $store->kiosk_devices_count ?? 0,
            'created_at' => $store->created_at?->toIso8601String(),
        ];
    }
}
