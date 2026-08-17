<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\BaseFormRequest;

class CancelSubscriptionRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'immediately' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
