<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Product\StoreSizeChartRequest;
use App\Http\Requests\Product\SyncProductSizeChartsRequest;
use App\Http\Requests\Product\UpdateSizeChartRequest;
use App\Models\Product;
use App\Models\SizeChart;
use App\Services\Product\SizeChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Size chart CRUD, gated on the `products.*` permissions (ProductPolicy) —
 * a chart only exists to be attached to a product, so it carries no
 * independent permission module of its own, the same reasoning
 * StorePolicy::manageHours() applies to opening hours.
 */
class SizeChartController extends BaseApiController
{
    public function __construct(private readonly SizeChartService $sizeCharts) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SizeChart::class);

        $charts = SizeChart::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return $this->success(['size_charts' => $charts->map(fn (SizeChart $c) => $this->present($c))->values()]);
    }

    public function store(StoreSizeChartRequest $request): JsonResponse
    {
        $this->authorize('create', SizeChart::class);

        $chart = $this->sizeCharts->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($chart), 'Size chart created successfully.');
    }

    public function show(SizeChart $sizeChart): JsonResponse
    {
        $this->authorize('view', $sizeChart);

        return $this->success($this->present($sizeChart));
    }

    public function update(UpdateSizeChartRequest $request, SizeChart $sizeChart): JsonResponse
    {
        $this->authorize('update', $sizeChart);

        $updated = $this->sizeCharts->update($sizeChart, $request->validated());

        return $this->success($this->present($updated), 'Size chart updated successfully.');
    }

    public function destroy(SizeChart $sizeChart): JsonResponse
    {
        $this->authorize('delete', $sizeChart);

        $this->sizeCharts->delete($sizeChart);

        return $this->noContent();
    }

    public function sync(SyncProductSizeChartsRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageSizeCharts', $product);

        $updated = $this->sizeCharts->sync($product, $request->validated()['size_chart_ids']);

        return $this->success([
            'size_charts' => $updated->sizeCharts->map(fn (SizeChart $c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SizeChart $chart): array
    {
        return [
            'id' => $chart->id,
            'name' => $chart->name,
            'unit' => $chart->unit,
            'rows' => $chart->rows,
        ];
    }
}
