<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Attach/detach in one call — the request always carries the complete
 * desired set of size charts for the product, and ProductSizeChartService
 * ::sync() diffs it against what's already attached (a plain
 * BelongsToMany::sync() call), rather than exposing separate attach/detach
 * endpoints for what is, from the dashboard's point of view, a single
 * multi-select field.
 */
class SyncProductSizeChartsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'size_chart_ids' => ['present', 'array'],
            'size_chart_ids.*' => [
                'integer', Rule::exists('size_charts', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
