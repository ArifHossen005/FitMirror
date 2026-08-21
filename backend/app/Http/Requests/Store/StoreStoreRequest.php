<?php

namespace App\Http\Requests\Store;

use App\Enums\StoreStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\Store;
use Illuminate\Validation\Rule;

/**
 * Creating a branch. Uniqueness of `code` is deliberately not asserted
 * here — it is per-tenant and must also consider soft-deleted rows, which
 * StoreService::create() checks against the resolved tenant. A `unique`
 * rule here would have to re-derive the tenant from the request and would
 * still miss the trashed rows the database's own index covers.
 */
class StoreStoreRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'is_main' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'status' => ['sometimes', Rule::enum(StoreStatus::class)],
            // `array:a,b,c` restricts which keys may appear, so an unknown
            // network is a reportable validation error rather than
            // something StoreService::normaliseSocials() silently drops.
            'socials' => ['nullable', 'array:' . implode(',', Store::SUPPORTED_SOCIALS)],
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
