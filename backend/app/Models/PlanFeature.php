<?php

namespace App\Models;

use Database\Factories\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gate per plan — `feature_key` in use today: `campaign_manager`,
 * `loyalty_program`, `social_media_post`, `analytics`, `custom_branding`,
 * `api_access`, `sslcommerz_payment`. `meta` carries tier detail for
 * features that aren't simple on/off (see the migration's own comment) —
 * e.g. `{"tier": "full_ai"}`.
 */
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'feature_key',
        'enabled',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tier(): ?string
    {
        return $this->meta['tier'] ?? null;
    }
}
