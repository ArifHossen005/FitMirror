<?php

namespace App\Models;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide, not BelongsToTenant — the same four catalog rows (SMS
 * pack, storage pack, priority support, template pack) are purchasable by
 * every tenant, the same way every tenant chooses from the same three
 * Plan rows.
 */
class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'price',
        'currency',
        'unit_amount',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'price' => 'integer',
            'unit_amount' => 'integer',
            'status' => AddonStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TenantAddon, $this>
     */
    public function tenantAddons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }

    public function isPurchasable(): bool
    {
        return $this->status === AddonStatus::Active;
    }
}
