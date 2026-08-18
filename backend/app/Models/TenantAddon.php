<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TenantAddonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per purchase — a tenant buying the same addon twice gets two
 * rows, drawn down oldest-first by App\Services\Billing\
 * AddonConsumptionService (FIFO), rather than merged into a single
 * running balance, so each purchase's own `expires_at` (if any) is
 * honoured independently.
 */
class TenantAddon extends Model
{
    /** @use HasFactory<TenantAddonFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'addon_id',
        'invoice_id',
        'remaining_balance',
        'purchased_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'remaining_balance' => 'integer',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function isUsable(): bool
    {
        if ($this->remaining_balance <= 0) {
            return false;
        }

        return !($this->expires_at && $this->expires_at->isPast());
    }
}
