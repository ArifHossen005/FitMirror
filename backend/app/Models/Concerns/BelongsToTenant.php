<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned Eloquent model (see DOCUMENTATION.md
 * §4.4.1 — every such table carries `tenant_id`, never omitted). Two
 * effects:
 *
 *   1. A global scope filters every query to the active TenantContext, so
 *      a cross-tenant data leak requires deliberately calling
 *      withoutTenantScope() — it can never happen by omission.
 *   2. `tenant_id` is auto-filled on create from the active context, so
 *      application code never assigns it by hand (and can't assign the
 *      wrong one).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if ($model->getAttribute('tenant_id') === null && app(TenantContext::class)->has()) {
                $model->setAttribute('tenant_id', app(TenantContext::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Escape hatch for the rare legitimate cross-tenant query — Mission
     * Control's tenant list, a scheduled job aggregating across every
     * tenant, an artisan command. Named explicitly (not a bare
     * withoutGlobalScope(TenantScope::class) at every call site) so a code
     * review can grep for every place tenant isolation is deliberately
     * bypassed.
     */
    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
