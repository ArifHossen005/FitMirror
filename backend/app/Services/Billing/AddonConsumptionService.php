<?php

namespace App\Services\Billing;

use App\Exceptions\InsufficientAddonBalanceException;
use App\Models\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Draws down a tenant's purchased add-on balance (SMS sent, storage
 * bytes, support days, templates used) — FIFO across TenantAddon rows by
 * `purchased_at`, so an earlier purchase's `expires_at` (if any) is
 * exhausted before a later one's. No real caller exists yet: SMS sending
 * (Phase 11) and storage tracking (Phase 5's media pipeline) aren't built,
 * so this is a tested primitive ahead of its callers — the same pattern
 * as SubscriptionService's cancel()/startTrial() were in Phase 3.B.
 */
class AddonConsumptionService extends BaseService
{
    public function balance(Tenant $tenant, string $addonCode): int
    {
        return (int) $this->usableRowsQuery($tenant, $addonCode)->sum('remaining_balance');
    }

    /**
     * @throws InsufficientAddonBalanceException if the tenant's total
     *                                           usable balance for $addonCode is less than $amount — nothing is
     *                                           decremented in that case, not even partially.
     */
    public function consume(Tenant $tenant, string $addonCode, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->transaction(function () use ($tenant, $addonCode, $amount) {
            $rows = $this->usableRowsQuery($tenant, $addonCode)->lockForUpdate()->get();
            $available = (int) $rows->sum('remaining_balance');

            if ($available < $amount) {
                throw new InsufficientAddonBalanceException($addonCode, $amount, $available);
            }

            $remainingToDeduct = $amount;

            foreach ($rows as $row) {
                if ($remainingToDeduct <= 0) {
                    break;
                }

                $deduction = min($row->remaining_balance, $remainingToDeduct);
                $row->decrement('remaining_balance', $deduction);
                $remainingToDeduct -= $deduction;
            }
        });
    }

    /**
     * @return Builder<TenantAddon>
     */
    private function usableRowsQuery(Tenant $tenant, string $addonCode): Builder
    {
        $addonId = Addon::query()->where('code', $addonCode)->value('id');

        return TenantAddon::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('addon_id', $addonId)
            ->where('remaining_balance', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('purchased_at');
    }
}
