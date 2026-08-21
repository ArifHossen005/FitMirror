<?php

namespace App\Services\Store;

use App\Models\Store;
use App\Models\StoreHour;
use App\Services\BaseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Weekly opening hours for a branch, and the single authority on "may a
 * kiosk in this branch run a session right now" — consumed both by
 * App\Http\Middleware\EnsureKioskWithinActiveHours (which blocks the
 * request) and by the kiosk's own availability endpoint (which lets the
 * device render a "we are closed" screen instead of erroring).
 *
 * A branch with no hours configured at all is treated as always open. That
 * is deliberate: hours are an optional restriction a tenant opts into, and
 * defaulting an unconfigured branch to "always closed" would silently
 * brick every kiosk the moment it was paired.
 */
class StoreHoursService extends BaseService
{
    /**
     * Replaces the branch's whole week in one call. A partial update is
     * not offered — the editor UI submits all seven days together, and
     * per-day PATCHes would let a half-applied week (Monday saved, Tuesday
     * rejected) reach the kiosk guard.
     *
     * @param list<array{day_of_week: int, is_closed: bool, opens_at: string|null, closes_at: string|null, kiosk_opens_at: string|null, kiosk_closes_at: string|null}> $days
     * @return Collection<int, StoreHour>
     */
    public function replaceWeek(Store $store, array $days): Collection
    {
        foreach ($days as $day) {
            $this->assertDayIsCoherent($day);
        }

        return $this->transaction(function () use ($store, $days) {
            foreach ($days as $day) {
                $isClosed = $day['is_closed'];

                $store->hours()->updateOrCreate(
                    ['day_of_week' => $day['day_of_week']],
                    [
                        'tenant_id' => $store->tenant_id,
                        'is_closed' => $isClosed,
                        // A closed day carries no times at all, so a tenant
                        // toggling a day shut and back open again cannot
                        // resurrect stale hours they never re-confirmed.
                        'opens_at' => $isClosed ? null : $day['opens_at'],
                        'closes_at' => $isClosed ? null : $day['closes_at'],
                        'kiosk_opens_at' => $isClosed ? null : $day['kiosk_opens_at'],
                        'kiosk_closes_at' => $isClosed ? null : $day['kiosk_closes_at'],
                    ],
                );
            }

            return $store->hours()->orderBy('day_of_week')->get();
        });
    }

    /**
     * @return Collection<int, StoreHour>
     */
    public function forStore(Store $store): Collection
    {
        return $store->hours()->orderBy('day_of_week')->get();
    }

    /**
     * Whether a kiosk in $store may run a session at $at (defaults to now).
     *
     * $at is converted into the branch's own timezone before any comparison
     * — the rows hold wall-clock times, and the server runs in UTC per
     * Decision D-07, so comparing without converting would put a Dhaka shop
     * six hours out.
     */
    public function kioskIsOpen(Store $store, ?CarbonImmutable $at = null): bool
    {
        $hours = $this->forStore($store);

        if ($hours->isEmpty()) {
            return true;
        }

        $local = ($at ?? CarbonImmutable::now())->setTimezone($store->timezone);

        // An overnight window that started yesterday is still running now,
        // so yesterday's row has to be consulted as well as today's — see
        // StoreHour::kioskIsOpenAt() for how a wrapping window is read.
        $todayRow = $hours->firstWhere('day_of_week', $local->dayOfWeek);
        $yesterdayRow = $hours->firstWhere('day_of_week', $local->subDay()->dayOfWeek);

        if ($todayRow instanceof StoreHour && $todayRow->kioskIsOpenAt($local)) {
            return true;
        }

        return $yesterdayRow instanceof StoreHour
            && $this->isOvernightWindow($yesterdayRow)
            && $yesterdayRow->kioskIsOpenAt($local);
    }

    /**
     * The branch's current kiosk availability, shaped for the kiosk app's
     * "closed" screen: whether it may run now, and when the window next
     * opens so the device can show a countdown rather than a bare refusal.
     *
     * @return array{is_open: bool, timezone: string, local_time: string, next_opens_at: string|null}
     */
    public function availability(Store $store, ?CarbonImmutable $at = null): array
    {
        $local = ($at ?? CarbonImmutable::now())->setTimezone($store->timezone);
        $isOpen = $this->kioskIsOpen($store, $at);

        return [
            'is_open' => $isOpen,
            'timezone' => $store->timezone,
            'local_time' => $local->toIso8601String(),
            'next_opens_at' => $isOpen ? null : $this->nextOpeningInstant($store, $local)?->toIso8601String(),
        ];
    }

    /**
     * Scans forward one week — the schedule repeats weekly, so if no window
     * opens within seven days none ever will (every day is marked closed).
     */
    private function nextOpeningInstant(Store $store, CarbonImmutable $local): ?CarbonImmutable
    {
        $hours = $this->forStore($store);

        if ($hours->isEmpty()) {
            return null;
        }

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $local->addDays($offset);
            $row = $hours->firstWhere('day_of_week', $day->dayOfWeek);

            if (!$row instanceof StoreHour) {
                continue;
            }

            $window = $row->kioskWindow();

            if ($window === null) {
                continue;
            }

            $opensAt = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $window[0], $store->timezone);

            if ($opensAt->greaterThan($local)) {
                return $opensAt;
            }
        }

        return null;
    }

    private function isOvernightWindow(StoreHour $row): bool
    {
        $window = $row->kioskWindow();

        return $window !== null && $window[1] < $window[0];
    }

    /**
     * Rules that need both ends of a day's row at once, which a field-level
     * validation rule cannot express.
     *
     * @param array{day_of_week: int, is_closed: bool, opens_at: string|null, closes_at: string|null, kiosk_opens_at: string|null, kiosk_closes_at: string|null} $day
     */
    private function assertDayIsCoherent(array $day): void
    {
        $dayLabel = StoreHour::DAY_NAMES[$day['day_of_week']] ?? 'Day ' . $day['day_of_week'];

        if ($day['is_closed']) {
            return;
        }

        if ($day['opens_at'] === null || $day['closes_at'] === null) {
            throw ValidationException::withMessages([
                'days' => ["{$dayLabel} needs both an opening and a closing time, or must be marked closed."],
            ]);
        }

        $kioskOpens = $day['kiosk_opens_at'];
        $kioskCloses = $day['kiosk_closes_at'];

        if (($kioskOpens === null) !== ($kioskCloses === null)) {
            throw ValidationException::withMessages([
                'days' => ["{$dayLabel} needs both kiosk times, or neither — one alone has no window to define."],
            ]);
        }
    }
}
