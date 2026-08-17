<?php

namespace App\Models;

use Database\Factories\FeatureFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide toggle — see the migration's own docblock for how this
 * differs from PlanFeature (per-plan entitlement vs. global kill switch).
 */
class FeatureFlag extends Model
{
    /** @use HasFactory<FeatureFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'enabled',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * Unknown keys default to `false` (a flag that was never seeded is
     * treated as off, never as "the check doesn't apply") — cached for a
     * short window since this is checked on potentially every request.
     */
    public static function isEnabled(string $key): bool
    {
        return (bool) Cache::remember(
            "feature_flag:{$key}",
            now()->addMinutes(5),
            fn () => static::query()->where('key', $key)->value('enabled') ?? false,
        );
    }
}
