<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class PurchaseAddonRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
