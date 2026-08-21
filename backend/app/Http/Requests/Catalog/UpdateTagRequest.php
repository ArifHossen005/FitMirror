<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseFormRequest;

class UpdateTagRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'The color must be a 6-digit hex code, e.g. #FF0000.',
        ];
    }
}
