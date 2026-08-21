<?php

namespace App\Services\Product;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Records and reads the audit trail PROGRESS.md's 5.B checklist asks for:
 * "record every price change with actor and timestamp". ProductService
 * calls recordIfChanged() from inside its own create()/update()
 * transactions whenever a price field is actually present and different
 * from its current value — a field merely being present in a PATCH with
 * the same value is not a change and is not logged, the same "only log
 * what actually moved" instinct as Store::getActivitylogOptions()
 * ->logOnlyDirty().
 */
class PriceHistoryService extends BaseService
{
    public function recordIfChanged(
        Product $product,
        ?ProductVariant $variant,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        ?User $actor,
    ): void {
        if ($oldValue === $newValue || $newValue === null) {
            return;
        }

        PriceHistory::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, PriceHistory>
     */
    public function history(Product $product, int $perPage): LengthAwarePaginator
    {
        return $product->priceHistory()->with('user:id,name')->paginate($perPage);
    }
}
