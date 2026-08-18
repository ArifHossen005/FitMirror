<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CouponPreviewRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
        ];
    }
}
