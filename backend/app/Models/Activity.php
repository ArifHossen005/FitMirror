<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Replaces Spatie's own Activity model (config/activitylog.php
 * 'activity_model') so every logged event carries a tenant_id, filled the
 * same way as any other BelongsToTenant model — automatically, from
 * TenantContext, at the moment the underlying model's create/update/delete
 * fires LogsActivity's listener during an authenticated tenant request.
 *
 * Mission Control actions (no tenant context resolved — see D-13) log with
 * tenant_id left null unless a service explicitly sets it via
 * activity()->tap(fn ($activity) => $activity->tenant_id = $tenant->id) —
 * see App\Services\Mission\ImpersonationService for the one place that
 * matters today.
 *
 * SpatieActivity's own docblock declares `newQuery()`/`query()` as
 * returning a plain (non-generic) `Builder|Activity` union — PHPStan
 * resolves `@method` docblocks from the nearest declaring class up the
 * hierarchy, so without redeclaring it here, every `App\Models\Activity::
 * query()` call in this codebase would inherit that non-generic
 * declaration instead of Larastan's usual auto-inferred `Builder<static>`,
 * making every `->paginate()->through(fn (Activity $e) => ...)` call
 * downstream "unresolvable" to static analysis.
 *
 * @property int|null $tenant_id
 *
 * @method static Builder<Activity> query()
 */
class Activity extends SpatieActivity
{
    use BelongsToTenant;
}
