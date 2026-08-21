<?php

namespace App\Http\Requests\Catalog;

use App\Enums\AttributeType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
