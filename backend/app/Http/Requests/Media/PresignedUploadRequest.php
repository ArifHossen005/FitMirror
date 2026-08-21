<?php

namespace App\Http\Requests\Media;

use App\Http\Requests\BaseFormRequest;

class PresignedUploadRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp'],
        ];
    }
}
