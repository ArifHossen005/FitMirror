<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gates GET /api/v1/audit-log. Row-level tenant isolation is handled
 * separately by App\Models\Activity's own TenantScope (BelongsToTenant) —
 * this policy only answers "is this user even allowed to open the audit
 * log at all", i.e. do they hold 'audit_log.view'.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_log.view');
    }
}
