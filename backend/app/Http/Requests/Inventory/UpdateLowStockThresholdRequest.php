<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;

class UpdateLowStockThresholdRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // present, not required — null is how a tenant clears the
            // alert entirely, distinct from omitting the field.
            'low_stock_threshold' => ['present', 'nullable', 'integer', 'min:0'],
        ];
    }
}
