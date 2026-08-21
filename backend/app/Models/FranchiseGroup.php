<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FranchiseGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A franchisor's roll-up of the franchisee tenants it monitors. The group
 * itself is owned by one tenant (`tenant_id`, carrying the usual
 * BelongsToTenant scope); its *members* are other tenants entirely, linked
 * through FranchiseGroupMember whose column is deliberately called
 * `member_tenant_id` rather than `tenant_id` — see that migration's own
 * comment for why the naming matters to TenantScope.
 *
 * Reading a member's data is therefore an explicit, audited cross-tenant
 * operation (App\Services\Store\FranchiseService), in the same family as
 * the four bypasses catalogued in Decision D-13 — and gated to plans
 * carrying the `franchise_management` feature.
 */
class FranchiseGroup extends Model
{
    /** @use HasFactory<FranchiseGroupFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
    ];

    /**
     * @return HasMany<FranchiseGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FranchiseGroupMember::class);
    }
}
