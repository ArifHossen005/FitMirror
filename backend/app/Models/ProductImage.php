<?php

namespace App\Models;

use App\Enums\BackgroundRemovalStatus;
use App\Enums\ProductImageType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded image for a product or a specific variant of it. `cdn_url`
 * is populated only when the storage disk is a real CDN-fronted one
 * (S3/R2 in production, per Decision D config) — url() below always
 * prefers it but falls back to resolving the disk URL directly so local
 * development (the `tenant` disk backed by local storage, per D-01) works
 * without one.
 *
 * `derivatives`/`size_bytes`/`background_removal_*` are Phase 5.C additions
 * on top of the Phase 5.B table — see App\Jobs\ProcessProductImageJob and
 * App\Jobs\RemoveBackgroundJob.
 */
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'variant_id',
        'disk',
        'path',
        'cdn_url',
        'type',
        'sort_order',
        'is_primary',
        'size_bytes',
        'derivatives',
        'background_removal_status',
        'background_removal_attempts',
        'background_removal_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductImageType::class,
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
            'derivatives' => 'array',
            'background_removal_status' => BackgroundRemovalStatus::class,
            'background_removal_attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function url(): string
    {
        return $this->cdn_url ?? Storage::disk($this->disk)->url($this->path);
    }

    /**
     * The processed thumbnail's URL for $size ("sm"/"md"/"lg"), or the
     * original's url() when ProcessProductImageJob hasn't produced one yet
     * (e.g. the job is still queued) — never a broken link while
     * processing catches up.
     */
    public function derivativeUrl(string $size): string
    {
        $path = $this->derivatives[$size] ?? null;

        return $path !== null ? Storage::disk($this->disk)->url($path) : $this->url();
    }
}
