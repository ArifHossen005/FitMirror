<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SyncProductOccasionsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occasion_ids' => ['present', 'array'],
            'occasion_ids.*' => [
                'integer', Rule::exists('occasions', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
