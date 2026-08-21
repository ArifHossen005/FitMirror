<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole week in one payload — see StoreHoursService::replaceWeek() for
 * why partial updates are not offered.
 *
 * Field-level shape is checked here; the cross-field rules (a day that is
 * open needs both times, kiosk times come in pairs) live in the service so
 * the same guarantees hold for any future caller that is not an HTTP
 * request. The one rule kept here is duplicate days, because it is about
 * the payload's structure rather than about a single day's coherence.
 */
class UpdateStoreHoursRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['sometimes', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
            'days.*.kiosk_opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.kiosk_closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $days = $this->input('days');

                if (!is_array($days)) {
                    return;
                }

                $seen = array_column($days, 'day_of_week');

                if (count($seen) !== count(array_unique($seen))) {
                    $validator->errors()->add('days', 'Each day of the week may appear only once.');
                }
            },
        ];
    }

    /**
     * The validated week, shaped into exactly what StoreHoursService
     * expects. Two normalisations happen here rather than in the service,
     * so the service's contract stays a precise array shape whoever calls
     * it — an artisan command or a future import would build the same
     * structure without re-deriving these rules:
     *
     *   - MySQL TIME columns want H:i:s; the editor sends H:i.
     *   - An omitted or blank time is null, never an empty string.
     *
     * @return list<array{day_of_week: int, is_closed: bool, opens_at: string|null, closes_at: string|null, kiosk_opens_at: string|null, kiosk_closes_at: string|null}>
     */
    public function days(): array
    {
        $validated = $this->validated();
        $rawDays = is_array($validated['days'] ?? null) ? $validated['days'] : [];

        $days = [];

        foreach ($rawDays as $rawDay) {
            $day = is_array($rawDay) ? $rawDay : [];

            $days[] = [
                'day_of_week' => (int) ($day['day_of_week'] ?? 0),
                'is_closed' => (bool) ($day['is_closed'] ?? false),
                'opens_at' => $this->normaliseTime($day['opens_at'] ?? null),
                'closes_at' => $this->normaliseTime($day['closes_at'] ?? null),
                'kiosk_opens_at' => $this->normaliseTime($day['kiosk_opens_at'] ?? null),
                'kiosk_closes_at' => $this->normaliseTime($day['kiosk_closes_at'] ?? null),
            ];
        }

        return $days;
    }

    private function normaliseTime(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value . ':00' : null;
    }
}
