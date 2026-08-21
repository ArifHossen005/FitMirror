<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_id' => [
                'required', 'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            // Signed — a positive quantity receives stock in, negative
            // writes it off. Zero is rejected in the service (an
            // adjustment that changes nothing is not a real movement).
            'quantity' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
