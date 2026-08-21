<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A physical branch. One tenant has one or more; the plan's `branches`
 * limit caps how many non-closed ones may exist at a time (enforced in
 * App\Services\Store\StoreService, never here — see PlanService::
 * assertWithinLimit()'s own docblock for why limits are checked at the
 * service call site rather than by generic middleware).
 *
 * Soft-deleted rather than hard-deleted: kiosk devices, shifts, and (from
 * Phase 6) try-on sessions all reference `store_id`, and a manager who
 * removes a branch by mistake must not take that history with it.
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The social networks a store profile may carry. Anything outside this
     * list is rejected by UpdateStoreRequest rather than silently stored,
     * so the dashboard never has to render a key it has no icon for.
     *
     * @var list<string>
     */
    public const SUPPORTED_SOCIALS = ['facebook', 'instagram', 'tiktok', 'youtube', 'whatsapp', 'website'];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'is_main',
        'phone',
        'email',
        'address',
        'city',
        'area',
        'lat',
        'lng',
        'map_url',
        'logo',
        'banner',
        'socials',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'socials' => 'array',
            'status' => StoreStatus::class,
        ];
    }

    /**
     * @return HasMany<StoreHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(StoreHour::class);
    }

    /**
     * @return HasMany<KioskDevice, $this>
     */
    public function kioskDevices(): HasMany
    {
        return $this->hasMany(KioskDevice::class);
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * Branches that consume a slot against the plan's `branches` limit —
     * see StoreStatus::countsTowardBranchLimit().
     *
     * @param Builder<Store> $query
     * @return Builder<Store>
     */
    public function scopeCountingTowardLimit(Builder $query): Builder
    {
        return $query->whereNot('status', StoreStatus::Closed->value);
    }

    /**
     * @param Builder<Store> $query
     * @return Builder<Store>
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', StoreStatus::Active->value);
    }

    /**
     * Public URL for a path stored on the `tenant` disk, or null when the
     * asset was never uploaded. Kept on the model (not duplicated in every
     * controller's present() method) because both the dashboard API and
     * the kiosk API serve the same two images.
     */
    public function assetUrl(?string $path): ?string
    {
        return $path ? Storage::disk('tenant')->url($path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('store')
            ->logOnly(['name', 'code', 'is_main', 'status', 'address', 'city', 'phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
