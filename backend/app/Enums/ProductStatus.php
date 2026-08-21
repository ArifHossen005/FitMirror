<?php

namespace App\Enums;

/**
 * A product's publication state, independent of stock — an out-of-stock
 * product stays Published (Phase 5.D hides it from the catalog/kiosk based
 * on variant stock, without changing this column) and a Draft product with
 * plenty of stock still never appears anywhere customer-facing.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * Products the plan's `skus` limit counts against. Archived products
     * are excluded — archiving is FitMirror's soft "retire this listing
     * without losing its history" action, distinct from deleting it, and
     * must free the SKU slot the same way a permanently closed branch frees
     * a `branches` slot (StoreStatus::countsTowardBranchLimit()).
     */
    public function countsTowardSkuLimit(): bool
    {
        return $this !== self::Archived;
    }

    public function isVisibleToCustomers(): bool
    {
        return $this === self::Published;
    }
}
