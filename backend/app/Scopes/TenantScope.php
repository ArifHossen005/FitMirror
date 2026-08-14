<?php

namespace App\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied automatically by every model using BelongsToTenant
 * (app/Models/Concerns/BelongsToTenant.php) — never attached manually.
 *
 * Fails closed: with no active tenant context, the query returns zero
 * rows rather than every tenant's rows. A code path that legitimately
 * needs cross-tenant access (a console command iterating every tenant, an
 * analytics rollup) must say so explicitly — via
 * TenantContext::runAs($tenant, ...) per-tenant, or the model's
 * withoutTenantScope() to intentionally go unscoped — so that forgetting
 * to establish context is a visibly empty result set to debug, never a
 * silent data leak across every tenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->id());

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
