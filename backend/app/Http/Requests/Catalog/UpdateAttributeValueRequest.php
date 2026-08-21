<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseFormRequest;

class UpdateAttributeValueRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'string', 'max:255'],
            'hex_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
