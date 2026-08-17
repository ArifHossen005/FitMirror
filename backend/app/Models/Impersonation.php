<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Mission Control impersonation session — issued by
 * Api\Mission\ImpersonationController, ended by
 * Api\V1\Auth\ImpersonationExitController or by the token's own expiry.
 * Deliberately not BelongsToTenant: it is itself the audit record of a
 * cross-tenant action, and a super admin's own impersonation history must
 * remain queryable from Mission Control regardless of tenant context.
 */
class Impersonation extends Model
{
    protected $fillable = [
        'super_admin_id',
        'tenant_id',
        'user_id',
        'token_id',
        'reason',
        'started_at',
        'expires_at',
        'ended_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
