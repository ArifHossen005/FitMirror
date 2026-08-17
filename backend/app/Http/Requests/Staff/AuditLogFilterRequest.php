<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\BaseFormRequest;

class AuditLogFilterRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'in:created,updated,deleted,restored'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
