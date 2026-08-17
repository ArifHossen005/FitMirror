<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Free/Pro/Max — see the product document's §15 "Subscription Plan
 * Comparison" table, seeded verbatim by PlanSeeder. Not a BelongsToTenant
 * model — plans are platform-wide, defined once and referenced by every
 * tenant's `tenants.plan_id`.
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'currency',
        'trial_days',
        'is_public',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'trial_days' => 'integer',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'status' => PlanStatus::class,
        ];
    }

    /**
     * @return HasMany<PlanLimit, $this>
     */
    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function isUsable(): bool
    {
        return $this->status === PlanStatus::Active;
    }

    /**
     * The always-available floor every tenant with no plan chosen yet
     * (still pending checkout — Phase 3.E doesn't exist) effectively gets.
     * Throws if the Free plan hasn't been seeded — that's a deployment
     * error (PlanSeeder must run before the app is reachable), not a
     * condition to silently paper over.
     */
    public static function free(): self
    {
        return static::query()->where('slug', 'free')->firstOrFail();
    }

    /**
     * @return Collection<int, Plan>
     */
    public static function publicPlans(): Collection
    {
        return Plan::query()
            ->where('is_public', true)
            ->where('status', PlanStatus::Active)
            ->orderBy('sort_order')
            ->get();
    }
}
