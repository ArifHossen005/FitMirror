<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\SizeChart;
use App\Models\Tenant;
use App\Services\BaseService;

/**
 * Size chart CRUD plus the attach/detach and kiosk-popup-payload endpoints
 * PROGRESS.md's 5.B checklist asks for. A chart is reusable across many
 * products (see the product_size_chart migration's own comment), so
 * deleting one detaches it everywhere via the pivot's cascadeOnDelete
 * rather than being blocked the way CategoryService::delete() blocks a
 * category still holding products — there is no equivalent "orphaned
 * product" failure mode here, a product simply loses that chart.
 */
class SizeChartService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): SizeChart
    {
        return SizeChart::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'rows' => $data['rows'],
            'unit' => $data['unit'] ?? 'in',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(SizeChart $chart, array $data): SizeChart
    {
        $chart->fill($data)->save();

        return $chart->refresh();
    }

    public function delete(SizeChart $chart): void
    {
        $chart->delete();
    }

    /**
     * @param list<int> $sizeChartIds
     */
    public function sync(Product $product, array $sizeChartIds): Product
    {
        $product->sizeCharts()->sync($sizeChartIds);

        return $product->refresh()->load('sizeCharts');
    }

    /**
     * The flat payload shape the kiosk's size-chart popup renders directly
     * — every chart attached to the product, in attachment order, with no
     * wrapping pagination envelope since a kiosk screen shows all of them
     * at once rather than paging through a list.
     *
     * @return list<array{id: int, name: string, unit: string, rows: mixed}>
     */
    public function kioskPayload(Product $product): array
    {
        return $product->sizeCharts()->get()->map(fn (SizeChart $chart) => [
            'id' => $chart->id,
            'name' => $chart->name,
            'unit' => $chart->unit,
            'rows' => $chart->rows,
        ])->all();
    }
}
