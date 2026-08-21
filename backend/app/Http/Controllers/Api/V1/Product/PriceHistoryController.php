<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\Product\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceHistoryController extends BaseApiController
{
    public function __construct(private readonly PriceHistoryService $history) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $history = $this->history->history($product, $this->perPage($request));

        return $this->success([
            'price_history' => $history->through(fn (PriceHistory $row) => [
                'id' => $row->id,
                'variant_id' => $row->variant_id,
                'field' => $row->field,
                'old_value' => $row->old_value !== null ? (float) $row->old_value : null,
                'new_value' => (float) $row->new_value,
                'changed_by' => $row->user?->name,
                'created_at' => $row->created_at->toIso8601String(),
            ])->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }
}
