<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only model — no `widgets` table exists in production. Exists purely
 * to prove BelongsToTenant/TenantScope actually isolate data between
 * tenants, without needing a real business table to land first (none of
 * Phase 2.A's own tables carry tenant_id — the first real one is Phase
 * 2.B's `users`). See tests/Feature/Tenancy/TenantScopeIsolationTest.php.
 *
 * @property int $tenant_id
 * @property string $name
 */
class Widget extends Model
{
    use BelongsToTenant;

    protected $table = 'widgets';

    protected $fillable = ['tenant_id', 'name'];
}
