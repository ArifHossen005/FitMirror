<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\BaseFormRequest;

class RecordManualPaymentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            // Omitted/null falls back to the plan's own listed price for
            // that cycle — only set this to record a negotiated/partial
            // amount that differs from the list price.
            'amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
