<?php

namespace App\Services\Media;

use App\Models\ProductImage;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resizes an uploaded product image into sm/md/lg WebP thumbnails —
 * PROGRESS.md's 5.C checklist item, run by App\Jobs\ProcessProductImageJob
 * after every upload. The original file is left untouched; derivatives are
 * additional files at their own paths, recorded on `product_images
 * .derivatives` (see that column's migration comment for why JSON rather
 * than fixed columns).
 */
class ImageProcessingService
{
    /**
     * Width in pixels per named size. scaleDown() never enlarges a smaller
     * source, so a small original simply keeps its own size for a "sm"
     * derivative rather than being upscaled and softened.
     *
     * @var array<string, int>
     */
    private const SIZES = [
        'sm' => 300,
        'md' => 800,
        'lg' => 1600,
    ];

    private const WEBP_QUALITY = 82;

    /**
     * @return array<string, string> size => storage path
     */
    public function generateDerivatives(ProductImage $image): array
    {
        // Resolved via the 'image' container alias intervention/image-laravel
        // registers its singleton under (Facades\Image::BINDING) — not
        // app(ImageManager::class), which has no explicit binding and would
        // fail auto-wiring: ImageManager's constructor takes a required
        // string|DriverInterface $driver with no default the container
        // could guess.
        $manager = app('image');
        $source = Storage::disk($image->disk)->get($image->path);
        $directory = TenantStorage::path($image->tenant_id, 'products/' . $image->product_id . '/derivatives');

        $derivatives = [];

        foreach (self::SIZES as $size => $width) {
            $encoded = $manager->read($source)
                ->scaleDown(width: $width)
                ->toWebp(quality: self::WEBP_QUALITY);

            $path = $directory . '/' . $size . '-' . Str::random(16) . '.webp';

            Storage::disk($image->disk)->put($path, (string) $encoded);

            $derivatives[$size] = $path;
        }

        return $derivatives;
    }

    public function deleteDerivatives(ProductImage $image): void
    {
        foreach ($image->derivatives ?? [] as $path) {
            if (Storage::disk($image->disk)->exists($path)) {
                Storage::disk($image->disk)->delete($path);
            }
        }
    }
}
