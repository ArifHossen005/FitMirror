<?php

namespace App\Enums;

/**
 * What an uploaded product image is for. `Tryon` is the background-removed,
 * AR-ready asset Phase 5.C's RemoveBackgroundJob produces — stored as its
 * own row (not a flag on a gallery image) so a product can have many
 * gallery photos but exactly one active try-on asset per variant.
 */
enum ProductImageType: string
{
    case Gallery = 'gallery';
    case Tryon = 'tryon';
    case Swatch = 'swatch';

    public function label(): string
    {
        return match ($this) {
            self::Gallery => 'Gallery',
            self::Tryon => 'Try-On Asset',
            self::Swatch => 'Swatch',
        };
    }
}
