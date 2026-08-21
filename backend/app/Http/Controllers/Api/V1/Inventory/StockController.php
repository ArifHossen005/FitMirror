<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
use App\Http\Requests\Inventory\UpdateLowStockThresholdRequest;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock adjustment, branch-to-branch transfer, and per-variant movement
 * history — PROGRESS.md's 5.D checklist. Gated on the same `products.*`
 * permissions as the rest of the catalog (a variant's stock is a product-
 * management concern, not a distinct permission module — same reasoning
 * SizeChartController and StoreHoursController both already follow for
 * their own sub-resources).
 */
class StockController extends BaseApiController
{
    public function __construct(private readonly StockService $stock) {}

    public function show(ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $variant->product);

        return $this->success([
            'variant_id' => $variant->id,
            'total_stock' => $variant->stock,
            'low_stock_threshold' => $variant->low_stock_threshold,
            'is_low_stock' => $variant->isLowStock(),
            'by_store' => $this->stock->breakdownByStore($variant),
        ]);
    }

    public function adjust(AdjustStockRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant->product);

        $data = $request->validated();
        $store = Store::query()->findOrFail($data['store_id']);

        $movement = $this->stock->adjust($variant, $store, $data['quantity'], $data['note'] ?? null, $request->user());

        return $this->created($this->presentMovement($movement), 'Stock adjusted successfully.');
    }

    public function transfer(TransferStockRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant->product);

        $data = $request->validated();
        $from = Store::query()->findOrFail($data['from_store_id']);
        $to = Store::query()->findOrFail($data['to_store_id']);

        $result = $this->stock->transfer($variant, $from, $to, $data['quantity'], $data['note'] ?? null, $request->user());

        return $this->created([
            'out' => $this->presentMovement($result['out']),
            'in' => $this->presentMovement($result['in']),
        ], 'Stock transferred successfully.');
    }

    public function updateLowStockThreshold(UpdateLowStockThresholdRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant->product);

        $variant->update(['low_stock_threshold' => $request->validated()['low_stock_threshold']]);

        return $this->success([
            'variant_id' => $variant->id,
            'low_stock_threshold' => $variant->low_stock_threshold,
        ], 'Low-stock threshold updated successfully.');
    }

    public function movements(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $variant->product);

        $movements = $variant->stockMovements()->with('store:id,name', 'user:id,name')->paginate($this->perPage($request));

        return $this->success([
            'movements' => $movements->through(fn (StockMovement $m) => $this->presentMovement($m))->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMovement(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'variant_id' => $movement->variant_id,
            'store_id' => $movement->store_id,
            'store_name' => $movement->relationLoaded('store') ? $movement->store?->name : null,
            'type' => $movement->type->value,
            'type_label' => $movement->type->label(),
            'quantity' => $movement->quantity,
            'reference' => $movement->reference,
            'note' => $movement->note,
            'changed_by' => $movement->relationLoaded('user') ? $movement->user?->name : null,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
