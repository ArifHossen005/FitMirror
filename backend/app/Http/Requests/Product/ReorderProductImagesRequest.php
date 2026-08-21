<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ReorderProductImagesRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => [
                'required', 'integer', 'distinct',
                Rule::exists('product_images', 'id')->where('tenant_id', $tenantId),
            ],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
            'primary_image_id' => [
                'sometimes', 'integer',
                Rule::exists('product_images', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
