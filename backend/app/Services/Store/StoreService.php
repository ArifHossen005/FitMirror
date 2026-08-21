<?php

namespace App\Services\Store;

use App\Enums\KioskDeviceStatus;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Services\Plan\PlanService;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Branch CRUD, plus the two rules that cannot live in a form request
 * because they depend on the tenant's other rows: the plan's `branches`
 * cap, and the invariant that a tenant always has exactly one main branch.
 *
 * The plan check follows the pattern StaffInvitationService established —
 * the service counts its own resource and hands that count to
 * PlanService::assertWithinLimit(), rather than a generic middleware
 * trying to guess what is being counted (see that method's own docblock).
 */
class StoreService extends BaseService
{
    /** Sub-path under the tenant's own storage prefix that branch imagery lives in. */
    private const BRANDING_DIRECTORY = 'stores';

    public function __construct(private readonly PlanService $plans) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Store
    {
        $this->plans->assertWithinLimit($tenant, 'branches', $this->countTowardLimit($tenant));

        $this->assertCodeIsUnique($tenant, $data['code']);

        return $this->transaction(function () use ($tenant, $data) {
            // The tenant's very first branch is always the main one —
            // otherwise a single-branch tenant would have no main branch at
            // all until they happened to tick the box.
            $isFirstStore = !Store::query()->where('tenant_id', $tenant->id)->exists();

            $store = Store::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'code' => Str::upper($data['code']),
                'is_main' => $isFirstStore ? true : (bool) ($data['is_main'] ?? false),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'area' => $data['area'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'map_url' => $data['map_url'] ?? null,
                'socials' => $this->normaliseSocials($data['socials'] ?? null),
                'timezone' => $data['timezone'] ?? config('app.tenant_default_timezone', 'Asia/Dhaka'),
                'status' => $data['status'] ?? StoreStatus::Active->value,
            ]);

            if ($store->is_main) {
                $this->demoteOtherMainStores($tenant, $store);
            }

            return $store;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Store $store, array $data): Store
    {
        if (array_key_exists('code', $data)) {
            $this->assertCodeIsUnique($store->tenant, $data['code'], $store->id);
            $data['code'] = Str::upper($data['code']);
        }

        if (array_key_exists('socials', $data)) {
            $data['socials'] = $this->normaliseSocials($data['socials']);
        }

        // Re-opening or re-activating a closed branch consumes a slot
        // again, so it has to pass the same cap a new branch would — an
        // otherwise trivial status change is the one edit that can push a
        // tenant over their plan.
        if (array_key_exists('status', $data) && $this->reactivatesBranch($store, (string) $data['status'])) {
            $this->plans->assertWithinLimit($store->tenant, 'branches', $this->countTowardLimit($store->tenant));
        }

        return $this->transaction(function () use ($store, $data) {
            $wantsMain = array_key_exists('is_main', $data) ? (bool) $data['is_main'] : null;

            if ($wantsMain === false && $store->is_main) {
                throw ValidationException::withMessages([
                    'is_main' => ['Promote another branch to main instead of unsetting this one.'],
                ]);
            }

            $store->fill($data)->save();

            if ($store->is_main) {
                $this->demoteOtherMainStores($store->tenant, $store);
            }

            return $store->refresh();
        });
    }

    /**
     * Replaces the branch logo and/or banner on the tenant disk. Each
     * upload deletes whatever it supersedes, so a tenant that re-uploads a
     * logo weekly does not accumulate orphaned objects against their
     * storage quota.
     *
     * @param array{logo?: UploadedFile, banner?: UploadedFile, remove_logo?: bool, remove_banner?: bool} $data
     */
    public function updateBranding(Store $store, array $data): Store
    {
        foreach (['logo', 'banner'] as $field) {
            $uploaded = $data[$field] ?? null;
            $shouldRemove = (bool) ($data['remove_' . $field] ?? false);

            if (!$uploaded instanceof UploadedFile && !$shouldRemove) {
                continue;
            }

            $this->deleteAsset($store->{$field});

            $store->{$field} = $uploaded instanceof UploadedFile
                ? $this->storeAsset($store, $field, $uploaded)
                : null;
        }

        $store->save();

        return $store;
    }

    /**
     * Soft delete. The main branch is protected structurally: deleting it
     * would leave the tenant with no branch flagged main and every "which
     * branch does this belong to by default" lookup silently answering
     * null.
     */
    public function delete(Store $store): void
    {
        if ($store->is_main && Store::query()->where('tenant_id', $store->tenant_id)->count() > 1) {
            throw ValidationException::withMessages([
                'store' => ['Promote another branch to main before deleting this one.'],
            ]);
        }

        $this->transaction(function () use ($store) {
            // Kiosk devices in a removed branch must stop authenticating
            // immediately — the hardware is physically gone, and a device
            // token that outlives its branch is a credential nobody is
            // watching any more.
            $store->kioskDevices()->update([
                'device_token_hash' => null,
                'pairing_code' => null,
                'pairing_code_expires_at' => null,
                'status' => KioskDeviceStatus::Pending->value,
            ]);

            $store->delete();
        });
    }

    /**
     * Branches occupying a plan slot right now — see
     * StoreStatus::countsTowardBranchLimit().
     */
    public function countTowardLimit(Tenant $tenant): int
    {
        return Store::query()
            ->where('tenant_id', $tenant->id)
            ->countingTowardLimit()
            ->count();
    }

    private function reactivatesBranch(Store $store, string $newStatus): bool
    {
        $wasCounting = $store->status->countsTowardBranchLimit();
        $willCount = StoreStatus::from($newStatus)->countsTowardBranchLimit();

        return !$wasCounting && $willCount;
    }

    private function assertCodeIsUnique(Tenant $tenant, string $code, ?int $ignoreStoreId = null): void
    {
        $query = Store::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', Str::upper($code));

        if ($ignoreStoreId !== null) {
            $query->whereKeyNot($ignoreStoreId);
        }

        // withTrashed(): the unique index spans soft-deleted rows too, so
        // reusing a deleted branch's code would fail at the database level
        // with an opaque 500 rather than a field-level validation message.
        if ($query->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Another branch already uses this code.'],
            ]);
        }
    }

    private function demoteOtherMainStores(Tenant $tenant, Store $keep): void
    {
        Store::query()
            ->where('tenant_id', $tenant->id)
            ->whereKeyNot($keep->id)
            ->where('is_main', true)
            ->update(['is_main' => false]);
    }

    /**
     * Drops blank entries rather than persisting empty strings, so
     * "clear my Instagram link" and "never set one" are the same stored
     * state and the dashboard has one case to render, not two.
     *
     * @param array<string, string|null>|null $socials
     * @return array<string, string>|null
     */
    private function normaliseSocials(?array $socials): ?array
    {
        if ($socials === null) {
            return null;
        }

        $cleaned = [];

        foreach ($socials as $network => $url) {
            $url = is_string($url) ? trim($url) : '';

            if ($url !== '' && in_array($network, Store::SUPPORTED_SOCIALS, true)) {
                $cleaned[$network] = $url;
            }
        }

        return $cleaned === [] ? null : $cleaned;
    }

    private function storeAsset(Store $store, string $field, UploadedFile $file): string
    {
        $directory = TenantStorage::path($store->tenant_id, self::BRANDING_DIRECTORY . '/' . $store->id);
        $filename = $field . '-' . Str::random(16) . '.' . $file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'tenant');
    }

    private function deleteAsset(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('tenant')->exists($path)) {
            Storage::disk('tenant')->delete($path);
        }
    }
}
