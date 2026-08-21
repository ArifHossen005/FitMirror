<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;

class StoreSizeChartRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['sometimes', 'string', 'max:8'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.size' => ['required', 'string', 'max:32'],
            'rows.*.measurements' => ['required', 'array', 'min:1'],
            'rows.*.measurements.*' => ['required', 'string', 'max:32'],
        ];
    }
}
