<?php

namespace App\Http\Requests\Store;

use App\Enums\StoreStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\Store;
use Illuminate\Validation\Rule;

/**
 * Editing a branch's profile: contact details, address, map link, social
 * links, status. Every field is `sometimes` so a PATCH may carry one key
 * without the others being read as "clear this".
 *
 * Branding (logo/banner) is a separate multipart endpoint —
 * StoreBrandingRequest — because PHP does not populate $_POST for a
 * multipart PATCH body, so mixing file uploads into this request would
 * only work over POST.
 */
class UpdateStoreRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'is_main' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'area' => ['sometimes', 'nullable', 'string', 'max:120'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'map_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'status' => ['sometimes', Rule::enum(StoreStatus::class)],
            'socials' => ['sometimes', 'nullable', 'array:' . implode(',', Store::SUPPORTED_SOCIALS)],
            'socials.*' => ['nullable', 'url', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The branch code may only contain letters, numbers, hyphens and underscores.',
        ];
    }
}
