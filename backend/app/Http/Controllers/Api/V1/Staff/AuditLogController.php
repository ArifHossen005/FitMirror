<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Staff\AuditLogFilterRequest;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/audit-log — every Tenant/User change recorded by
 * App\Models\Activity, scoped to the caller's own tenant by TenantScope
 * (App\Models\Activity uses BelongsToTenant, same fail-closed guarantee as
 * every other tenant-owned model — see Decision D-13).
 */
class AuditLogController extends BaseApiController
{
    public function index(AuditLogFilterRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()->with(['causer:id,name,email', 'subject']);

        if ($request->validated('user_id')) {
            $query->where('causer_id', $request->validated('user_id'))->where('causer_type', User::class);
        }

        if ($request->validated('module')) {
            $query->where('log_name', $request->validated('module'));
        }

        if ($request->validated('action')) {
            $query->where('event', $request->validated('action'));
        }

        if ($request->validated('date_from')) {
            $query->whereDate('created_at', '>=', $request->validated('date_from'));
        }

        if ($request->validated('date_to')) {
            $query->whereDate('created_at', '<=', $request->validated('date_to'));
        }

        $entries = $query->orderByDesc('created_at')->paginate($this->perPage($request));

        return $this->paginated($entries->through(fn (Activity $entry) => [
            'id' => $entry->id,
            'module' => $entry->log_name,
            'action' => $entry->event,
            'description' => $entry->description,
            // causer is a MorphTo — User or SuperAdmin, either of which has
            // these three columns, but the relation itself types as plain
            // Model (Spatie's own docblock), so getAttribute() is used
            // instead of property access to stay type-safe across both.
            'causer' => $entry->causer ? [
                'id' => $entry->causer->getAttribute('id'),
                'name' => $entry->causer->getAttribute('name'),
                'email' => $entry->causer->getAttribute('email'),
            ] : null,
            'subject_type' => $entry->subject_type ? class_basename($entry->subject_type) : null,
            'subject_id' => $entry->subject_id,
            'changes' => $entry->changes(),
            'created_at' => $entry->created_at?->toIso8601String(),
        ]));
    }
}
