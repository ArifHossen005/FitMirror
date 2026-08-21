<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;

class UpdateSizeChartRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['sometimes', 'string', 'max:8'],
            'rows' => ['sometimes', 'array', 'min:1'],
            'rows.*.size' => ['required_with:rows', 'string', 'max:32'],
            'rows.*.measurements' => ['required_with:rows', 'array', 'min:1'],
            'rows.*.measurements.*' => ['required', 'string', 'max:32'],
        ];
    }
}
