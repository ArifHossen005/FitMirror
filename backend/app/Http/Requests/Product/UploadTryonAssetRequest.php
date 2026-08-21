<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;

/**
 * The manual AR-asset re-upload endpoint PROGRESS.md's 5.C checklist asks
 * for — lets an owner replace a `tryon`-type image by hand when the
 * automatic background-removal result is wrong, without waiting on
 * another AI pass.
 */
class UploadTryonAssetRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:png', 'max:8192'],
            'variant_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'The try-on asset must be a PNG with a transparent background.',
        ];
    }
}
