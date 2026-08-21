<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The inventory report PROGRESS.md's 5.D checklist asks for: on-hand, low
 * stock, dead stock, movement history. "Dead stock" is deliberately an
 * empty array with an explanatory `dead_stock_note`, not a fabricated
 * metric — detecting it means "no try-on within N days", and
 * `try_on_sessions` does not exist until Phase 6. Same honest phase-
 * boundary this codebase used for Phase 4's own two skipped items rather
 * than shipping a number that looks like a real signal but measures
 * nothing.
 */
class InventoryReportController extends BaseApiController
{
    public function report(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $tenantId = $request->user()->tenant_id;

        $variants = ProductVariant::query()
            ->where('tenant_id', $tenantId)
            ->with('product:id,name,sku');

        $totalVariants = (clone $variants)->count();
        $outOfStockCount = (clone $variants)->where('stock', 0)->count();

        $lowStock = (clone $variants)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'variant_id' => $v->id,
                'sku' => $v->sku,
                'product_id' => $v->product_id,
                'product_name' => $v->product?->name,
                'stock' => $v->stock,
                'low_stock_threshold' => $v->low_stock_threshold,
            ])
            ->values();

        $recentMovements = StockMovement::query()
            ->where('tenant_id', $tenantId)
            ->with('store:id,name', 'variant:id,sku')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'variant_sku' => $m->variant?->sku,
                'store_name' => $m->store?->name,
                'type' => $m->type->value,
                'quantity' => $m->quantity,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->values();

        return $this->success([
            'summary' => [
                'total_variants' => $totalVariants,
                'out_of_stock_count' => $outOfStockCount,
                'low_stock_count' => $lowStock->count(),
            ],
            'low_stock' => $lowStock,
            'dead_stock' => [],
            'dead_stock_note' => 'Dead-stock detection requires try_on_sessions, which does not exist until Phase 6 — see PROGRESS.md 5.D.',
            'recent_movements' => $recentMovements,
        ]);
    }
}
