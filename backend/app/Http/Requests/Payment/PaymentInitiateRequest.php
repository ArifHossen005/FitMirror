<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\BaseFormRequest;

class PaymentInitiateRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
