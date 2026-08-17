<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The root of FitMirror's single-database multi-tenancy model. Every
 * tenant-owned table carries a `tenant_id` and uses the `BelongsToTenant`
 * trait (app/Models/Concerns/BelongsToTenant.php) — this model is the one
 * exception, since a tenant cannot belong to itself.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'custom_domain',
        'owner_id',
        'status',
        'trial_ends_at',
        'plan_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }

    /**
     * Reads a single key out of the `settings` JSON column with dot
     * notation, e.g. `$tenant->setting('kiosk.idle_timeout_seconds')`.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tenant')
            ->logOnly(['name', 'slug', 'custom_domain', 'status', 'plan_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
