<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of one tenant in one franchise group.
 *
 * Deliberately does NOT use BelongsToTenant. The row's only tenant column
 * is `member_tenant_id` — the franchisee, not the franchisor — so applying
 * TenantScope here would filter a franchisor's own membership list down to
 * the single row where the franchisor is also a member of its own group,
 * which is exactly backwards. Isolation is enforced one level up instead:
 * FranchiseGroup itself is tenant-scoped, so a franchisor can only ever
 * reach memberships through a group it owns.
 */
class FranchiseGroupMember extends Model
{
    protected $fillable = [
        'franchise_group_id',
        'member_tenant_id',
        'label',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FranchiseGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(FranchiseGroup::class, 'franchise_group_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function memberTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'member_tenant_id');
    }
}
