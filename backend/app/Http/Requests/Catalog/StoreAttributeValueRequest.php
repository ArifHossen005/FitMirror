<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseFormRequest;

/**
 * `hex_color` is only meaningful when the parent attribute's type is Color
 * (AttributeType::supportsHexColor()) — AttributeValueService rejects it
 * for any other type rather than this request, because the check depends
 * on the already-loaded parent Attribute, not on the request body alone.
 */
class StoreAttributeValueRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
            'hex_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hex_color.regex' => 'The color must be a 6-digit hex code, e.g. #FF0000.',
        ];
    }
}
