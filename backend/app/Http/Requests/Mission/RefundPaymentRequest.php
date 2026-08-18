<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\BaseFormRequest;

class RefundPaymentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Omitted/null refunds the full original payment amount.
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
