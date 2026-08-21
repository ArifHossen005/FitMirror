<?php

namespace App\Models;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A node in the tenant's catalog taxonomy, self-referencing via parent_id
 * to any depth. Tree-walking logic (ancestors/descendants/depth) lives on
 * App\Services\Catalog\CategoryService, not here, matching how StoreHour's
 * comparison logic sits in the model but Store's plan-limit/main-branch
 * logic sits in StoreService — a leaf query the model itself can answer
 * cheaply (parent(), children()) stays here; anything that walks the whole
 * tree or enforces a business rule belongs in the service.
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Hard cap on nesting depth (root = depth 0), enforced by
     * CategoryService::assertWithinDepthLimit(). Four levels comfortably
     * covers "Boys > Panjabi > Wedding > Premium" without letting the tree
     * grow deep enough that a breadcrumb becomes unusable on a kiosk screen.
     */
    public const MAX_DEPTH = 4;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'icon',
        'image',
        'gender',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'gender' => CategoryGender::class,
            'status' => CategoryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Direct children only — see CategoryService::descendants() for the
     * whole subtree.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param Builder<Category> $query
     * @return Builder<Category>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param Builder<Category> $query
     * @return Builder<Category>
     */
    public function scopeCountingTowardLimit(Builder $query): Builder
    {
        return $query->where('status', CategoryStatus::Active->value)
            ->orWhere('status', CategoryStatus::Inactive->value);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('tenant')->url($this->image) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('category')
            ->logOnly(['name', 'slug', 'parent_id', 'gender', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
