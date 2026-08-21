<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

/**
 * Filters for the roster listing. The range is capped rather than
 * unbounded — the weekly grid asks for seven days, a month view for
 * thirty-one, and an uncapped range would let one request pull a tenant's
 * entire scheduling history through a JSON response.
 */
class ShiftScheduleRequest extends BaseFormRequest
{
    public const MAX_RANGE_DAYS = 92;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'store_id' => ['sometimes', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = strtotime((string) $this->input('from'));
                $to = strtotime((string) $this->input('to'));

                if ($from === false || $to === false) {
                    return;
                }

                if (($to - $from) / 86400 > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'to',
                        'The schedule range cannot be longer than ' . self::MAX_RANGE_DAYS . ' days.',
                    );
                }
            },
        ];
    }
}
