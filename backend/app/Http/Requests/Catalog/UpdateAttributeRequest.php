<?php

namespace App\Http\Requests\Catalog;

use App\Enums\AttributeStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * `type` is deliberately not editable after creation — AttributeService
 * enforces this, not this request, because the check ("does this attribute
 * already have values, or power any variant?") requires a database read.
 * Changing Color to Size after variants already reference its values would
 * silently invalidate every one of them.
 */
class UpdateAttributeRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(AttributeStatus::class)],
        ];
    }
}
