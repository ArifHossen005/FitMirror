<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

/**
 * Branch logo and banner upload. POST + multipart rather than PATCH,
 * because PHP does not parse a multipart body on any verb but POST — a
 * multipart PATCH arrives with empty $_POST and $_FILES.
 *
 * The crop is applied client-side (the dashboard's branding page ships the
 * cropped result, not the original), so the server's job here is to bound
 * what it accepts: real image mime types only, and a size ceiling that
 * keeps one careless upload from eating a Free plan's whole 5 GB.
 */
class StoreBrandingRequest extends BaseFormRequest
{
    /** 5 MB — comfortably above a cropped logo or banner, far below a raw phone photo. */
    private const MAX_KILOBYTES = 5120;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_KILOBYTES],
            'banner' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_KILOBYTES],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_banner' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A request that names neither an upload nor a removal has nothing to
     * do, and is far more likely a client bug (a form posted with the file
     * input never populated) than an intentional no-op.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $touchesSomething = $this->hasFile('logo')
                    || $this->hasFile('banner')
                    || $this->boolean('remove_logo')
                    || $this->boolean('remove_banner');

                if (!$touchesSomething) {
                    $validator->errors()->add('logo', 'Provide a logo or banner to upload, or ask for one to be removed.');
                }
            },
        ];
    }
}
