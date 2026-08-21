<?php

namespace App\Enums;

/**
 * Tracks one `product_images` (gallery-type) row's progress through
 * App\Jobs\RemoveBackgroundJob. Null (no value at all, not a case here)
 * means "never submitted" — a row that has never been sent for AI
 * background removal, the ordinary state of most gallery photos.
 */
enum BackgroundRemovalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
